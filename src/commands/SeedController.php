<?php
namespace app\commands;

use app\models\Customer;
use app\models\Product;
use Yii;
use yii\console\Controller;

class SeedController extends Controller
{
    public function actionDemo()
    {
        $customer = Customer::findOne(['email' => 'billing@acme.test']);
        if (!$customer) {
            $customer = new Customer();
            $customer->name = 'Acme Corporation';
            $customer->email = 'billing@acme.test';
            $customer->phone = '+62 21 555 0100';
            $customer->company = 'Acme Corporation';
            $customer->address = 'Jakarta, Indonesia';
            $customer->save(false);
        }

        foreach ([
            ['SKU-001', 'Website Development', 'service', 15000000],
            ['SKU-002', 'Maintenance Service', 'service', 5000000],
            ['SKU-003', 'Consulting', 'service', 2500000],
            ['SKU-004', 'Monitor', 'hardware', 3500000],
            ['SKU-005', 'Printer', 'hardware', 4500000],
        ] as $row) {
            if (Product::findOne(['sku' => $row[0]])) {
                continue;
            }
            $p = new Product();
            $p->sku = $row[0];
            $p->name = $row[1];
            $p->type = $row[2];
            $p->unit_price = $row[3];
            $p->is_active = 1;
            $p->save(false);
        }

        $this->stdout("Demo data is ready. Customer ID: {$customer->id}\n");
    }

    /**
     * Generate a large, repeatable dataset for pagination and query-plan checks.
     *
     * Usage: php yii seed/bigdata 1000000 1000
     * This appends synthetic invoices and one invoice item per invoice. It does
     * not delete or modify existing business data.
     */
    public function actionBigdata($count = 1000000, $batchSize = 1000)
    {
        $count = (int) $count;
        $batchSize = (int) $batchSize;
        if ($count < 1 || $batchSize < 1 || $batchSize > 5000) {
            $this->stderr("count must be positive and batchSize must be between 1 and 5000.\n");
            return self::EXIT_CODE_ERROR;
        }

        $this->actionDemo();
        $customer = Customer::findOne(['email' => 'billing@acme.test']);
        $product = Product::findOne(['sku' => 'SKU-001']);
        if (!$customer || !$product) {
            $this->stderr("Demo customer/product could not be prepared.\n");
            return self::EXIT_CODE_ERROR;
        }

        $db = Yii::$app->db;
        $runId = strtoupper(substr(sha1(uniqid('', true)), 0, 8));
        $now = date('Y-m-d H:i:s');
        $created = 0;
        $this->stdout("Creating {$count} synthetic invoices in batches of {$batchSize}...\n");

        for ($offset = 0; $offset < $count; $offset += $batchSize) {
            $size = min($batchSize, $count - $offset);
            $invoices = [];
            $items = [];
            $numbers = [];
            for ($i = 0; $i < $size; $i++) {
                $sequence = $offset + $i + 1;
                $number = sprintf('LOAD-%s-%07d', $runId, $sequence);
                $numbers[] = $number;
                $quantity = (float) (($sequence % 5) + 1);
                $unitPrice = (float) (($sequence % 20 + 1) * 1000);
                $subtotal = round($quantity * $unitPrice, 2);
                $taxRate = 10.00;
                $tax = round($subtotal * $taxRate / 100, 2);
                $total = $subtotal + $tax;
                $status = ($sequence % 2 === 0) ? 'paid' : 'sent';
                $invoices[] = [
                    $number, $customer->id, date('Y-m-d', strtotime('-' . ($sequence % 365) . ' days')),
                    date('Y-m-d', strtotime('+' . ($sequence % 60 + 1) . ' days')),
                    $status, $subtotal, $taxRate, $tax, $total,
                    $status === 'paid' ? $total : 0, 'Synthetic load-test record',
                    $now, $now, 'Load Test Invoice ' . $sequence, 1,
                ];
            }

            $transaction = $db->beginTransaction();
            try {
                $db->createCommand()->batchInsert('{{%invoices}}', [
                    'invoice_number', 'customer_id', 'issue_date', 'due_date', 'status',
                    'subtotal', 'tax_rate', 'tax_amount', 'total', 'amount_paid', 'notes',
                    'created_at', 'updated_at', 'subject', 'item_count',
                ], $invoices)->execute();

                // Resolve IDs by unique invoice numbers rather than assuming auto-increment IDs are contiguous.
                $rows = (new \yii\db\Query())
                    ->select(['id', 'invoice_number'])
                    ->from('{{%invoices}}')
                    ->where(['invoice_number' => $numbers])
                    ->all($db);
                $idByNumber = [];
                foreach ($rows as $row) {
                    $idByNumber[$row['invoice_number']] = (int) $row['id'];
                }

                foreach ($numbers as $i => $number) {
                    if (!isset($idByNumber[$number])) {
                        throw new \RuntimeException('Could not resolve inserted invoice ID: ' . $number);
                    }
                    $sequence = $offset + $i + 1;
                    $quantity = (float) (($sequence % 5) + 1);
                    $unitPrice = (float) (($sequence % 20 + 1) * 1000);
                    $items[] = [
                        $idByNumber[$number], $product->id,
                        'Synthetic load-test service', $quantity, $unitPrice,
                        round($quantity * $unitPrice, 2), $now, $now,
                    ];
                }
                $db->createCommand()->batchInsert('{{%invoice_items}}', [
                    'invoice_id', 'product_id', 'description', 'quantity', 'unit_price',
                    'line_total', 'created_at', 'updated_at',
                ], $items)->execute();
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                $this->stderr("Batch failed after {$created} invoices: {$e->getMessage()}\n");
                return self::EXIT_CODE_ERROR;
            }

            $created += $size;
            if ($created % 10000 === 0 || $created === $count) {
                $this->stdout("Inserted {$created}/{$count} invoices and matching invoice items.\n");
            }
        }

        $this->stdout("Done. Run ID: {$runId}. Created {$created} invoices and {$created} invoice items.\n");
        $this->stdout("Use GET /v1/invoices?per-page=100&after=0 to inspect pagination and EXPLAIN on the list query to review indexes.\n");
        return self::EXIT_CODE_NORMAL;
    }

}
