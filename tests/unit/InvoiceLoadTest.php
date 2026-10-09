<?php

namespace tests\unit;

use app\models\Invoice;
use Codeception\Test\Unit;
use Yii;

/**
 * Integration/performance test for invoice listing against a large MySQL dataset.
 *
 * Prerequisite: run `php yii seed/bigdata 1000000 1000` against the test database.
 * This test deliberately does not create or delete the large dataset.
 */
class InvoiceLoadTest extends Unit
{
    /** Minimum rows required before the performance assertion is meaningful. */
    private const MIN_INVOICES = 1000000;

    public function testInvoiceListQueryHandlesMillionRowsWithinBudget()
    {
        $count = (int) Invoice::find()->count();
        if ($count < self::MIN_INVOICES) {
            $this->markTestSkipped(sprintf(
                'Requires at least %d invoices; found %d. Run `php yii seed/bigdata 1000000 1000` first.',
                self::MIN_INVOICES,
                $count
            ));
        }

        // Mirrors the main invoice-list query: customer join, descending ID order,
        // bounded page size, and limit+1 to determine whether another page exists.
        $limit = 25;
        $startedAt = microtime(true);
        $rows = Invoice::find()
            ->alias('i')
            ->select([
                'i.id',
                'i.invoice_number',
                'i.customer_id',
                'i.issue_date',
                'i.due_date',
                'i.subject',
                'i.item_count',
                'i.status',
                'i.subtotal',
                'i.tax_rate',
                'i.tax_amount',
                'i.total',
                'i.amount_paid',
                'i.created_at',
                'i.updated_at',
                'customer_name' => 'c.name',
            ])
            ->leftJoin(['c' => '{{%customers}}'], 'c.id = i.customer_id')
            ->orderBy(['i.id' => SORT_DESC])
            ->limit($limit + 1)
            ->asArray()
            ->all();
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        $this->assertLessThanOrEqual(
            $limit + 1,
            count($rows),
            'The invoice list query must return only a bounded page.'
        );
        $this->assertNotEmpty($rows, 'The invoice list query should return rows.');
        $this->assertLessThanOrEqual(
            $this->getMaxQueryTimeMs(),
            $elapsedMs,
            sprintf(
                'Invoice list query took %.2f ms; configured budget is %d ms. Check indexes and EXPLAIN output.',
                $elapsedMs,
                $this->getMaxQueryTimeMs()
            )
        );

        // The descending, unique primary-key order is required for cursor pagination.
        $ids = array_map('intval', array_column($rows, 'id'));
        $sortedIds = $ids;
        rsort($sortedIds, SORT_NUMERIC);
        $this->assertSame($sortedIds, $ids, 'Invoice rows must be ordered by descending ID.');
        $this->assertSame(count($ids), count(array_unique($ids)), 'A page must not contain duplicate invoices.');

        codecept_debug(sprintf(
            'Invoice load check: total=%d, returned=%d, query_time=%.2fms, budget=%dms',
            $count,
            count($rows),
            $elapsedMs,
            $this->getMaxQueryTimeMs()
        ));
    }

    public function testCursorReturnsNextNonOverlappingPage()
    {
        $count = (int) Invoice::find()->count();
        if ($count < self::MIN_INVOICES) {
            $this->markTestSkipped(sprintf(
                'Requires at least %d invoices; found %d. Run `php yii seed/bigdata 1000000 1000` first.',
                self::MIN_INVOICES,
                $count
            ));
        }

        $pageSize = 25;
        $firstPage = Invoice::find()
            ->select(['id'])
            ->orderBy(['id' => SORT_DESC])
            ->limit($pageSize)
            ->column();
        $this->assertCount($pageSize, $firstPage, 'First page should contain the requested number of invoices.');

        $cursor = (int) end($firstPage);
        $secondPage = Invoice::find()
            ->select(['id'])
            ->where(['<', 'id', $cursor])
            ->orderBy(['id' => SORT_DESC])
            ->limit($pageSize)
            ->column();

        $this->assertCount($pageSize, $secondPage, 'Second page should contain the requested number of invoices.');
        $this->assertEmpty(
            array_intersect(array_map('intval', $firstPage), array_map('intval', $secondPage)),
            'Cursor pagination must not repeat invoices from the previous page.'
        );
        $this->assertLessThan(
            (int) end($firstPage),
            (int) $secondPage[0],
            'The second page must continue with older invoice IDs.'
        );
    }

    private function getMaxQueryTimeMs()
    {
        $configured = getenv('INVOICE_LOAD_MAX_QUERY_MS');
        return $configured !== false && ctype_digit((string) $configured)
            ? max(1, (int) $configured)
            : 1000;
    }
}
