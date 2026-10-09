<?php

use yii\db\Migration;

class m261009_071338_add_timestamps_to_invoice_items extends Migration
{
    public function safeUp()
    {
        $this->addColumn(
            '{{%invoice_items}}',
            'created_at',
            $this->dateTime()->null()
        );

        $this->addColumn(
            '{{%invoice_items}}',
            'updated_at',
            $this->dateTime()->null()
        );
    }

    public function safeDown()
    {
        $this->dropColumn('{{%invoice_items}}', 'updated_at');
        $this->dropColumn('{{%invoice_items}}', 'created_at');
    }
}