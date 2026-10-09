<?php
namespace app\controllers\v1;

use app\models\Customer;
use Yii;
use yii\web\BadRequestHttpException;

class CustomerController extends BaseApiController
{
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $limit = min(100, max(1, (int)$request->get('per-page', 25)));
        $query = Customer::find()->orderBy(['id' => SORT_DESC]);

        if (($q = trim((string)$request->get('q', ''))) !== '') {
            $query->andWhere(['or', ['like', 'name', $q], ['like', 'email', $q], ['like', 'company', $q]]);
        }
        if (($after = $request->get('after')) !== null && $after !== '') {
            if (!ctype_digit((string)$after)) throw new BadRequestHttpException('after must be a positive integer cursor.');
            $query->andWhere(['<', 'id', (int)$after]);
        }
        $rows = $query->limit($limit + 1)->asArray()->all();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        return [
            'items' => $rows,
            'pagination' => ['per_page' => $limit, 'has_more' => $hasMore, 'next_cursor' => $hasMore && $rows ? (int)$rows[count($rows)-1]['id'] : null],
        ];
    }

    public function actionView($id) { return $this->find($id); }

    public function actionCreate()
    {
        $model = new Customer();
        $model->load(Yii::$app->request->bodyParams, '');
        if (!$model->save()) throw new BadRequestHttpException(implode(' ', $model->getFirstErrors()));
        Yii::$app->response->statusCode = 201;
        return $model;
    }

    public function actionUpdate($id)
    {
        $model = $this->find($id);
        $model->load(Yii::$app->request->bodyParams, '');
        if (!$model->save()) throw new BadRequestHttpException(implode(' ', $model->getFirstErrors()));
        return $model;
    }

    public function actionDelete($id)
    {
        $model = $this->find($id);
        if (!$model->delete()) throw new BadRequestHttpException('Unable to delete customer.');
        Yii::$app->response->statusCode = 204;
        return null;
    }

    private function find($id)
    {
        $model = Customer::findOne((int)$id);
        if (!$model) throw new \yii\web\NotFoundHttpException('Customer not found.');
        return $model;
    }
}
