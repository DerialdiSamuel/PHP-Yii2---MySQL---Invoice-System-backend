<?php
return [
    'id' => 'invoice-api',
    'basePath' => dirname(__DIR__) . '/src',
    'controllerNamespace' => 'app\\controllers',
    'bootstrap' => ['log'],
    'components' => [
        'errorHandler' => [
            'class' => 'app\components\ApiExceptionHandler',
        ],
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'local-development-only-change-me',
            'parsers' => [
                'application/json' => 'yii\\web\\JsonParser',
            ],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
            'on beforeSend' => ['app\\components\\ApiResponse', 'beforeSend'],
        ],
        'user' => [
            'identityClass' => 'app\\models\\User',
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                // Explicit REST routes keep public plural URLs aligned with singular controller IDs.
                'GET v1/customers' => 'v1/customer/index',
                'POST v1/customers' => 'v1/customer/create',
                'GET v1/customers/<id:\\d+>' => 'v1/customer/view',
                'PUT,PATCH v1/customers/<id:\\d+>' => 'v1/customer/update',
                'DELETE v1/customers/<id:\\d+>' => 'v1/customer/delete',
                'GET v1/products' => 'v1/product/index',
                'POST v1/products' => 'v1/product/create',
                'GET v1/products/<id:\\d+>' => 'v1/product/view',
                'PUT,PATCH v1/products/<id:\\d+>' => 'v1/product/update',
                'DELETE v1/products/<id:\\d+>' => 'v1/product/delete',
                'GET v1/health' => 'v1/health/index',
                'POST v1/auth/login' => 'v1/auth/login',
                'GET v1/invoices' => 'v1/invoice/index',
                'POST v1/invoices' => 'v1/invoice/create',
                'GET v1/invoices/<id:\\d+>' => 'v1/invoice/view',
                'PUT,PATCH v1/invoices/<id:\\d+>' => 'v1/invoice/update',
                'DELETE v1/invoices/<id:\\d+>' => 'v1/invoice/delete',
                'POST v1/invoices/<id:\\d+>/send' => 'v1/invoice/send',
                'POST v1/invoices/<id:\\d+>/mark-paid' => 'v1/invoice/mark-paid',
                'GET v1/dashboard/summary' => 'v1/dashboard/summary',
            ],
        ],
        'db' => require __DIR__ . '/db.php',
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\\log\\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
    'params' => [
        'adminEmail' => 'admin@example.com',
    ],
];