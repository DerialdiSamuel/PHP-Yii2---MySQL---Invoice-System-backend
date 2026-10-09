<?php
namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Product extends ActiveRecord
{
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
        return '{{%products}}';
    }

    public function rules()
    {
        return [
            [['name', 'unit_price'], 'required'],
            ['type', 'in', 'range' => ['service', 'hardware']],
            ['name', 'string', 'max' => 255],
            ['unit_price', 'number', 'min' => 0],
            ['is_active', 'boolean'],
        ];
    }

    public function fields()
    {
        return ['id', 'sku', 'name', 'type', 'description', 'unit_price', 'is_active', 'created_at', 'updated_at'];
    }
}