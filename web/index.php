<?php
// CORS must be applied to EVERY API response, including errors.
// The browser otherwise reports a real backend error as a misleading CORS error.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = 'http://localhost:4200';

if ($origin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
}

// Preflight must never enter Yii routing/authentication.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Yii.php';

$config = require dirname(__DIR__) . '/config/web.php';
(new yii\web\Application($config))->run();
