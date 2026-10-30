<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Database;
use App\Models\InboxRepository;
use App\Services\AuthGuard;

final class InboxController extends Controller
{
    private InboxRepository $repo;
    private array $actor;
    public function __construct() {
        $this->actor=AuthGuard::identity() ?? throw new \DomainException('Akses ditolak.');
        $this->repo=new InboxRepository(Database::getInstance());
    }
    public function index(): void {
        $filter=is_string($_GET['filter'] ?? null) ? $_GET['filter'] : 'all';
        if (!in_array($filter,['all','unread','unmatched'],true)) $filter='all';
        $page=filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]) ?: 1;
        $lead=filter_var($_GET['lead'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
        $messages=$this->repo->listing($this->actor,$filter,$page,$lead);
        if ($this->actor['role']==='admin') foreach ($messages as &$message) {
            $message['candidates']=$message['lead_id']===null && $message['contact_phone']!==null ? $this->repo->linkCandidates($this->actor,(int)$message['id']) : [];
        }
        unset($message);
        $this->view('inbox/index',['isAdmin'=>$this->actor['role']==='admin','filter'=>$filter,'page'=>$page,'leadFilter'=>$lead,
            'messages'=>$messages],false);
    }
    public function review(): void {
        $this->validateCsrf();
        $id=filter_var($_POST['message_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) $this->json(['success'=>false,'message'=>'ID tidak valid.'],422);
        try { $this->repo->review($this->actor,$id);set_flash('success','Pesan ditandai sudah ditinjau.'); }
        catch (\DomainException $e) { $this->json(['success'=>false,'message'=>'Akses ditolak.'],403); }
        $this->redirect('inbox');
    }
    public function link(): void {
        $this->validateCsrf();
        $id=filter_var($_POST['message_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $lead=filter_var($_POST['lead_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id || !$lead) $this->json(['success'=>false,'message'=>'ID tidak valid.'],422);
        try { $this->repo->link($this->actor,$id,$lead);set_flash('success','Percakapan dihubungkan ke prospek.'); }
        catch (\DomainException $e) { $this->json(['success'=>false,'message'=>'Akses ditolak.'],403); }
        catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); }
        $this->redirect('inbox');
    }
}
