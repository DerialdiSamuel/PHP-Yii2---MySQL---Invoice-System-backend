<?php
namespace app\controllers\v1;

use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\Response;

abstract class BaseApiController extends Controller
{
    public function behaviors()
    {
        return [
            'cors' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => ['http://localhost:4200'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
                    'Access-Control-Allow-Credentials' => true,
                ],
            ],
            'authenticator' => ['class' => HttpBearerAuth::class],
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
        ];
    }
}
