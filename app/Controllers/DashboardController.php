<?php
/**
 * Dashboard Controller
 * Analytics Overview, Lead Performance Metrics & Campaign Summary
 * Gmap Prospek
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProjectModel;
use App\Models\CrmModel;

class DashboardController extends Controller
{
    protected ProjectModel $projectModel;
    protected CrmModel $crmModel;

    public function __construct()
    {
        $this->projectModel = new ProjectModel();
        $this->crmModel = new CrmModel();
    }

    /**
     * CRM prospect and follow-up overview.
     */
    public function index(): void
    {
        $this->requireAuth();

        $projects = $this->projectModel->getAllProjects();

        $this->view('dashboard/index', [
            'pageTitle' => 'Dashboard Prospek Bisnis - Gmap Prospek',
            'projects' => $projects,
            'reminders' => (new \App\Models\SalesModel())->reminderCounts(),
            'stats' => $this->crmModel->getDashboardStats(),
            'needsReply'=>(new \App\Models\ConversationRepository(\App\Core\Database::getInstance()))->needsReplyCount(\App\Services\AuthGuard::identity())
        ]);
    }
    public function followups(): void
    {
        $this->requireAuth();
        $page=max(1,min(100000,(int)($_GET['page']??1)));
        $items=$this->crmModel->unscheduledActive($page);
        $this->view('dashboard/followups',['pageTitle'=>'Prospek tanpa jadwal','activeSection'=>'dashboard','items'=>array_slice($items,0,50),'page'=>$page,'hasNext'=>count($items)>50]);
    }
}
