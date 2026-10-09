<?php
namespace app\services;

use app\models\Customer;
use app\models\Invoice;
use app\models\InvoiceItem;
use app\models\Product;
use yii\db\Transaction;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class InvoiceService
{
    private $calculator;

    public function __construct(InvoiceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function create(array $payload)
    {
        $this->validatePayload($payload, true);
        $this->validateDateRange($payload['issue_date'], $payload['due_date']);
        $this->assertCustomerExists((int)$payload['customer_id']);

        $transaction = Invoice::getDb()->beginTransaction(Transaction::READ_COMMITTED);
        try {
            $invoice = new Invoice();
            $invoice->invoice_number = $this->generateInvoiceNumber();
            $invoice->customer_id = (int)$payload['customer_id'];
            $invoice->subject = trim($payload['subject']);
            $invoice->issue_date = $payload['issue_date'];
            $invoice->due_date = $payload['due_date'];
            $invoice->status = Invoice::STATUS_DRAFT;
            $invoice->tax_rate = isset($payload['tax_rate']) ? (float)$payload['tax_rate'] : 0;
            $invoice->amount_paid = 0;
            $invoice->notes = isset($payload['notes']) ? $payload['notes'] : null;

            $this->persistInvoice($invoice);
            $this->replaceItems($invoice, $payload['items']);
            $this->recalculate($invoice);
            $invoice->save(false);

            $transaction->commit();
            return $invoice;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function update($id, array $payload)
    {
        $invoice = Invoice::findOne((int)$id);
        if (!$invoice) {
            throw new NotFoundHttpException('Invoice not found.');
        }
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
            throw new BadRequestHttpException('Paid or cancelled invoices cannot be edited.');
        }

        $nextIssueDate = array_key_exists('issue_date', $payload) ? $payload['issue_date'] : $invoice->issue_date;
        $nextDueDate = array_key_exists('due_date', $payload) ? $payload['due_date'] : $invoice->due_date;
        $this->validateDateRange($nextIssueDate, $nextDueDate);

        if (array_key_exists('customer_id', $payload)) {
            $this->assertCustomerExists((int)$payload['customer_id']);
        }
        if (array_key_exists('subject', $payload) && trim((string)$payload['subject']) === '') {
            throw new BadRequestHttpException('subject cannot be empty.');
        }
        if (array_key_exists('tax_rate', $payload) && ((float)$payload['tax_rate'] < 0 || (float)$payload['tax_rate'] > 100)) {
            throw new BadRequestHttpException('tax_rate must be between 0 and 100.');
        }
        if (array_key_exists('items', $payload) && !is_array($payload['items'])) {
            throw new BadRequestHttpException('items must be an array.');
        }

        $transaction = Invoice::getDb()->beginTransaction();
        try {
            foreach (['customer_id', 'issue_date', 'due_date', 'subject', 'tax_rate', 'notes'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $invoice->$field = $field === 'subject' ? trim((string)$payload[$field]) : $payload[$field];
                }
            }

            $this->persistInvoice($invoice);

            if (array_key_exists('items', $payload)) {
                $this->replaceItems($invoice, $payload['items']);
                $this->recalculate($invoice);
            }

            $invoice->save(false);
            $transaction->commit();
            return $invoice;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function send($id)
    {
        $invoice = $this->find($id);
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new BadRequestHttpException('Only draft invoices can be sent.');
        }
        $invoice->status = Invoice::STATUS_SENT;
        if (!$invoice->save(false)) {
            throw new BadRequestHttpException('Unable to send invoice.');
        }
        return $invoice;
    }

    public function markPaid($id)
    {
        $invoice = $this->find($id);
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw new BadRequestHttpException('Cancelled invoices cannot be marked as paid.');
        }
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw new BadRequestHttpException('Invoice is already marked as paid.');
        }

        $invoice->status = Invoice::STATUS_PAID;
        $invoice->amount_paid = $invoice->total;
        if (!$invoice->save(false)) {
            throw new BadRequestHttpException('Unable to update invoice status.');
        }
        return $invoice;
    }

    private function find($id)
    {
        $invoice = Invoice::findOne((int)$id);
        if (!$invoice) {
            throw new NotFoundHttpException('Invoice not found.');
        }
        return $invoice;
    }

    private function persistInvoice(Invoice $invoice)
    {
        if (!$invoice->validate()) {
            throw new BadRequestHttpException(implode(' ', $invoice->getFirstErrors()));
        }
        if (!$invoice->save(false)) {
            throw new BadRequestHttpException('Unable to save invoice.');
        }
    }

    private function replaceItems(Invoice $invoice, array $items)
    {
        if (!$items) {
            throw new BadRequestHttpException('Invoice must contain at least one item.');
        }

        InvoiceItem::deleteAll(['invoice_id' => $invoice->id]);

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new BadRequestHttpException('Each invoice item must be an object.');
            }

            $product = null;
            if (isset($item['product_id']) && $item['product_id'] !== '') {
                $product = Product::findOne(['id' => (int)$item['product_id'], 'is_active' => 1]);
                if (!$product) {
                    throw new BadRequestHttpException('Invoice item #' . ($index + 1) . ' references an inactive or unknown product.');
                }
            }

            $description = isset($item['description']) && trim((string)$item['description']) !== ''
                ? trim((string)$item['description'])
                : ($product ? $product->name : null);
            if ($description === null) {
                throw new BadRequestHttpException('Invoice item #' . ($index + 1) . ' requires description or product_id.');
            }

            if (!array_key_exists('quantity', $item)) {
                throw new BadRequestHttpException('Invoice item #' . ($index + 1) . ' requires quantity.');
            }
            if (!array_key_exists('unit_price', $item)) {
                if (!$product) {
                    throw new BadRequestHttpException('Invoice item #' . ($index + 1) . ' requires unit_price when product_id is not provided.');
                }
                $item['unit_price'] = $product->unit_price;
            }

            $model = new InvoiceItem();
            $model->invoice_id = $invoice->id;
            $model->product_id = $product ? $product->id : null;
            $model->description = $description;
            $model->quantity = $item['quantity'];
            $model->unit_price = $item['unit_price'];
            $model->line_total = $this->calculator->lineTotal($model->quantity, $model->unit_price);

            if (!$model->validate()) {
                throw new BadRequestHttpException('Invoice item #' . ($index + 1) . ': ' . implode(' ', $model->getFirstErrors()));
            }
            if (!$model->save(false)) {
                throw new BadRequestHttpException('Unable to save invoice item #' . ($index + 1) . '.');
            }
        }
    }

    private function recalculate(Invoice $invoice)
    {
        $aggregate = InvoiceItem::find()
            ->select([
                'item_count' => 'COUNT(*)',
                'subtotal' => 'COALESCE(SUM(line_total), 0)',
            ])
            ->where(['invoice_id' => $invoice->id])
            ->asArray()
            ->one();

        $subtotal = round((float)$aggregate['subtotal'], 2);
        $tax = $this->calculator->tax($subtotal, $invoice->tax_rate);
        $invoice->item_count = (int)$aggregate['item_count'];
        $invoice->subtotal = $subtotal;
        $invoice->tax_amount = $tax;
        $invoice->total = $this->calculator->total($subtotal, $tax);
    }

    private function validatePayload(array $payload, $isCreate = false)
    {
        foreach (['customer_id', 'issue_date', 'due_date', 'subject', 'items'] as $field) {
            if ($isCreate && !array_key_exists($field, $payload)) {
                throw new BadRequestHttpException("Missing required field: {$field}.");
            }
        }
        if (isset($payload['items']) && !is_array($payload['items'])) {
            throw new BadRequestHttpException('items must be an array.');
        }
        if ($isCreate && trim((string)$payload['subject']) === '') {
            throw new BadRequestHttpException('subject cannot be empty.');
        }
        if (isset($payload['tax_rate']) && ((float)$payload['tax_rate'] < 0 || (float)$payload['tax_rate'] > 100)) {
            throw new BadRequestHttpException('tax_rate must be between 0 and 100.');
        }
    }

    private function validateDateRange($issueDate, $dueDate)
    {
        $issue = \DateTime::createFromFormat('!Y-m-d', (string)$issueDate);
        $due = \DateTime::createFromFormat('!Y-m-d', (string)$dueDate);
        $issueErrors = \DateTime::getLastErrors();
        if (!$issue || !$due || ($issueErrors && ($issueErrors['warning_count'] || $issueErrors['error_count']))) {
            throw new BadRequestHttpException('issue_date and due_date must use YYYY-MM-DD format.');
        }
        if ($due < $issue) {
            throw new BadRequestHttpException('due_date cannot be earlier than issue_date.');
        }
    }

    private function assertCustomerExists($id)
    {
        if (!Customer::find()->where(['id' => $id])->exists()) {
            throw new BadRequestHttpException('Customer not found.');
        }
    }

    private function generateInvoiceNumber()
    {
        return 'INV-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
