<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Yii.php';

new \yii\console\Application(
    require dirname(__DIR__) . '/config/console.php'
);