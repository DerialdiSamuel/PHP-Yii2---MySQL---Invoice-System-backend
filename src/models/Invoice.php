<?php
namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Invoice extends ActiveRecord
{
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => function () {
                    return date('Y-m-d H:i:s');
                },
            ],
        ];
    }

    public static function tableName()
    {
        return '{{%invoices}}';
    }

    public function rules()
    {
        return [
            [['customer_id', 'issue_date', 'due_date', 'subject'], 'required'],
            ['customer_id', 'integer'],
            [['issue_date', 'due_date'], 'date', 'format' => 'php:Y-m-d'],
            ['subject', 'string', 'max' => 255],
            ['item_count', 'integer', 'min' => 0],
            ['status', 'in', 'range' => array_keys(self::statuses())],
            [['subtotal', 'tax_amount', 'total', 'amount_paid'], 'number', 'min' => 0],
            ['tax_rate', 'number', 'min' => 0, 'max' => 100],
            ['notes', 'string', 'max' => 2000],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'invoice_number',
            'customer_id',
            'issue_date',
            'due_date',
            'subject',
            'status',
            'item_count',
            'subtotal',
            'tax_rate',
            'tax_amount',
            'total',
            'amount_paid',
            'amount_due' => 'amountDue',
            'notes',
            'created_at',
            'updated_at',
        ];
    }

    public function getCustomer()
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getItems()
    {
        return $this->hasMany(InvoiceItem::class, ['invoice_id' => 'id']);
    }

    public function getAmountDue()
    {
        return max(0, (float)$this->total - (float)$this->amount_paid);
    }

    public static function statuses()
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent',
            self::STATUS_PAID => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}