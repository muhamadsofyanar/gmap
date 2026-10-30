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
            'stats' => $this->crmModel->getDashboardStats()
        ]);
    }
}
