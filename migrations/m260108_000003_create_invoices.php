<?php
use yii\db\Migration;

class m260108_000003_create_invoices extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%invoices}}', [
            'id' => $this->bigPrimaryKey(),
            'invoice_number' => $this->string(50)->notNull()->unique(),
            'customer_id' => $this->integer()->notNull(),
            'issue_date' => $this->date()->notNull(),
            'due_date' => $this->date()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('draft'),
            'subtotal' => $this->decimal(15, 2)->notNull()->defaultValue(0),
            'tax_rate' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'tax_amount' => $this->decimal(15, 2)->notNull()->defaultValue(0),
            'total' => $this->decimal(15, 2)->notNull()->defaultValue(0),
            'amount_paid' => $this->decimal(15, 2)->notNull()->defaultValue(0),
            'notes' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
            'FOREIGN KEY ([[customer_id]]) REFERENCES {{%customers}} ([[id]]) ON UPDATE CASCADE ON DELETE RESTRICT',
        ]);
        $this->createIndex('idx_invoices_status_due', '{{%invoices}}', ['status', 'due_date', 'id']);
        $this->createIndex('idx_invoices_customer_date', '{{%invoices}}', ['customer_id', 'issue_date', 'id']);

        $this->createTable('{{%invoice_items}}', [
            'id' => $this->bigPrimaryKey(),
            'invoice_id' => $this->bigInteger()->notNull(),
            'product_id' => $this->integer()->null(),
            'description' => $this->string(500)->notNull(),
            'quantity' => $this->decimal(15, 3)->notNull(),
            'unit_price' => $this->decimal(15, 2)->notNull(),
            'line_total' => $this->decimal(15, 2)->notNull(),
        ]);
        $this->createIndex('idx_invoice_items_invoice', '{{%invoice_items}}', 'invoice_id');
        $this->addForeignKey('fk_invoice_items_invoice', '{{%invoice_items}}', 'invoice_id', '{{%invoices}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_invoice_items_product', '{{%invoice_items}}', 'product_id', '{{%products}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%invoice_items}}');
        $this->dropTable('{{%invoices}}');
    }
}