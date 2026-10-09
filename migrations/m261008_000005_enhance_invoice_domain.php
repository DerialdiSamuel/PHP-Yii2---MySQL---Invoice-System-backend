<?php
use yii\db\Migration;

class m261008_000005_enhance_invoice_domain extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%invoices}}', 'subject', $this->string(255)->notNull()->defaultValue('Invoice'));
        $this->addColumn('{{%invoices}}', 'item_count', $this->integer()->notNull()->defaultValue(0));
        $this->createIndex('idx_invoices_subject', '{{%invoices}}', 'subject');
        $this->createIndex('idx_invoices_status_id', '{{%invoices}}', ['status', 'id']);

        $this->execute('UPDATE {{%invoices}} i LEFT JOIN (SELECT invoice_id, COUNT(*) item_count FROM {{%invoice_items}} GROUP BY invoice_id) x ON x.invoice_id = i.id SET i.item_count = COALESCE(x.item_count, 0)');

        $this->addColumn('{{%products}}', 'type', $this->string(20)->notNull()->defaultValue('service'));
        $this->createIndex('idx_products_type_active_name', '{{%products}}', ['type', 'is_active', 'name']);
    }

    public function safeDown()
    {
        $this->dropIndex('idx_products_type_active_name', '{{%products}}');
        $this->dropColumn('{{%products}}', 'type');
        $this->dropIndex('idx_invoices_status_id', '{{%invoices}}');
        $this->dropIndex('idx_invoices_subject', '{{%invoices}}');
        $this->dropColumn('{{%invoices}}', 'item_count');
        $this->dropColumn('{{%invoices}}', 'subject');
    }
}
