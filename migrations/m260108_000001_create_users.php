<?php
use yii\db\Migration;

class m260108_000001_create_users extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%users}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(100)->notNull()->unique(),
            'password_hash' => $this->string(255)->notNull(),
            'access_token_hash' => $this->string(64)->null()->unique(),
            'token_expires_at' => $this->dateTime()->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_users_token', '{{%users}}', ['access_token_hash', 'token_expires_at', 'is_active']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%users}}');
    }
}