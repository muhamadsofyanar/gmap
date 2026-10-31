<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Services\PlatformAccounts;
use App\Services\PlatformContext;

class AccountController extends Controller
{
    private PlatformAccounts $accounts;
    public function __construct()
    {
        if(!PlatformContext::enabled()){http_response_code(404);exit;}
        $this->accounts=new PlatformAccounts();
    }
    private function input(string $name):string{return is_string($_POST[$name]??null)?trim($_POST[$name]):'';}
    private function token():string{return is_string($_POST['token']??$_GET['token']??null)?($_POST['token']??$_GET['token']):'';}
    private function isPost():bool{return ($_SERVER['REQUEST_METHOD']??'GET')==='POST';}
    private function render(string $mode,array $data=[]):void{$this->view('account/form',$data+['mode'=>$mode,'token'=>$this->token()],false);}
    private function mutation(callable $action,string $success,string $route):void
    {
        $this->validateCsrf();
        try{$action();set_flash('success',$success);}
        catch(\DomainException|\InvalidArgumentException $e){set_flash('error',$e->getMessage());}
        catch(\Throwable $e){set_flash('error','Permintaan belum dapat diproses. Silakan coba kembali atau hubungi administrator.');}
        $this->redirect($route);
    }
    public function register():void
    {
        if($this->isPost())$this->mutation(fn()=>$this->accounts->register($_POST),'Pendaftaran diterima. Periksa pesan verifikasi sebelum masuk.','login');
        $this->render('register');
    }
    public function forgot():void
    {
        if($this->isPost()){
            $this->validateCsrf();
            try{$this->accounts->forgot($this->input('identifier'));}catch(\Throwable $ignored){}
            set_flash('success','Jika akun memenuhi syarat, tautan pemulihan telah dikirim ke kanal yang terverifikasi.');$this->redirect('account/forgot');
        }
        $this->render('forgot');
    }
    public function resend():void
    {
        if($this->isPost()){
            $this->validateCsrf();
            try{$this->accounts->resend($this->input('identifier'));}catch(\Throwable $ignored){}
            set_flash('success','Jika akun memenuhi syarat, permintaan verifikasi telah diproses.');$this->redirect('account/resend');
        }
        $this->render('resend');
    }
    public function verify():void{$this->linkAction('verify');}
    public function reset():void{$this->linkAction('reset');}
    public function accept():void{$this->linkAction('invite');}
    private function linkAction(string $purpose):void
    {
        if($this->isPost()){
            $this->validateCsrf();
            try{
                match($purpose){'verify'=>$this->accounts->verify($this->token()),'reset'=>$this->accounts->reset($this->token(),$_POST),'invite'=>$this->accounts->accept($this->token(),$_POST)};
                set_flash('success',match($purpose){'verify'=>'Verifikasi berhasil. Akun dapat masuk setelah aktivasi atau persetujuan administrator.','reset'=>'Kata sandi berhasil diperbarui. Silakan masuk kembali.','invite'=>'Undangan berhasil diterima. Silakan masuk.'});
                $this->redirect('login');
            }catch(\DomainException|\InvalidArgumentException $e){$error=$e->getMessage();}
            catch(\Throwable $e){$error='Permintaan belum berhasil. Silakan coba kembali; bila perlu minta tautan baru.';}
        }
        try{$link=$this->accounts->inspectToken($this->token(),$purpose);$valid=true;}
        catch(\Throwable $e){$valid=false;$error='Tautan tidak valid, telah digunakan, atau sudah kedaluwarsa.';}
        header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
        $this->render($purpose==='invite'?'accept':$purpose,['valid'=>$valid,'error'=>$error??null,'link'=>$link??[]]);
    }
    private function authorize(bool $platformAdmin=false):bool
    {
        try{$platformAdmin?PlatformContext::requirePlatformAdmin():PlatformContext::requireOwner();return true;}
        catch(\DomainException $e){http_response_code(403);echo e($e->getMessage());return false;}
    }
    public function index():void
    {
        try{$actor=PlatformContext::requireOwner();$members=$this->accounts->members();$registry=$actor['is_platform_admin']&&((int)$actor['workspace_id']===1)?$this->accounts->listAccounts():[];}
        catch(\DomainException $e){http_response_code(403);echo e($e->getMessage());return;}
        $this->view('account/index',compact('actor','members','registry')+['pageTitle'=>'Akun & undangan']);
    }
    public function invite():void
    {
        if(!$this->authorize())return;$this->mutation(fn()=>$this->accounts->invite($_POST),'Undangan dikirim ke tujuan yang ditentukan.','account');
    }
    /**
     * Backward-compatible alias for the singular URL used in earlier instructions.
     * Canonical route: /account/settings
     */
    public function setting():void
    {
        $this->redirect('account/settings');
    }

    public function settings():void
    {
        try{PlatformContext::requirePlatformAdmin();$settings=$this->accounts->publicSettings();}
        catch(\DomainException $e){http_response_code(403);echo e($e->getMessage());return;}
        $logs=\App\Core\Database::platform()->query('SELECT purpose,channel,destination_masked,status,http_code,created_at FROM platform_notification_log ORDER BY id DESC LIMIT 50')->fetchAll();
        $this->view('account/settings',compact('settings','logs')+['pageTitle'=>'Pengaturan akun platform']);
    }
    public function saveSettings():void
    {
        if(!$this->authorize(true))return;$input=$_POST;$input['registration_enabled']=isset($_POST['registration_enabled'])?'1':'0';$input['approval_required']=isset($_POST['approval_required'])?'1':'0';
        $this->mutation(fn()=>$this->accounts->saveSettings($input),'Pengaturan platform disimpan.','account/settings');
    }
    public function testNotification():void
    {
        if(!$this->authorize(true))return;
        $this->mutation(function(){$result=$this->accounts->testNotification($this->input('channel'),$this->input('destination'));if($result['status']!=='accepted')throw new \DomainException('Provider belum mengonfirmasi pesan uji. Periksa konfigurasi dan log notifikasi.');},'Provider menerima pesan uji.','account/settings');
    }
    public function approve():void{if(!$this->authorize(true))return;$this->mutation(fn()=>$this->accounts->approve((int)$this->input('id')),'Akun disetujui dan workspace aktif.','account');}
    public function disable():void{if(!$this->authorize(true))return;$this->mutation(fn()=>$this->accounts->disable((int)$this->input('id')),'Akun dinonaktifkan.','account');}
}
