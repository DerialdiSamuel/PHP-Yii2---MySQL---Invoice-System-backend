<?php
namespace app\controllers\v1;

use app\models\Product;
use Yii;
use yii\web\BadRequestHttpException;

class ProductController extends BaseApiController
{
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $limit = min(100, max(1, (int)$request->get('per-page', 25)));
        $query = Product::find()->orderBy(['id' => SORT_DESC]);
        if (($q = trim((string)$request->get('q', ''))) !== '') {
            $query->andWhere(['or', ['like', 'name', $q], ['like', 'sku', $q]]);
        }
        if (($type = trim((string)$request->get('type', ''))) !== '') {
            if (!in_array($type, ['service', 'hardware'], true)) throw new BadRequestHttpException('Invalid product type.');
            $query->andWhere(['type' => $type]);
        }
        if ($request->get('active') !== null && $request->get('active') !== '') {
            $query->andWhere(['is_active' => (int)(bool)$request->get('active')]);
        }
        if (($after = $request->get('after')) !== null && $after !== '') {
            if (!ctype_digit((string)$after)) throw new BadRequestHttpException('after must be a positive integer cursor.');
            $query->andWhere(['<', 'id', (int)$after]);
        }
        $rows = $query->limit($limit + 1)->asArray()->all();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        return ['items' => $rows, 'pagination' => ['per_page' => $limit, 'has_more' => $hasMore, 'next_cursor' => $hasMore && $rows ? (int)$rows[count($rows)-1]['id'] : null]];
    }

    public function actionView($id) { return $this->find($id); }

    public function actionCreate()
    {
        $model = new Product();
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
        $model->is_active = 0;
        if (!$model->save(false)) throw new BadRequestHttpException('Unable to deactivate product.');
        return $model;
    }

    private function find($id)
    {
        $model = Product::findOne((int)$id);
        if (!$model) throw new \yii\web\NotFoundHttpException('Product not found.');
        return $model;
    }
}
