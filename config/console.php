<?php

return [
    'id' => 'invoice-console',
    'basePath' => dirname(__DIR__) . '/src',
    'controllerNamespace' => 'app\\commands',
    'controllerPath' => dirname(__DIR__) . '/src/commands',

    'components' => [
        'db' => require dirname(__DIR__) . '/config/db.php',
    ],

    'controllerMap' => [
        'migrate' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationPath' => dirname(__DIR__) . '/migrations',
        ],
    ],
];