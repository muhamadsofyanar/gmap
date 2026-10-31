<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Services\ClientCallback;
use App\Services\PlatformContext;
final class CallbackController extends Controller
{
    public function index(): void
    {
        try{$actor=PlatformContext::requireOwner();$settings=ClientCallback::settings();$this->view('settings/client-callback',['pageTitle'=>'Webhook workspace','activeSection'=>'settings','settings'=>$settings]);}
        catch(\DomainException $e){http_response_code(403);echo 'Akses pemilik workspace diperlukan.';}
    }
    public function save(): void
    {
        $this->validateCsrf();
        try{
            PlatformContext::requireOwner();$device=$_POST['device']??'';
            if(!is_string($device))throw new \InvalidArgumentException('Device ID tidak valid.');
            ClientCallback::save(trim($device),($_POST['rotate']??'')==='1');set_flash('success','Konfigurasi callback workspace tersimpan.');
        }catch(\DomainException $e){http_response_code(403);echo 'Akses pemilik workspace diperlukan.';return;}
        catch(\InvalidArgumentException $e){set_flash('error',$e->getMessage());}
        $this->redirect('callback');
    }
}
