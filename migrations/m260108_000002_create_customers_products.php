<?php
use yii\db\Migration;

class m260108_000002_create_customers_products extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%customers}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'email' => $this->string(255)->notNull()->unique(),
            'phone' => $this->string(50)->null(),
            'company' => $this->string(255)->null(),
            'address' => $this->string(500)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_customers_name', '{{%customers}}', 'name');

        $this->createTable('{{%products}}', [
            'id' => $this->primaryKey(),
            'sku' => $this->string(100)->null()->unique(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->string(500)->null(),
            'unit_price' => $this->decimal(15, 2)->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_products_active_name', '{{%products}}', ['is_active', 'name']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%products}}');
        $this->dropTable('{{%customers}}');
    }
}