<?php
namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Customer extends ActiveRecord
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
        return '{{%customers}}';
    }

    public function rules()
    {
        return [
            [['name', 'email'], 'required'],
            ['email', 'email'],
            [['name', 'email', 'phone', 'company'], 'string', 'max' => 255],
            ['address', 'string', 'max' => 500],
            ['email', 'unique'],
        ];
    }

    public function fields()
    {
        return ['id', 'name', 'email', 'phone', 'company', 'address', 'created_at', 'updated_at'];
    }
}