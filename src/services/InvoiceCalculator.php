<?php
namespace app\services;

class InvoiceCalculator
{
    public function lineTotal($quantity, $unitPrice)
    {
        return round((float)$quantity * (float)$unitPrice, 2);
    }

    public function tax($subtotal, $taxRate)
    {
        return round((float)$subtotal * ((float)$taxRate / 100), 2);
    }

    public function total($subtotal, $tax)
    {
        return round((float)$subtotal + (float)$tax, 2);
    }
}
