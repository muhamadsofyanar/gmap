<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\IntakeModel;
use App\Models\SalesModel;

class IntakeController extends Controller
{
    /** Project-first CSV/Excel import; review before committing. */
    private const IMPORT_ENABLED = true;

    private function page(array $extra=[]): void
    {
        $this->view('intake/index', $extra+['pageTitle'=>'Tambah prospek','activeSection'=>'projects','projects'=>(new \App\Models\ProjectModel())->getAllProjects(),'selectedProject'=>max(0,(int)($_GET['project_id']??$_POST['project_id']??0))]);
    }
    public function index(): void { $this->requireAuth(); $this->page(); }

    /** Manual entry still reuses IntakeModel internally; only the import routes are closed. */
    private function importDisabled(): void
    {
        $this->requireAuth();
        set_flash('info','Impor CSV / Excel tidak tersedia. Tambahkan prospek satu per satu.');
        $this->redirect('intake');
    }

    public function manual(): void
    {
        $this->requireAuth(); $this->validateCsrf();
        try {
            $row=[]; foreach (IntakeModel::FIELDS as $key=>$_) $row[]=SalesModel::text($_POST,$key,4000);
            $model=new IntakeModel(); $token=SalesModel::text($_POST,'token',32,true);
            $model->draft($token,max(0,(int)($_POST['project_id']??0)),SalesModel::text($_POST,'project_name',200),['headers'=>array_values(IntakeModel::FIELDS),'rows'=>[$row]],'manual');
            $existing=$model->batch($token);
            if ($existing['result']) { $result=json_decode($existing['result'],true,512,JSON_THROW_ON_ERROR); $this->redirect('projects/viewProject/'.$result['project_id']); return; }
            $mapping=[]; foreach (array_keys(IntakeModel::FIELDS) as $i=>$key) $mapping[$key]=(string)$i;
            $model->map($token,$mapping); $review=$model->review($model->batch($token));
            if (!$review[0]['eligible']) throw new \InvalidArgumentException($review[0]['message']);
            $result=$model->commit($token,['0']); set_flash('success','Prospek ditambahkan ke proyek. Pilih prospek lalu tambahkan ke CRM bila siap ditindaklanjuti.');
            $this->redirect('projects/viewProject/'.$result['project_id']);
        } catch (\InvalidArgumentException $e) { $this->page(['error'=>$e->getMessage(),'old'=>$_POST]); }
    }

    public function upload(): void
    {
        if (!self::IMPORT_ENABLED) { $this->importDisabled(); return; }
        $this->requireAuth(); $this->validateCsrf();
        try {
            $file=$_FILES['file']??[];
            if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) throw new \InvalidArgumentException('Pilih file CSV/XLSX yang valid, maksimal 5 MB.');
            $payload=\App\Services\ProspectFile::read($file['tmp_name'],$file['name']);
            $token=bin2hex(random_bytes(16));
            (new IntakeModel())->draft($token,max(0,(int)($_POST['project_id']??0)),SalesModel::text($_POST,'project_name',200),$payload,$payload['source']);
            $this->redirect('intake/mapping?token='.$token);
        } catch (\InvalidArgumentException $e) { $this->page(['error'=>$e->getMessage(),'old'=>$_POST]); }
    }

    public function mapping(): void
    {
        if (!self::IMPORT_ENABLED) { $this->importDisabled(); return; }
        $this->requireAuth();
        try {
            $batch=(new IntakeModel())->batch(SalesModel::text($_GET,'token',32,true));
            $this->view('intake/mapping',['pageTitle'=>'Cocokkan kolom impor','activeSection'=>'projects','batch'=>$batch,'payload'=>json_decode($batch['payload'],true,512,JSON_THROW_ON_ERROR)]);
        } catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); $this->redirect('intake'); }
    }

    public function preview(): void
    {
        if (!self::IMPORT_ENABLED) { $this->importDisabled(); return; }
        $this->requireAuth(); $this->validateCsrf();
        $token=SalesModel::text($_POST,'token',32,true);
        try { (new IntakeModel())->map($token,is_array($_POST['mapping']??null)?$_POST['mapping']:[]); $this->redirect('intake/review?token='.$token); }
        catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); $this->redirect('intake/mapping?token='.$token); }
    }

    public function review(): void
    {
        if (!self::IMPORT_ENABLED) { $this->importDisabled(); return; }
        $this->requireAuth();
        try {
            $model=new IntakeModel(); $batch=$model->batch(SalesModel::text($_GET,'token',32,true));
            $this->view('intake/review',['pageTitle'=>'Tinjau impor prospek','activeSection'=>'projects','batch'=>$batch,'rows'=>$model->review($batch)]);
        } catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); $this->redirect('intake'); }
    }

    public function commit(): void
    {
        if (!self::IMPORT_ENABLED) { $this->importDisabled(); return; }
        $this->requireAuth(); $this->validateCsrf();
        try {
            $selected=is_array($_POST['selected']??null)?$_POST['selected']:[];
            if(isset($_POST['selected_json'])) {
                $encoded=$_POST['selected_json'];
                if(!is_string($encoded) || strlen($encoded)>12000)throw new \InvalidArgumentException('Pilihan baris tidak valid.');
                try{$selected=json_decode($encoded,false,8,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new \InvalidArgumentException('Pilihan baris tidak valid.');}
                if(!is_array($selected) || !array_is_list($selected) || count($selected)>1000)throw new \InvalidArgumentException('Pilihan baris tidak valid.');
            }
            $result=(new IntakeModel())->commit(SalesModel::text($_POST,'token',32,true),$selected);
            set_flash('success',$result['inserted'].' prospek diimpor; '.$result['skipped'].' pilihan dilewati. Data lama tetap tersimpan.');
            $this->redirect('projects/viewProject/'.$result['project_id']);
        } catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); $this->redirect('intake/review?token='.rawurlencode(is_string($_POST['token']??null)?$_POST['token']:'')); }
    }

    public function report(): void
    {
        $this->requireAuth();
        try {
            $model=new IntakeModel();
            $batch=$model->batch(SalesModel::text($_GET,'token',32,true));
            $rows=$model->review($batch);
        } catch (\InvalidArgumentException $e) { set_flash('error',$e->getMessage()); $this->redirect('intake'); return; }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="hasil-tinjauan-impor.csv"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        $out=fopen('php://output','w');
        fputcsv($out,['Baris','Nama bisnis','Telepon','Alamat','Status','Keterangan'],',','"','');
        foreach ($rows as $item) {
            $cells=[(string)($item['index']+2),$item['row']['shop_name']??'',$item['row']['phone']??'',$item['row']['address']??'',$item['eligible']?'Valid':'Dilewati',$item['message']];
            // Prevent spreadsheet formula execution, including whitespace-prefixed formulas.
            $cells=array_map(static fn($value)=>preg_match('/^[\s]*[=+@-]/u',$value) ? "'".$value : $value,$cells);
            fputcsv($out,$cells,',','"','');
        }
        fclose($out);
    }

    public function example(): void
    {
        if (!self::IMPORT_ENABLED) { $this->importDisabled(); return; }
        $this->requireAuth(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="contoh-prospek.csv"');
        echo "\xEF\xBB\xBF"; $out=fopen('php://output','w'); fputcsv($out,array_values(IntakeModel::FIELDS),',','"','');
        fputcsv($out,['Contoh Bisnis','','Jl. Contoh 1','Retail','','','Ganti dengan data bisnis Anda'],',','"',''); fclose($out);
    }
}
