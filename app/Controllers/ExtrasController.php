<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\WorkspaceExtras;
use App\Models\SalesModel;

class ExtrasController extends Controller
{
    public function contact(): void
    {
        $this->requireAuth(); $this->validateCsrf();
        $id=max(0,(int)($_POST['lead_id']??0));
        try {
            if (!in_array($_POST['blocked']??null,['0','1'],true)) throw new \InvalidArgumentException('Pilih preferensi kontak secara eksplisit.');
            (new \App\Models\ContactPolicy())->save($id,($_POST['blocked']??'')==='1',SalesModel::text($_POST,'reason',300));
            set_flash('success','Preferensi kontak tersimpan.');
        } catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); }
        $this->redirect('crm/detail?id='.$id.'#contactPreference');
    }

    public function upload(): void
    {
        $this->requireAuth(); $this->validateCsrf(); $id=max(0,(int)($_POST['lead_id']??0));
        try {
            $file=$_FILES['attachment']??[];
            if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) throw new \InvalidArgumentException('Unggah file yang valid (maksimal 5 MB).');
            (new WorkspaceExtras())->attach($id,$file['tmp_name'],$file['name']); set_flash('success','Lampiran tersimpan.');
        } catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); }
        $this->redirect('crm/detail?id='.$id.'#prospectAttachments');
    }

    public function removeAttachment(): void
    {
        $this->requireAuth(); $this->validateCsrf();
        try { $id=(new WorkspaceExtras())->deleteAttachment(max(0,(int)($_POST['attachment_id']??0))); set_flash('success','Lampiran dihapus.'); }
        catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); $this->redirect('crm'); return; }
        $this->redirect('crm/detail?id='.$id.'#prospectAttachments');
    }

    public function download(): void
    {
        $this->requireAuth();
        $q=Database::getInstance()->prepare('SELECT a.* FROM crm_attachments a JOIN project_leads l ON l.id=a.lead_id WHERE a.id=? AND l.in_crm=1'); $q->execute([max(0,(int)($_GET['id']??0))]); $file=$q->fetch();
        if (!$file) { http_response_code(404); echo 'Lampiran tidak ditemukan.'; return; }
        if ($file['content']==='') $file['content']=\App\Services\PrivateStorage::read(Database::getInstance(),'attachment',(int)$file['id']);
        if (strlen($file['content'])!==(int)$file['size']) { http_response_code(500); echo 'Lampiran belum dapat dibaca.'; return; }
        $ext=strtolower(preg_replace('/[^a-z0-9]/i','',pathinfo($file['name'],PATHINFO_EXTENSION)));
        header('Content-Type: '.$file['mime']); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
        // Never render tenant uploads in the app origin, even if a browser ignores the attachment disposition.
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Disposition: attachment; filename="attachment'.($ext!==''?'.'.$ext:'').'"; filename*=UTF-8\'\''.rawurlencode($file['name']));
        header('Content-Length: '.strlen($file['content'])); echo $file['content'];
    }
}
