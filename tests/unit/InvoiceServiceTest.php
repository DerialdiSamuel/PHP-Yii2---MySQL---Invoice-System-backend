<?php

namespace tests\unit;

use app\models\Customer;
use app\models\Invoice;
use app\models\InvoiceItem;
use app\models\Product;
use app\services\InvoiceCalculator;
use app\services\InvoiceService;
use Codeception\Test\Unit;
use yii\web\BadRequestHttpException;

class InvoiceServiceTest extends Unit
{
    private $service;
    private $customer;
    private $product;

    protected function _before()
    {
        $this->service = new InvoiceService(
            new InvoiceCalculator()
        );

        $this->customer = new Customer([
            'name' => 'Unit Test Customer',
            'email' => 'test-' . uniqid() . '@example.com',
            'company' => 'Unit Test Company',
            'address' => 'Test Address',
        ]);

        $this->assertTrue(
            $this->customer->save(),
            implode(' ', $this->customer->getFirstErrors())
        );

        $this->product = new Product([
            'sku' => 'TEST-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'name' => 'Unit Test Product',
            'description' => 'Product created by unit test',
            'unit_price' => 100.00,
            'type' => 'service',
            'is_active' => 1,
        ]);

        $this->assertTrue(
            $this->product->save(),
            implode(' ', $this->product->getFirstErrors())
        );
    }

    protected function _after()
    {
        if ($this->customer && $this->customer->id) {
            $invoiceIds = Invoice::find()
                ->select('id')
                ->where(['customer_id' => $this->customer->id])
                ->column();

            if ($invoiceIds) {
                InvoiceItem::deleteAll([
                    'invoice_id' => $invoiceIds,
                ]);

                Invoice::deleteAll([
                    'id' => $invoiceIds,
                ]);
            }

            Customer::deleteAll([
                'id' => $this->customer->id,
            ]);
        }

        if ($this->product && $this->product->id) {
            Product::deleteAll([
                'id' => $this->product->id,
            ]);
        }
    }

    public function testCreateInvoiceSuccessfully()
    {
        $invoice = $this->service->create([
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-10-09',
            'due_date' => '2026-10-16',
            'subject' => 'Unit Test Invoice',
            'tax_rate' => 10,
            'notes' => 'Created by unit test',

            'items' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Development Service',
                    'quantity' => 2,
                    'unit_price' => 100,
                ],
                [
                    'description' => 'Design Service',
                    'quantity' => 3,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $this->assertInstanceOf(
            Invoice::class,
            $invoice
        );

        $this->assertNotNull($invoice->id);

        $this->assertMatchesRegularExpression(
            '/^INV-\d{6}-[A-F0-9]{6}$/',
            $invoice->invoice_number
        );

        $this->assertSame(
            $this->customer->id,
            (int) $invoice->customer_id
        );

        $this->assertSame(
            'Unit Test Invoice',
            $invoice->subject
        );

        $this->assertSame(
            Invoice::STATUS_DRAFT,
            $invoice->status
        );

        /*
         * 2 × 100 = 200
         * 3 × 50  = 150
         *
         * Subtotal = 350
         * Tax 10%  = 35
         * Total     = 385
         */
        $this->assertSame(
            2,
            (int) $invoice->item_count
        );

        $this->assertSame(
            350.00,
            (float) $invoice->subtotal
        );

        $this->assertSame(
            10.00,
            (float) $invoice->tax_rate
        );

        $this->assertSame(
            35.00,
            (float) $invoice->tax_amount
        );

        $this->assertSame(
            385.00,
            (float) $invoice->total
        );

        $this->assertSame(
            0.00,
            (float) $invoice->amount_paid
        );

        $this->assertSame(
            385.00,
            $invoice->getAmountDue()
        );

        // Verify the invoice items were actually persisted.
        $items = InvoiceItem::find()
            ->where([
                'invoice_id' => $invoice->id,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $this->assertCount(2, $items);

        $this->assertSame(
            'Development Service',
            $items[0]->description
        );

        $this->assertSame(
            200.00,
            (float) $items[0]->line_total
        );

        $this->assertSame(
            'Design Service',
            $items[1]->description
        );

        $this->assertSame(
            150.00,
            (float) $items[1]->line_total
        );
    }

    public function testCreateInvoiceUsesProductPrice()
    {
        $invoice = $this->service->create([
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-10-09',
            'due_date' => '2026-10-16',
            'subject' => 'Product Price Test',

            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                ],
            ],
        ]);

        /*
         * Product price = 100
         * Quantity = 3
         * Subtotal = 300
         */
        $this->assertSame(
            300.00,
            (float) $invoice->subtotal
        );

        $this->assertSame(
            300.00,
            (float) $invoice->total
        );

        $item = InvoiceItem::findOne([
            'invoice_id' => $invoice->id,
        ]);

        $this->assertNotNull($item);

        $this->assertSame(
            100.00,
            (float) $item->unit_price
        );

        $this->assertSame(
            300.00,
            (float) $item->line_total
        );
    }

    public function testCreateInvoiceRequiresItems()
    {
        $this->expectException(
            BadRequestHttpException::class
        );

        $this->expectExceptionMessage(
            'Invoice must contain at least one item.'
        );

        $this->service->create([
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-10-09',
            'due_date' => '2026-10-16',
            'subject' => 'Invalid Invoice',
            'items' => [],
        ]);
    }

    public function testCreateInvoiceRequiresExistingCustomer()
    {
        $this->expectException(
            BadRequestHttpException::class
        );

        $this->expectExceptionMessage(
            'Customer not found.'
        );

        $this->service->create([
            'customer_id' => 999999999,
            'issue_date' => '2026-10-09',
            'due_date' => '2026-10-16',
            'subject' => 'Invalid Customer',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);
    }

    public function testCreateInvoiceRejectsInvalidDateRange()
    {
        $this->expectException(
            BadRequestHttpException::class
        );

        $this->expectExceptionMessage(
            'due_date cannot be earlier than issue_date.'
        );

        $this->service->create([
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-10-16',
            'due_date' => '2026-10-09',
            'subject' => 'Invalid Date',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);
    }

    public function testCreateInvoiceRejectsInvalidTaxRate()
    {
        $this->expectException(
            BadRequestHttpException::class
        );

        $this->expectExceptionMessage(
            'tax_rate must be between 0 and 100.'
        );

        $this->service->create([
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-10-09',
            'due_date' => '2026-10-16',
            'subject' => 'Invalid Tax',
            'tax_rate' => 101,
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);
    }

    public function testCreateInvoiceRequiresSubject()
    {
        $this->expectException(
            BadRequestHttpException::class
        );

        $this->expectExceptionMessage(
            'subject cannot be empty.'
        );

        $this->service->create([
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-10-09',
            'due_date' => '2026-10-16',
            'subject' => '   ',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);
    }

    public function testCreateInvoiceRejectsUnknownProduct()
    {
        $this->expectException(
            BadRequestHttpException::class
        );

        $this->expectExceptionMessage(
            'Invoice item #1 references an inactive or unknown product.'
        );

        $this->service->create([
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-10-09',
            'due_date' => '2026-10-16',
            'subject' => 'Unknown Product',
            'items' => [
                [
                    'product_id' => 999999999,
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);
    }
}