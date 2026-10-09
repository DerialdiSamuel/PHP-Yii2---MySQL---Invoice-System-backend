<?php
namespace app\controllers\v1;

use app\models\Invoice;
use app\models\InvoiceItem;
use app\services\InvoiceService;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class InvoiceController extends BaseApiController
{
    private $service;

    public function __construct($id, $module, InvoiceService $service, $config = [])
    {
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionIndex()
    {
        $request = Yii::$app->request;
        $limit = min(100, max(1, (int)$request->get('per-page', 25)));

        $query = Invoice::find()
            ->alias('i')
            ->select([
                'i.id', 'i.invoice_number', 'i.customer_id', 'i.issue_date', 'i.due_date',
                'i.subject', 'i.item_count', 'i.status', 'i.subtotal', 'i.tax_rate',
                'i.tax_amount', 'i.total', 'i.amount_paid', 'i.notes',
                'i.created_at', 'i.updated_at', 'customer_name' => 'c.name',
            ])
            ->leftJoin(['c' => '{{%customers}}'], 'c.id = i.customer_id')
            ->orderBy(['i.id' => SORT_DESC]);

        if (($status = trim((string)$request->get('status', ''))) !== '') {
            if (!array_key_exists($status, Invoice::statuses())) {
                throw new BadRequestHttpException('Invalid invoice status.');
            }
            $query->andWhere(['i.status' => $status]);
        }
        if (($customerId = $request->get('customer_id')) !== null && $customerId !== '') {
            $query->andWhere(['i.customer_id' => (int)$customerId]);
        }
        if (($search = trim((string)$request->get('q', ''))) !== '') {
            $query->andWhere([
                'or',
                ['like', 'i.invoice_number', $search],
                ['like', 'i.subject', $search],
                ['like', 'c.name', $search],
            ]);
        }
        foreach (['issue_date' => 'issue_date', 'due_date' => 'due_date'] as $queryParam => $column) {
            if (($value = $request->get($queryParam)) !== null && $value !== '') {
                $query->andWhere(['i.' . $column => $value]);
            }
        }

        if (($after = $request->get('after')) !== null && $after !== '') {
            if (!ctype_digit((string)$after)) {
                throw new BadRequestHttpException('after must be a positive integer cursor.');
            }
            $query->andWhere(['<', 'i.id', (int)$after]);
        }

        $rows = $query->limit($limit + 1)->asArray()->all();
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        return [
            'items' => $rows,
            'pagination' => [
                'per_page' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $hasMore && $rows ? (int)$rows[count($rows) - 1]['id'] : null,
            ],
        ];
    }

    public function actionView($id)
    {
        $invoice = Invoice::find()->where(['id' => (int)$id])->with('customer')->one();
        if (!$invoice) {
            throw new NotFoundHttpException('Invoice not found.');
        }

        $data = $invoice->toArray();
        $data['customer'] = $invoice->customer ? $invoice->customer->toArray() : null;
        $data['items'] = InvoiceItem::find()->where(['invoice_id' => $invoice->id])->asArray()->all();
        $data['amount_due'] = $invoice->getAmountDue();
        return $data;
    }

    public function actionCreate()
    {
        $invoice = $this->service->create(Yii::$app->request->bodyParams);
        Yii::$app->response->statusCode = 201;
        return $invoice;
    }

    public function actionUpdate($id)
    {
        return $this->service->update($id, Yii::$app->request->bodyParams);
    }

    public function actionDelete($id)
    {
        $invoice = Invoice::findOne($id);
        if (!$invoice) {
            throw new NotFoundHttpException('Invoice not found.');
        }
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new BadRequestHttpException('Only draft invoices can be deleted.');
        }

        $transaction = Invoice::getDb()->beginTransaction();
        try {
            InvoiceItem::deleteAll(['invoice_id' => $invoice->id]);
            $invoice->delete();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Yii::$app->response->statusCode = 204;
        return null;
    }

    public function actionSend($id)
    {
        return $this->service->send($id);
    }

    public function actionMarkPaid($id)
    {
        return $this->service->markPaid($id);
    }
}
