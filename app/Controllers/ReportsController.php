<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\SalesModel;

/** Laporan: sales results per project, separate from the Aktivitas to-do list. */
class ReportsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $reports=(new SalesModel())->reports();
        $totals=['prospects'=>0,'crm_total'=>0,'contacted'=>0,'interested'=>0,'closing'=>0,'deal_value'=>0.0,'orders'=>0,'revenue'=>0.0];
        foreach ($reports as $report) foreach ($totals as $key=>$value) $totals[$key]+=is_float($value)?(float)$report[$key]:(int)$report[$key];
        $this->view('reports/index',['pageTitle'=>'Laporan - Gmap Prospek','activeSection'=>'reports','reports'=>$reports,'totals'=>$totals]);
    }
}
