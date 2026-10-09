<?php
namespace app\components;

use Yii;
use yii\web\ErrorHandler;
use yii\web\HttpException;

class ApiExceptionHandler extends ErrorHandler
{
    public function renderException($exception)
    {
        $response = Yii::$app->response;

        // Keep CORS headers on error responses so frontend clients can see the
        // actual API error instead of receiving a browser-level CORS error.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === 'http://localhost:4200') {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
        $status = $exception instanceof HttpException ? $exception->statusCode : 500;
        $response->statusCode = $status;
        $message = ($exception instanceof HttpException || YII_DEBUG)
            ? $exception->getMessage()
            : 'An unexpected error occurred.';

        $response->data = [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $this->errorCode($exception, $status),
                'message' => $message,
            ],
        ];
        $response->format = \yii\web\Response::FORMAT_JSON;
    }

    private function errorCode($exception, $status)
    {
        if ($exception instanceof HttpException) {
            return strtoupper(str_replace(' ', '_', $exception->getName()));
        }
        return $status === 500 ? 'INTERNAL_SERVER_ERROR' : 'API_ERROR';
    }
}
