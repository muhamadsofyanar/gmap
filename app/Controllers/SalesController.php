<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\SalesModel;

class SalesController extends Controller
{
    public const FILTERS=['all','today','overdue','upcoming','repeat'];

    /** Aktivitas: the follow-up to-do list. Sales results live on the separate Laporan page. */
    public function index(): void
    {
        $this->requireAuth();
        $model=new SalesModel();
        $filter=is_string($_GET['due']??null) && in_array($_GET['due'],self::FILTERS,true) ? $_GET['due'] : 'all';
        $page=max(1,min(100000,(int)($_GET['page']??1)));
        $tasks=$model->reminders($filter,$page);
        $this->view('sales/index',[
            'pageTitle'=>'Aktivitas - Gmap Prospek', 'activeSection'=>'sales',
            'counts'=>$model->reminderCounts(),'tasks'=>array_slice($tasks,0,50),'hasNext'=>count($tasks)>50,
            'page'=>$page,'dueFilter'=>$filter
        ]);
    }

    public function save(): void
    {
        $this->requireAuth(); $this->validateCsrf();
        $model=new SalesModel();
        $id=filter_var($_POST['lead_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $project=filter_var($_POST['project_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $action=$_POST['action']??'';
        $back='sales';
        try {
            if ($action==='pipeline' && $project) {
                $back='settings/funnel?project_id='.$project;
                $model->saveStages($project,$_POST);
            } elseif ($id) {
                $back='crm/detail?id='.$id;
                $sections=['profile'=>'salesProfile','task'=>'salesTasks','complete'=>'salesTasks','order'=>'salesOrders'];
                if (is_string($action) && isset($sections[$action])) $back.='#'.$sections[$action];
                switch ($action) {
                    case 'profile': $model->saveProfile($id,$_POST); break;
                    case 'task': $model->addTask($id,$_POST); break;
                    case 'complete':
                        $task=filter_var($_POST['task_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                        if (!$task) throw new \InvalidArgumentException('Aktivitas tidak valid.');
                        $model->completeTask($id,$task); break;
                    case 'order': $model->addOrder($id,$_POST); break;
                    default: throw new \InvalidArgumentException('Tindakan tidak valid.');
                }
            } else throw new \InvalidArgumentException('Prospek atau proyek tidak valid.');
            set_flash('success','Perubahan tersimpan.');
        } catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); }
        catch (\Throwable $e) { error_log('Sales save: '.$e->getMessage()); set_flash('error','Perubahan belum tersimpan. Coba lagi.'); }
        $this->redirect($back);
    }
}
