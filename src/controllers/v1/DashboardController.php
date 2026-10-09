<?php
namespace app\controllers\v1;

use app\models\Invoice;
use Yii;

class DashboardController extends BaseApiController
{

    public function actionSummary()
    {
        $statusRows = Invoice::find()
            ->select(['status', 'count' => 'COUNT(*)'])
            ->groupBy('status')
            ->asArray()->all();

        $totals = Invoice::find()
            ->select([
                'invoice_count' => 'COUNT(*)',
                'total_amount' => 'COALESCE(SUM(total),0)',
                'amount_paid' => 'COALESCE(SUM(amount_paid),0)',
            ])->asArray()->one();

        return [
            'invoice_count' => (int)$totals['invoice_count'],
            'total_amount' => (float)$totals['total_amount'],
            'amount_paid' => (float)$totals['amount_paid'],
            'amount_due' => (float)$totals['total_amount'] - (float)$totals['amount_paid'],
            'by_status' => array_map(function ($row) {
                return ['status' => $row['status'], 'count' => (int)$row['count']];
            }, $statusRows),
        ];
    }
}