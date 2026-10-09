<?php
namespace app\controllers\v1;

class HealthController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['index'];
        return $behaviors;
    }

    public function actionIndex()
    {
        return ['status' => 'ok', 'service' => 'invoice-api'];
    }
}
