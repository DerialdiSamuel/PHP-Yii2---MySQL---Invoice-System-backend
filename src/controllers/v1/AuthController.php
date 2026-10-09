<?php
namespace app\controllers\v1;

use app\models\User;
use Yii;
use yii\web\UnauthorizedHttpException;
use yii\web\BadRequestHttpException;

class AuthController extends BaseApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['login'];
        return $behaviors;
    }

    public function actionLogin()
    {
        $body = Yii::$app->request->bodyParams;
        $username = isset($body['username']) ? trim($body['username']) : '';
        $password = isset($body['password']) ? $body['password'] : '';

        if ($username === '' || $password === '') {
            throw new BadRequestHttpException('username and password are required.');
        }

        $user = User::findOne(['username' => $username, 'is_active' => 1]);
        if (!$user || !$user->validatePassword($password)) {
            throw new UnauthorizedHttpException('Invalid username or password.');
        }

        $plainToken = bin2hex(random_bytes(32));
        $user->access_token_hash = hash('sha256', $plainToken);
        $user->token_expires_at = date('Y-m-d H:i:s', time() + 86400);
        $user->save(false);

        return [
            'token' => $plainToken,
            'expires_at' => $user->token_expires_at,
            'user' => ['id' => $user->id, 'username' => $user->username],
        ];
    }
}