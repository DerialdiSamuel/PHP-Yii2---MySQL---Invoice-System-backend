<?php

return [
    'class' => 'yii\\db\\Connection',
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        getenv('DB_HOST') ?: '127.0.0.1',
        getenv('DB_PORT') ?: '3307',
        getenv('DB_NAME') ?: 'invoice_system'
    ),
    'username' => getenv('DB_USER') ?: 'invoice',
    'password' => getenv('DB_PASSWORD') ?: 'invoice',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
];