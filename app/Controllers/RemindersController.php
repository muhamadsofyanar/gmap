<?php
namespace App\Controllers;

use App\Models\SalesModel;

class RemindersController extends \App\Core\Controller
{
    private function filter(string $value): string { return in_array($value,SalesController::FILTERS,true)?$value:'all'; }
    // The reminder centre is now the Aktivitas page; keep old links and bookmarks working.
    public function index(): void
    {
        $this->requireAuth();
        $this->redirect('sales?due='.$this->filter(is_string($_GET['due']??null)?$_GET['due']:'all'));
    }
    public function counts(): void { $this->requireAuth(); $this->json(['success'=>true,'counts'=>(new SalesModel())->reminderCounts()]); }
    public function complete(): void
    {
        $this->requireAuth(); $this->validateCsrf();
        try { (new SalesModel())->completeTask(max(0,(int)($_POST['lead_id']??0)),max(0,(int)($_POST['task_id']??0))); set_flash('success','Aktivitas ditandai selesai.'); }
        catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); }
        $this->redirect('sales?due='.$this->filter(is_string($_POST['due']??null)?$_POST['due']:'all'));
    }
}
