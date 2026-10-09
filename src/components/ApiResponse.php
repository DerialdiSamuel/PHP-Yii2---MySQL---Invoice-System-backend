<?php
namespace app\components;

use yii\web\Response;

class ApiResponse
{
    public static function beforeSend($event)
    {
        /** @var Response $response */
        $response = $event->sender;

        if ($response->data === null) {
            return;
        }

        if ($response->isSuccessful) {
            $response->data = [
                'success' => true,
                'data' => $response->data,
                'error' => null,
            ];
        }
    }
}