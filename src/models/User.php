<?php
namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface
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
        return '{{%users}}';
    }

    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'is_active' => 1]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::find()
            ->where(['access_token_hash' => hash('sha256', $token), 'is_active' => 1])
            ->andWhere(['>', 'token_expires_at', date('Y-m-d H:i:s')])
            ->one();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return '';
    }

    public function validateAuthKey($authKey)
    {
        return false;
    }

    public function validatePassword($password)
    {
        return password_verify($password, $this->password_hash);
    }
}