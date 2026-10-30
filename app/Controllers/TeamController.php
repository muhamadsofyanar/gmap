<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\TeamRepository;
use App\Services\AuthGuard;

final class TeamController extends Controller
{
    private TeamRepository $repo;
    public function __construct()
    {
        $actor=AuthGuard::identity();
        if (!$actor) {header('Location: '.base_url('login'));exit;}
        $this->repo=new TeamRepository(Database::getInstance(),$actor);
    }

    private function text(string $key): string
    {
        $value=$_POST[$key] ?? '';
        if (!is_string($value)) throw new \InvalidArgumentException('Input tidak valid.');
        return $value;
    }

    private function id(string $key): int
    {
        $value=$this->text($key);
        if (!preg_match('/^[1-9][0-9]{0,9}$/D',$value)) throw new \InvalidArgumentException('ID tidak valid.');
        return (int)$value;
    }

    private function mutation(callable $action,string $target='team'): void
    {
        $this->validateCsrf();
        try {
            $action(); set_flash('success','Perubahan berhasil disimpan.');
        } catch (\DomainException $e) {
            http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo $e->getMessage();exit;
        } catch (\InvalidArgumentException $e) {
            set_flash('error',$e->getMessage());
        } catch (\PDOException $e) {
            error_log('Team write: '.$e->getMessage());
            set_flash('error','Perubahan gagal. Periksa input atau coba kembali.');
        }
        $this->redirect($target);
    }

    public function index(): void
    {
        $search=is_string($_GET['q'] ?? null)?trim($_GET['q']):'';
        if (strlen($search)>400) {http_response_code(400);echo 'Pencarian terlalu panjang.';return;}
        $page=max(1,min(100000,(int)($_GET['page'] ?? 1)));
        $leads=$this->repo->listing($search,$page);
        $this->view('team/index',[
            'isAdmin'=>$this->repo->isAdmin(),'leads'=>$leads,'search'=>$search,'page'=>$page,
            'summary'=>$this->repo->summary(),'members'=>$this->repo->isAdmin()?$this->repo->members():[],
        ],false);
    }

    public function detail(int $id): void
    {
        $lead=$this->repo->lead($id);
        if (!$lead) {http_response_code(404);echo 'Prospek tidak ditemukan.';return;}
        $this->view('team/detail',[
            'isAdmin'=>$this->repo->isAdmin(),'lead'=>$lead,'tasks'=>$this->repo->tasks($id),
            'attachments'=>$this->repo->attachments($id),'history'=>$this->repo->history($id),
        ],false);
    }

    public function save(): void
    {
        $this->mutation(function():void {
            $this->repo->updateLead($this->id('lead_id'),['status'=>$this->text('status'),'notes'=>$this->text('notes'),'deal_value'=>$this->text('deal_value')]);
        });
    }

    public function assign(): void
    {
        $this->mutation(function():void {
            $ids=$_POST['lead_ids'] ?? [];
            if (!is_array($ids)) throw new \InvalidArgumentException('Pilihan prospek tidak valid.');
            foreach($ids as $id) if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D',$id)) throw new \InvalidArgumentException('ID tidak valid.');
            $member=$this->text('member_id');
            if ($member!=='' && !preg_match('/^[1-9][0-9]{0,9}$/D',$member)) throw new \InvalidArgumentException('Anggota tidak valid.');
            $this->repo->assign($ids,$member===''?null:(int)$member);
        });
    }

    public function createMember(): void
    {
        $this->mutation(fn()=> $this->repo->createMember($this->text('username'),$this->text('email'),$this->text('name'),$this->text('password')));
    }

    public function disableMember(): void
    {
        $this->mutation(fn()=> $this->repo->disableMember($this->id('member_id')));
    }

    public function resetPassword(): void
    {
        $this->mutation(fn()=> $this->repo->resetMemberPassword($this->id('member_id'),$this->text('password')));
    }

    public function addTask(): void
    {
        $this->mutation(fn()=> $this->repo->addTask($this->id('lead_id'),$this->text('title'),$this->text('due_at')));
    }

    public function completeTask(): void
    {
        $this->mutation(fn()=> $this->repo->completeTask($this->id('lead_id'),$this->id('task_id')));
    }

    public function attach(): void
    {
        $this->mutation(function():void {
            $file=$_FILES['attachment'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
                || !is_string($file['name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new \InvalidArgumentException('Upload tidak valid atau file terlalu besar.');
            $this->repo->attach($this->id('lead_id'),$file['tmp_name'],$file['name']);
        });
    }

    public function deleteAttachment(): void
    {
        $this->mutation(fn()=> $this->repo->deleteAttachment($this->id('attachment_id')));
    }

    public function download(int $id): void
    {
        $file=$this->repo->attachment($id);
        if (!$file) {http_response_code(404);echo 'Lampiran tidak ditemukan.';return;}
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"attachment\"; filename*=UTF-8''".rawurlencode($file['name']));
        header('Cache-Control: private, no-store');
        echo $file['content'];
    }

    public function export(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="prospek.csv"');
        header('Cache-Control: private, no-store');
        $out=fopen('php://output','w');
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['ID','Usaha','Proyek','Telepon','Alamat','Website','Status','Catatan','Nilai deal'],',','"','');
        foreach($this->repo->exportRows() as $row) {
            fputcsv($out,array_map(static function($v):string {
                $s=(string)($v ?? '');return preg_match('/^[\s]*[=+@\-\t\r]/u',$s)?"'".$s:$s;
            },array_values($row)),',','"','');
        }
        fclose($out);
    }
}
