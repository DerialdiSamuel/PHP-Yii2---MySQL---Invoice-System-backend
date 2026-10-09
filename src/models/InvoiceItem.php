<?php
namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class InvoiceItem extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => function () {
                    return date('Y-m-d H:i:s');
                },
            ],
        ];
    }

    public static function tableName()
    {
        return '{{%invoice_items}}';
    }

    public function rules()
    {
        return [
            [['invoice_id', 'description', 'quantity', 'unit_price'], 'required'],
            [['invoice_id', 'product_id'], 'integer'],
            ['quantity', 'number', 'min' => 0.01],
            ['unit_price', 'number', 'min' => 0],
            ['description', 'string', 'max' => 500],
        ];
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function fields()
    {
        return ['id', 'product_id', 'description', 'quantity', 'unit_price', 'line_total'];
    }
}