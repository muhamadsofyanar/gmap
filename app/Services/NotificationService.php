<?php
namespace App\Services;

use App\Core\Database;
use App\Models\PlatformSettings;
use PDO;

/** Transactional account notifications, independent of broadcast gateway configuration. */
class NotificationService
{
    private PDO $db;
    private PlatformSettings $settings;
    public function __construct(?PDO $db=null,?PlatformSettings $settings=null)
    {
        $this->db=$db??Database::platform();
        $this->settings=$settings??new PlatformSettings($this->db);
    }

    public function ready(string $channel): bool
    {
        if(!in_array($channel,['mailketing','starsender'],true)) return false;
        try { $config=$this->settings->get(); } catch(\Throwable $e) { return false; }
        return $config[$channel.'_api_key']!=='' && ($channel!=='mailketing'||($config['from_name']!=='' && filter_var($config['from_email'],FILTER_VALIDATE_EMAIL)!==false));
    }

    public function send(array $recipient,string $purpose,string $url): array
    {
        if(!in_array($purpose,['verification','reset','invite','registration'],true)) throw new \InvalidArgumentException('Tujuan notifikasi tidak valid.');
        return $this->deliver($recipient,$purpose,$url);
    }

    public function sendTest(string $channel,string $destination): array
    {
        return $this->deliver(['channel'=>$channel,$channel==='starsender'?'phone':'email'=>$destination,'name'=>'Admin'],'test','');
    }

    private function deliver(array $recipient,string $purpose,string $url): array
    {
        $config=$this->settings->get();
        $channel=$recipient['channel']??$config['channel'];
        if(!in_array($channel,['mailketing','starsender'],true)) throw new \InvalidArgumentException('Kanal notifikasi tidak valid.');
        $destination=$recipient[$channel==='starsender'?'phone':'email']??'';
        if(!is_string($destination)) throw new \InvalidArgumentException('Tujuan notifikasi tidak valid.');
        if($channel==='mailketing') {
            if(strlen($destination)>100||!filter_var($destination,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n\x00]/',$destination)) throw new \InvalidArgumentException('Alamat email tidak valid.');
        } else {
            if(!preg_match('/^\+?[0-9][0-9 ()-]{6,28}$/',$destination)) throw new \InvalidArgumentException('Nomor WhatsApp tidak valid.');
            $destination=preg_replace('/[^0-9]/','',$destination);
            if(str_starts_with($destination,'0')) $destination='62'.substr($destination,1);
            if(!preg_match('/^[1-9][0-9]{7,14}$/',$destination)) throw new \InvalidArgumentException('Nomor WhatsApp tidak valid.');
        }
        $needsLink=in_array($purpose,['verification','reset','invite'],true);
        if($needsLink) {
            $parsed=parse_url($url);
            if(!filter_var($url,FILTER_VALIDATE_URL)||!is_array($parsed)||($parsed['scheme']??'')!=='https'||empty($parsed['host'])||isset($parsed['user'])||isset($parsed['pass'])||preg_match('/[\x00-\x20\x7f]/',$url)) throw new \InvalidArgumentException('Tautan akun tidak valid.');
        } else $url=''; // Administrative registration notices must never include account-action tokens.
        $template=$purpose==='test'?'Pesan uji notifikasi {app_name}.':$config['templates'][$purpose];
        if($needsLink&&!str_contains($template,'{link}')) $template.="\n{link}";
        $name=is_string($recipient['name']??null)?$recipient['name']:'';
        $text=strtr($template,['{name}'=>$name,'{app_name}'=>$config['from_name'],'{link}'=>$url]);
        $result=['status'=>'failed','http_code'=>0];
        if($this->ready($channel)) {
            try {
                if($channel==='mailketing') {
                    $subject=match($purpose) { 'verification'=>'Verifikasi akun', 'reset'=>'Atur ulang kata sandi', 'invite'=>'Undangan bergabung', 'registration'=>'Pendaftaran akun', default=>'Uji notifikasi' };
                    [$code,$body,$errno]=$this->mailketingTransport($config,[
                        'from_name'=>$config['from_name'],'from_email'=>$config['from_email'],'subject'=>$subject,
                        'recipient'=>$destination,'content'=>nl2br(htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))
                    ]);
                    $data=is_string($body)?json_decode($body,true):null;
                    $status='unknown';
                    if($errno) $status=in_array($errno,[5,6,7,35,60,77],true)?'failed':'unknown';
                    elseif($code>=400&&$code<500&&$code!==408) $status='failed';
                    elseif($code>=200&&$code<300&&is_array($data)) {
                        if(($data['success']??null)===true) $status='accepted';
                        elseif(($data['success']??null)===false) $status='failed';
                    }
                    $result=['status'=>$status,'http_code'=>$errno?0:(int)$code];
                } else {
                    $response=$this->starsenderTransport(['api_key'=>$config['starsender_api_key']],$destination,$text);
                    $result=['status'=>in_array($response['status']??null,['accepted','failed','unknown'],true)?$response['status']:'unknown','http_code'=>(int)($response['http_code']??0)];
                }
            } catch(\Throwable $e) { $result=['status'=>'unknown','http_code'=>0]; }
        }
        // Whitelist fields only. Provider responses and exception text may contain secrets.
        $masked=$channel==='mailketing'?substr($destination,0,1).'***@'.substr(strrchr($destination,'@'),1):'***'.substr($destination,-4);
        $accountId=isset($recipient['account_id'])&&filter_var($recipient['account_id'],FILTER_VALIDATE_INT)!==false&&(int)$recipient['account_id']>0?(int)$recipient['account_id']:null;
        $this->db->prepare('INSERT INTO platform_notification_log(account_id,purpose,channel,destination_masked,status,http_code) VALUES(?,?,?,?,?,?)')->execute([$accountId,$purpose,$channel,$masked,$result['status'],$result['http_code']]);
        return $result;
    }

    /** Fixed HTTPS endpoint; redirects disabled; never accept a URL from settings. */
    protected function mailketingTransport(array $config,array $payload): array
    {
        $ch=curl_init('https://api.mailketing.co.id/api/v2/send');
        curl_setopt_array($ch,XSenderService::connectionOptions()+[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','X-Api-Token: '.$config['mailketing_api_key']],
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)
        ]);
        $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$errno=curl_errno($ch);curl_close($ch);
        return [$code,$body,$errno];
    }

    protected function starsenderTransport(array $credentials,string $number,string $body): array
    {
        return (new StarsenderService())->send($credentials,$number,$body);
    }
}
