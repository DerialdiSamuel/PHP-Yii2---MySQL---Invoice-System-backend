<?php
defined('YII_DEBUG') or define('YII_DEBUG', filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN));
defined('YII_ENV') or define('YII_ENV', getenv('APP_ENV') ?: 'dev');

require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';