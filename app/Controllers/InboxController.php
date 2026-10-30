<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Database;
use App\Models\InboxRepository;
use App\Models\ConversationRepository;
use App\Services\AuthGuard;

final class InboxController extends Controller
{
    private InboxRepository $repo;
    private ConversationRepository $conversations;
    private array $actor;
    public function __construct() {
        $this->actor=AuthGuard::identity() ?? throw new \DomainException('Akses ditolak.');
        $db=Database::getInstance();$this->repo=new InboxRepository($db);$this->conversations=new ConversationRepository($db);
    }
    private function id(array $values,string $key,bool $required=false): ?int {
        $value=$values[$key] ?? null;
        if(($value===null || $value==='') && !$required)return null;
        if((!is_int($value) && !is_string($value)) || !filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]))throw new \InvalidArgumentException('Pilihan tidak valid.');
        return (int)$value;
    }
    public function index(): void {
        try {
            $filter=is_string($_GET['filter'] ?? null)?$_GET['filter']:'all';
            if(!in_array($filter,['all','unread','unmatched','unknown'],true))$filter='all';
            $page=min(100000,$this->id($_GET,'page') ?? 1);$historyPage=min(100000,$this->id($_GET,'history_page') ?? 1);
            $lead=$this->id($_GET,'lead');$anchor=$this->id($_GET,'conversation');
            $search=$_GET['q'] ?? '';if(!is_string($search) || mb_strlen($search)>100)throw new \InvalidArgumentException('Pencarian maksimal 100 karakter.');
            $search=trim($search);$groups=$this->conversations->groups($this->actor,$filter,$page,$search);
            if($lead===null && $anchor===null && $groups){$first=$groups[0];if($first['lead_id']!==null)$lead=(int)$first['lead_id'];else $anchor=(int)$first['id'];}
            $thread=$this->conversations->thread($this->actor,$anchor,$lead,$historyPage);
            if(($lead!==null || $anchor!==null) && !$thread){http_response_code(404);echo 'Percakapan tidak ditemukan atau akses ditolak.';return;}
            $context=$thread['context'] ?? null;
            $options=$this->conversations->options($this->actor);
            $candidates=$context && !$context['lead_id'] && $context['phone'] && $this->actor['role']==='admin' ? $this->repo->linkCandidates($this->actor,$context['anchor']):[];
            $this->view('inbox/index',[
                'pageTitle'=>'Kotak masuk · '.APP_NAME,'activeSection'=>'inbox','isAdmin'=>$this->actor['role']==='admin',
                'filter'=>$filter,'page'=>$page,'historyPage'=>$historyPage,'search'=>$search,'groups'=>$groups,'thread'=>$thread,'context'=>$context,
                'options'=>$options,'candidates'=>$candidates,'reminderTotal'=>$this->conversations->reminderCount($this->actor),
            ]);
        } catch(\InvalidArgumentException $e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}
    }
    public function review(): void {
        $this->validateCsrf();
        try{$id=$this->id($_POST,'message_id',true);$this->repo->review($this->actor,$id);set_flash('success','Pesan ditandai sudah ditinjau.');}
        catch(\DomainException $e){$this->json(['success'=>false,'message'=>'Akses ditolak.'],403);}
        catch(\InvalidArgumentException $e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}
        $this->redirect('inbox?conversation='.$id);
    }
    public function link(): void {
        $this->validateCsrf();$id=null;
        try{$id=$this->id($_POST,'message_id',true);$lead=$this->id($_POST,'lead_id',true);$this->repo->link($this->actor,$id,$lead);set_flash('success','Percakapan dihubungkan ke CRM.');$this->redirect('inbox?lead='.$lead);}
        catch(\DomainException $e){$this->json(['success'=>false,'message'=>'Akses ditolak.'],403);}
        catch(\InvalidArgumentException $e){set_flash('error',$e->getMessage());$this->redirect('inbox'.($id?'?conversation='.$id:''));}
    }
    public function createProspect(): void {
        $this->validateCsrf();$id=null;
        if($this->actor['role']!=='admin')$this->json(['success'=>false,'message'=>'Akses admin diperlukan.'],403);
        try{
            $id=$this->id($_POST,'message_id',true);$project=$this->id($_POST,'project_id',true);$member=$this->id($_POST,'member_id');
            $name=$_POST['name'] ?? '';if(!is_string($name))throw new \InvalidArgumentException('Nama tidak valid.');
            $lead=$this->repo->createProspect($this->actor,$id,$project,$name,$member);
            set_flash('success','Prospek tersimpan di CRM dan percakapan sudah terhubung.');$this->redirect('inbox?lead='.$lead);
        }catch(\DomainException $e){$this->json(['success'=>false,'message'=>'Akses ditolak.'],403);}
        catch(\InvalidArgumentException $e){set_flash('error',$e->getMessage());$this->redirect('inbox'.($id?'?conversation='.$id:''));}
    }
}
