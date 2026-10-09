<?php
namespace tests\unit;

use app\services\InvoiceCalculator;
use Codeception\Test\Unit;

class CalculationTest extends Unit
{
    private $calculator;

    protected function _before()
    {
        $this->calculator = new InvoiceCalculator();
    }

    public function testLineTotal()
    {
        $this->assertSame(375001.50, $this->calculator->lineTotal(3, 125000.50));
    }

    public function testTaxAndGrandTotal()
    {
        $tax = $this->calculator->tax(1000000, 11);
        $this->assertSame(110000.00, $tax);
        $this->assertSame(1110000.00, $this->calculator->total(1000000, $tax));
    }

    public function testRoundingIsAppliedToLineAndTaxTotals()
    {
        $line = $this->calculator->lineTotal(4.5, 60);
        $tax = $this->calculator->tax($line, 10);
        $this->assertSame(270.00, $line);
        $this->assertSame(27.00, $tax);
    }

    public function testZeroQuantityProducesZeroLineTotal()
    {
        $this->assertSame(0.00, $this->calculator->lineTotal(0, 125.50));
    }

    public function testLineTotalRoundsToTwoDecimalPlaces()
    {
        $this->assertSame(10.01, $this->calculator->lineTotal(3, 3.335));
    }

    public function testZeroTaxLeavesGrandTotalUnchanged()
    {
        $tax = $this->calculator->tax(1234.56, 0);
        $this->assertSame(0.00, $tax);
        $this->assertSame(1234.56, $this->calculator->total(1234.56, $tax));
    }

    public function testTaxRoundsToTwoDecimalPlaces()
    {
        $this->assertSame(1.01, $this->calculator->tax(10.05, 10));
    }

}
