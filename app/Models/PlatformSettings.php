<?php
namespace App\Models;

use App\Core\Database;
use App\Services\GatewaySecret;
use PDO;

/** Central platform configuration; never resolve this through the tenant connection. */
class PlatformSettings
{
    private PDO $db;
    private const KEYS = ['mailketing_api_key','starsender_api_key'];
    public function __construct(?PDO $db = null) { $this->db = $db ?? Database::platform(); }

    private function defaults(): array
    {
        return [
            'registration_enabled'=>0,'approval_required'=>0,'channel'=>'mailketing',
            'mailketing_api_key'=>'','starsender_api_key'=>'','from_email'=>'','from_name'=>'LegaOne',
            'admin_email'=>'','admin_phone'=>'',
            'templates'=>[
                'verification'=>'Halo {name}, verifikasi akun {app_name}: {link}',
                'reset'=>'Halo {name}, atur ulang kata sandi {app_name}: {link}',
                'invite'=>'Halo {name}, Anda diundang bergabung di {app_name}: {link}',
                'registration'=>'Pendaftaran akun {name} di {app_name} telah diterima.'
            ]
        ];
    }

    public function get(): array
    {
        $raw=$this->db->query('SELECT payload FROM platform_settings WHERE id=1')->fetchColumn();
        if ($raw===false) return $this->defaults();
        $saved=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($saved)) throw new \RuntimeException('Konfigurasi platform tidak dapat dibaca.');
        $config=array_replace($this->defaults(),array_intersect_key($saved,$this->defaults()));
        $config['templates']=array_replace($this->defaults()['templates'],is_array($saved['templates']??null)?$saved['templates']:[]);
        foreach (self::KEYS as $key) {
            $config[$key]=!empty($saved[$key]) ? (new GatewaySecret())->decrypt($saved[$key]) : '';
        }
        return $config;
    }

    public function publicSettings(): array
    {
        $config=$this->get();
        foreach(self::KEYS as $key) { $config[$key.'_configured']=$config[$key]!==''; $config[$key]=''; }
        return $config;
    }

    /** Blank API-key inputs intentionally preserve the existing secret. */
    public function save(array $input): void
    {
        $config=$this->get();
        foreach(['registration_enabled','approval_required'] as $key) if(array_key_exists($key,$input)) {
            if(!in_array($input[$key],[0,1,'0','1',false,true],true)) throw new \InvalidArgumentException('Pilihan platform tidak valid.');
            $config[$key]=(int)$input[$key];
        }
        if(array_key_exists('channel',$input)) {
            if(!in_array($input['channel'],['mailketing','starsender'],true)) throw new \InvalidArgumentException('Kanal notifikasi tidak valid.');
            $config['channel']=$input['channel'];
        }
        foreach(['from_email','admin_email','from_name','admin_phone',...self::KEYS] as $key) if(array_key_exists($key,$input)) {
            if(!is_string($input[$key]) || preg_match('/[\x00-\x1f\x7f]/',$input[$key])) throw new \InvalidArgumentException('Nilai konfigurasi tidak valid.');
            $value=trim($input[$key]);
            if(strlen($value)>(in_array($key,self::KEYS,true)?4096:100)) throw new \InvalidArgumentException('Nilai konfigurasi terlalu panjang.');
            if(in_array($key,['from_email','admin_email'],true)&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Alamat email tidak valid.');
            if($key==='admin_phone'&&$value!==''&&!preg_match('/^\+?[0-9][0-9 ()-]{6,28}$/',$value)) throw new \InvalidArgumentException('Nomor telepon tidak valid.');
            if(in_array($key,self::KEYS,true)&&$value==='') continue;
            $config[$key]=$value;
        }
        if(array_key_exists('templates',$input)) {
            if(!is_array($input['templates'])) throw new \InvalidArgumentException('Template tidak valid.');
            foreach($input['templates'] as $purpose=>$template) {
                if(!array_key_exists($purpose,$this->defaults()['templates'])||!is_string($template)||strlen($template)>8000||str_contains($template,"\0")) throw new \InvalidArgumentException('Template tidak valid.');
                preg_match_all('/\{([^{}]+)\}/',$template,$matches);
                if(array_diff($matches[1],['name','app_name','link'])) throw new \InvalidArgumentException('Variabel template tidak dikenal.');
                $config['templates'][$purpose]=$template;
            }
        }
        foreach(self::KEYS as $key) if($config[$key]!=='') $config[$key]=(new GatewaySecret())->encrypt($config[$key]);
        $sql=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ? 'INSERT INTO platform_settings(id,payload) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET payload=excluded.payload'
            : 'INSERT INTO platform_settings(id,payload) VALUES(1,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)';
        $this->db->prepare($sql)->execute([json_encode($config,JSON_THROW_ON_ERROR)]);
    }
}
