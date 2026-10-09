<?php
use yii\db\Migration;

class m260108_000004_seed_admin extends Migration
{
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');
        $this->insert('{{%users}}', [
            'username' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function safeDown()
    {
        $this->delete('{{%users}}', ['username' => 'admin']);
    }
}