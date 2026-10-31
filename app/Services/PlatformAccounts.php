<?php
namespace App\Services;

use App\Core\Database;
use App\Models\PlatformSettings;
use PDO;

/** Central identity lifecycle. Business credentials stay in the registered workspace. */
class PlatformAccounts
{
    private PDO $db;
    private NotificationService $notifications;
    private WorkspaceProvisioner $provisioner;
    private PlatformSettings $settings;

    public function __construct(?PDO $db=null,?NotificationService $notifications=null,?WorkspaceProvisioner $provisioner=null)
    {
        $this->db=$db??Database::platform();
        $this->settings=new PlatformSettings($this->db);
        $this->notifications=$notifications??new NotificationService($this->db,$this->settings);
        $this->provisioner=$provisioner??new WorkspaceProvisioner();
    }

    private function lock():string{return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';}
    private function row(string $sql,array $args=[]):?array{$q=$this->db->prepare($sql);$q->execute($args);return $q->fetch()?:null;}
    private function execute(string $sql,array $args=[]):void{$this->db->prepare($sql)->execute($args);}
    private function transaction(callable $action):mixed
    {
        $this->db->beginTransaction();
        try{$result=$action();$this->db->commit();return $result;}
        catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    /** Central buckets remain central even when an authenticated request has bound a tenant. */
    public function throttle(string $action,string $identifier='',int $limit=5):void
    {
        if(in_array($action,['register','forgot','resend','login'],true)){
            $account=$this->row('SELECT id FROM platform_accounts WHERE email=? OR username=?',[mb_strtolower($identifier),mb_strtolower($identifier)]);
            if($account)$identifier='id:'.$account['id'];
        }
        $scopes=[$action.'|ip|'.($_SERVER['REMOTE_ADDR']??'local')];
        if($identifier!=='')$scopes[]=$action.'|account|'.mb_strtolower($identifier);
        foreach($scopes as $scope){
            $key=hash('sha256','platform|'.$scope);$now=time();
            $sql=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
                ? 'INSERT INTO rate_limits(bucket,attempts,reset_at) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET attempts=CASE WHEN reset_at<=? THEN 1 ELSE attempts+1 END,reset_at=CASE WHEN reset_at<=? THEN excluded.reset_at ELSE reset_at END'
                : 'INSERT INTO rate_limits(bucket,attempts,reset_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(reset_at<=?,1,attempts+1),reset_at=IF(reset_at<=?,VALUES(reset_at),reset_at)';
            $this->execute($sql,[$key,$now+900,$now,$now]);
            $q=$this->db->prepare('SELECT attempts FROM rate_limits WHERE bucket=?');$q->execute([$key]);
            if((int)$q->fetchColumn()>$limit)throw new \DomainException('Terlalu banyak permintaan. Coba kembali dalam 15 menit.');
        }
    }
    private function text(array $input,string $key,int $max):string
    {
        $value=$input[$key]??'';
        if(!is_string($value)||strlen($value)>$max||preg_match('/[\x00-\x1f\x7f]/',$value))throw new \InvalidArgumentException('Data akun tidak valid.');
        return trim($value);
    }
    private function identity(array $input):array
    {
        $name=$this->text($input,'name',100);$username=mb_strtolower($this->text($input,'username',50));
        if($name===''||!preg_match('/^[a-z0-9][a-z0-9_.-]{2,49}$/D',$username))throw new \InvalidArgumentException('Nama wajib diisi; username 3–50 karakter huruf, angka, titik, garis bawah atau tanda hubung.');
        return ['name'=>$name,'username'=>$username];
    }
    private function password(array $input):string
    {
        $password=$input['password']??null;
        if(!is_string($password)||strlen($password)<8||strlen($password)>72||str_contains($password,"\0")||$password!==($input['password_confirmation']??null))throw new \InvalidArgumentException('Kata sandi minimal 8, maksimal 72 karakter, dan konfirmasi harus sama.');
        return password_hash_secure($password);
    }
    private function destination(array $input,string $channel):array
    {
        $email=mb_strtolower($this->text($input,'email',100));$phone=$this->text($input,'phone',30);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Alamat email tidak valid.');
        if(($phone!==''||$channel==='starsender')&&!preg_match('/^\+?[0-9][0-9 ()-]{6,28}$/D',$phone))throw new \InvalidArgumentException('Nomor WhatsApp tidak valid.');
        if($phone!==''&& !preg_match('/^[1-9][0-9]{7,14}$/D',str_starts_with(preg_replace('/[^0-9]/','',$phone),'0')?'62'.substr(preg_replace('/[^0-9]/','',$phone),1):preg_replace('/[^0-9]/','',$phone)))throw new \InvalidArgumentException('Nomor WhatsApp tidak valid.');
        return ['email'=>$email,'phone'=>$phone,'channel'=>$channel];
    }
    private function unique(string $email,string $username):void
    {
        if($this->row('SELECT id FROM platform_accounts WHERE email=? OR username=? OR email=? OR username=?',[$email,$username,$username,$email]))throw new \InvalidArgumentException('Identitas akun tidak tersedia. Gunakan akun Anda atau hubungi pemilik workspace.');
    }
    private function issue(string $purpose,array $recipient,?int $accountId,?int $workspaceId,int $ttl,?array $payload=null):void
    {
        $token=bin2hex(random_bytes(32));
        $this->execute('INSERT INTO platform_tokens(token_hash,purpose,account_id,workspace_id,email,phone,channel,expires_at,payload) VALUES(?,?,?,?,?,?,?,?,?)',[hash('sha256',$token),$purpose,$accountId,$workspaceId,$recipient['email'],$recipient['phone']??'',$recipient['channel'],time()+$ttl,$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR)]);
        $route=['verify'=>'verify','reset'=>'reset','invite'=>'accept'][$purpose];
        $recipient['account_id']=$accountId;
        $result=$this->notifications->send($recipient,['verify'=>'verification','reset'=>'reset','invite'=>'invite'][$purpose],base_url('account/'.$route).'?token='.$token);
        if($result['status']!=='accepted')throw new \DomainException('Pengiriman notifikasi belum dikonfirmasi. Bila tautan belum diterima, minta tautan baru.');
    }
    public function register(array $input):void
    {
        $config=$this->settings->get();
        if(!$config['registration_enabled']||!$this->notifications->ready($config['channel'])||!$this->provisioner::ready())throw new \DomainException('Pendaftaran belum tersedia. Hubungi administrator.');
        $identity=$this->identity($input);$destination=$this->destination($input,$config['channel']);$hash=$this->password($input);
        $this->throttle('register',$destination['email']);
        $this->unique($destination['email'],$identity['username']);
        try{
            $this->transaction(function()use($identity,$destination,$hash){
                $this->execute("INSERT INTO platform_accounts(email,username,name,phone,password_hash,state) VALUES(?,?,?,?,?,'pending')",[$destination['email'],$identity['username'],$identity['name'],$destination['phone'],$hash]);
            });
        }catch(\PDOException $e){if($e->getCode()==='23000')throw new \InvalidArgumentException('Identitas akun tidak tersedia.');throw $e;}
        $account=$this->row('SELECT * FROM platform_accounts WHERE email=?',[$destination['email']]);
        $this->issue('verify',$destination+$identity,(int)$account['id'],null,1800);
        $admin=['email'=>$config['admin_email'],'phone'=>$config['admin_phone'],'channel'=>$config['channel'],'name'=>$identity['name']];
        if(($config['channel']==='mailketing'?$admin['email']:$admin['phone'])!=='')$this->notifications->send($admin,'registration','');
    }
    /** Read-only: GET never marks a link as used. */
    public function inspectToken(string $token,string $purpose):array
    {
        if(!in_array($purpose,['verify','reset','invite'],true)||!preg_match('/^[a-f0-9]{64}$/D',$token))throw new \DomainException('Tautan tidak valid atau telah kedaluwarsa.');
        $row=$this->row('SELECT * FROM platform_tokens WHERE token_hash=? AND purpose=? AND consumed_at IS NULL AND expires_at>?',[hash('sha256',$token),$purpose,time()]);
        if(!$row)throw new \DomainException('Tautan tidak valid atau telah kedaluwarsa.');
        return $row;
    }
    private function lockedToken(string $token,string $purpose):array
    {
        $this->inspectToken($token,$purpose);
        $row=$this->row('SELECT * FROM platform_tokens WHERE token_hash=? AND purpose=? AND consumed_at IS NULL AND expires_at>?'.$this->lock(),[hash('sha256',$token),$purpose,time()]);
        if(!$row)throw new \DomainException('Tautan tidak valid atau telah kedaluwarsa.');
        return $row;
    }
    private function consume(array $token):void{$this->execute('UPDATE platform_tokens SET consumed_at=? WHERE id=? AND consumed_at IS NULL',[time(),$token['id']]);}
    private function activate(array $account):void
    {
        if(!in_array($account['state'],['pending','verified-pending-approval'],true)||empty($account['password_hash']))throw new \DomainException('Akun tidak dapat diaktifkan.');
        $workspaceId=(int)($account['workspace_id']??0);
        if(!$workspaceId){$this->execute("INSERT INTO platform_workspaces(name,state) VALUES(?,'pending')",[$account['name']]);$workspaceId=(int)$this->db->lastInsertId();$this->execute('UPDATE platform_accounts SET workspace_id=? WHERE id=?',[$workspaceId,$account['id']]);}
        $localId=$this->provisioner->provision($workspaceId,$account);
        $this->execute("UPDATE platform_accounts SET local_user_id=?,state='active',password_hash=NULL WHERE id=?",[$localId,$account['id']]);
    }
    public function verify(string $token):void
    {
        $this->throttle('verify-attempt','',30);$preview=$this->inspectToken($token,'verify');$this->throttle('verify',(string)$preview['account_id'],15);
        $this->transaction(function()use($token){
            $link=$this->lockedToken($token,'verify');
            $account=$this->row('SELECT * FROM platform_accounts WHERE id=?'.$this->lock(),[$link['account_id']]);
            if(!$account||$account['email']!==$link['email']||$account['phone']!==$link['phone'])throw new \DomainException('Akun tidak dapat diverifikasi.');
            $payload=json_decode($link['payload']??'null',true);
            if(($payload['kind']??null)==='destination'){
                $actor=$this->currentAccount();
                if($account['state']!=='active'||$account['verified_channel']!==null||!$actor||(int)$actor['id']!==(int)$account['id']||(int)$actor['workspace_id']!==(int)$account['workspace_id']||(int)$actor['local_user_id']!==(int)$account['local_user_id']||(int)$link['workspace_id']!==(int)$account['workspace_id'])throw new \DomainException('Masuk ke akun yang sesuai untuk memverifikasi tujuan pemulihan.');
                $local=$this->localIdentity($account);
                if(!$local||!isset($payload['session_version'])||!is_int($payload['session_version'])||(int)$local['session_version']!==$payload['session_version'])throw new \DomainException('Kredensial berubah. Minta tautan verifikasi baru.');
                $this->execute('UPDATE platform_accounts SET verified_channel=? WHERE id=?',[$link['channel'],$account['id']]);
                $this->execute("UPDATE platform_tokens SET consumed_at=? WHERE account_id=? AND purpose='verify' AND consumed_at IS NULL",[time(),$account['id']]);
                return;
            }
            if($account['state']!=='pending'||$payload!==null)throw new \DomainException('Akun tidak dapat diverifikasi.');
            $this->execute("UPDATE platform_accounts SET verified_channel=?,state='verified-pending-approval' WHERE id=?",[$link['channel'],$account['id']]);
            if(!$this->settings->get()['approval_required'])$this->activate($account);
            $this->consume($link);
            $this->execute("UPDATE platform_tokens SET consumed_at=? WHERE account_id=? AND purpose='verify' AND consumed_at IS NULL",[time(),$account['id']]);
        });
    }
    public function resend(string $identifier):void
    {
        $this->throttle('resend',$identifier);
        $account=$this->row("SELECT * FROM platform_accounts WHERE (email=? OR username=?) AND state IN ('pending','active')",[$identifier,$identifier]);
        if(!$account)return;
        $payload=null;$workspaceId=null;
        if($account['state']==='active'){
            $actor=$this->currentAccount();
            if($account['verified_channel']!==null||!$actor||(int)$actor['id']!==(int)$account['id']||(int)$actor['workspace_id']!==(int)$account['workspace_id']||(int)$actor['local_user_id']!==(int)$account['local_user_id'])return;
            $local=$this->localIdentity($account);if(!$local)return;
            $payload=['kind'=>'destination','session_version'=>(int)$local['session_version']];$workspaceId=(int)$account['workspace_id'];
        }
        $channel=$this->settings->get()['channel'];
        if(!$this->notifications->ready($channel))return;
        if($channel==='starsender'&&$account['phone']==='')return;
        $this->issue('verify',$account+['channel'=>$channel],(int)$account['id'],$workspaceId,1800,$payload);
    }
    public function forgot(string $identifier):void
    {
        $this->throttle('forgot',$identifier);
        $account=$this->row("SELECT a.* FROM platform_accounts a JOIN platform_workspaces w ON w.id=a.workspace_id WHERE (a.email=? OR a.username=?) AND a.state='active' AND w.state='ready'",[$identifier,$identifier]);
        if(!$account||!in_array($account['verified_channel'],['mailketing','starsender'],true)||!$this->notifications->ready($account['verified_channel']))return;
        $local=$this->localIdentity($account);if(!$local)return;
        $this->issue('reset',$account+['channel'=>$account['verified_channel']],(int)$account['id'],(int)$account['workspace_id'],1200,['session_version'=>(int)$local['session_version']]);
    }
    protected function currentAccount():?array{return PlatformContext::current();}
    private function localIdentity(array $account):?array
    {
        $db=$this->workspaceDb((int)$account['workspace_id']);
        $q=$db->prepare("SELECT id,session_version FROM users WHERE id=? AND workspace_id=1 AND deleted_at IS NULL AND role IN ('admin','user')");$q->execute([$account['local_user_id']]);return $q->fetch()?:null;
    }
    protected function workspaceDb(int $id):PDO{Database::useWorkspace($id);return Database::getInstance();}
    public function reset(string $token,array $input):void
    {
        $hash=$this->password($input);$this->throttle('reset-attempt','',30);$preview=$this->inspectToken($token,'reset');$this->throttle('reset',(string)$preview['account_id'],15);
        // Consume centrally first: any later tenant failure requires a new link, never replay.
        $account=$this->transaction(function()use($token){
            $link=$this->lockedToken($token,'reset');
            $account=$this->row("SELECT a.* FROM platform_accounts a JOIN platform_workspaces w ON w.id=a.workspace_id WHERE a.id=? AND a.state='active' AND w.state='ready'".$this->lock(),[$link['account_id']]);
            if(!$account||(int)$account['workspace_id']!==(int)$link['workspace_id']||$account['verified_channel']!==$link['channel']||$account['email']!==$link['email']||$account['phone']!==$link['phone'])throw new \DomainException('Pemulihan akun tidak tersedia.');
            $this->execute("UPDATE platform_tokens SET consumed_at=? WHERE account_id=? AND purpose='reset' AND consumed_at IS NULL",[time(),$account['id']]);$account['_reset_payload']=json_decode($link['payload']??'null',true);return $account;
        });
        $db=$this->workspaceDb((int)$account['workspace_id']);$db->beginTransaction();
        try{
            $lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';
            $q=$db->prepare("SELECT session_version FROM users WHERE id=? AND workspace_id=1 AND deleted_at IS NULL AND role IN ('admin','user')".$lock);$q->execute([$account['local_user_id']]);$local=$q->fetch();
            $payload=$account['_reset_payload'];
            if(!$local||!isset($payload['session_version'])||!is_int($payload['session_version'])||(int)$local['session_version']!==$payload['session_version'])throw new \DomainException('Kredensial telah berubah. Minta tautan pemulihan baru.');
            $q=$db->prepare("UPDATE users SET password=?,session_version=session_version+1 WHERE id=? AND workspace_id=1 AND deleted_at IS NULL AND role IN ('admin','user')");$q->execute([$hash,$account['local_user_id']]);
            if($q->rowCount()!==1)throw new \DomainException('Pemulihan akun tidak tersedia. Minta tautan baru.');
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public function authenticate(string $identifier,string $password):array|false
    {
        $account=$this->row("SELECT a.* FROM platform_accounts a JOIN platform_workspaces w ON w.id=a.workspace_id WHERE (a.email=? OR a.username=?) AND a.state='active' AND w.state='ready'",[$identifier,$identifier]);
        try{$this->throttle('login',$identifier,10);}catch(\DomainException $e){return false;}
        if(!$account){password_verify($password,'$2y$12$IC4aXn3s3bUPGGcqLRAFIugQvZ7OtPj.M8jaKQzY0Sr3P0GmdmDqC');return false;}
        try{
            $db=$this->workspaceDb((int)$account['workspace_id']);
            $q=$db->prepare("SELECT * FROM users WHERE id=? AND workspace_id=1 AND deleted_at IS NULL AND role IN ('admin','user')");$q->execute([$account['local_user_id']]);$user=$q->fetch();
            if(!$user||!password_verify($password,$user['password'])||strcasecmp($user['email'],$account['email'])!==0||$user['username']!==$account['username'])return false;
            unset($user['password']);$user['tenant_id']=(int)$account['workspace_id'];$user['platform_account_id']=(int)$account['id'];return $user;
        }catch(\Throwable $e){return false;}
    }
    public function listAccounts():array
    {
        PlatformContext::requirePlatformAdmin();
        return $this->db->query('SELECT id,name,email,username,phone,state,workspace_id,is_platform_admin FROM platform_accounts ORDER BY id DESC LIMIT 200')->fetchAll();
    }
    public function members():array
    {
        $actor=PlatformContext::requireOwner();$q=$this->db->prepare('SELECT id,name,email,username,state FROM platform_accounts WHERE workspace_id=? ORDER BY id');$q->execute([$actor['workspace_id']]);return $q->fetchAll();
    }
    public function approve(int $id):void
    {
        $actor=PlatformContext::requirePlatformAdmin();$this->throttle('approve',(string)$actor['id'],20);
        $this->transaction(function()use($id){$account=$this->row('SELECT * FROM platform_accounts WHERE id=?'.$this->lock(),[$id]);if(!$account||$account['state']!=='verified-pending-approval')throw new \DomainException('Akun belum siap disetujui.');$this->activate($account);});
    }
    public function disable(int $id):void
    {
        $actor=PlatformContext::requirePlatformAdmin();if($id===(int)$actor['id'])throw new \DomainException('Anda tidak dapat menonaktifkan akun sendiri.');
        $this->transaction(function()use($id){
            $account=$this->row('SELECT * FROM platform_accounts WHERE id=?'.$this->lock(),[$id]);
            if(!$account||$account['is_platform_admin'])throw new \DomainException('Akun tidak dapat dinonaktifkan.');
            if((int)$account['workspace_id']>1&&$account['local_user_id']){
                $local=$this->workspaceDb((int)$account['workspace_id']);$q=$local->prepare('SELECT role FROM users WHERE id=? AND workspace_id=1');$q->execute([$account['local_user_id']]);
                if($q->fetchColumn()==='admin')$this->execute("UPDATE platform_workspaces SET state='suspended' WHERE id=?",[$account['workspace_id']]);
            }
            $this->execute("UPDATE platform_accounts SET state='disabled',password_hash=NULL WHERE id=?",[$id]);
            $this->execute('UPDATE platform_tokens SET consumed_at=? WHERE account_id=? AND consumed_at IS NULL',[time(),$id]);
        });
    }
    public function invite(array $input):void
    {
        $actor=PlatformContext::requireOwner();$config=$this->settings->get();$destination=$this->destination($input,$config['channel']);
        $this->throttle('invite',(string)$actor['id']);
        if(!$this->notifications->ready($config['channel']))throw new \DomainException('Notifikasi undangan belum tersedia.');
        if($this->row('SELECT id FROM platform_accounts WHERE email=? OR username=?',[$destination['email'],$destination['email']]))throw new \InvalidArgumentException('Identitas akun tidak tersedia.');
        $this->issue('invite',$destination+['name'=>'Anggota tim'],null,(int)$actor['workspace_id'],172800);
    }
    public function accept(string $token,array $input):void
    {
        $identity=$this->identity($input);$hash=$this->password($input);$this->throttle('accept-attempt','',30);$preview=$this->inspectToken($token,'invite');$this->throttle('accept',$preview['email'],15);
        // Claim first. A failure after this point needs a fresh owner invitation.
        $link=$this->transaction(function()use($token,$identity){
            $link=$this->lockedToken($token,'invite');
            $workspace=$this->row("SELECT id FROM platform_workspaces WHERE id=? AND state='ready'".$this->lock(),[$link['workspace_id']]);
            if(!$workspace)throw new \DomainException('Workspace undangan tidak tersedia.');
            $this->unique($link['email'],$identity['username']);
            $this->consume($link);
            $this->execute("UPDATE platform_tokens SET consumed_at=? WHERE workspace_id=? AND email=? AND purpose='invite' AND consumed_at IS NULL",[time(),$link['workspace_id'],$link['email']]);
            return $link;
        });
        $db=$this->workspaceDb((int)$link['workspace_id']);$db->beginTransaction();$localId=null;
        try{
            $q=$db->prepare("INSERT INTO users(username,email,name,password,role,workspace_id) VALUES(?,?,?,?,'user',1)");$q->execute([$identity['username'],$link['email'],$identity['name'],$hash]);$localId=(int)$db->lastInsertId();
            if($db!==$this->db)$this->db->beginTransaction();
            $this->unique($link['email'],$identity['username']);
            $this->execute("INSERT INTO platform_accounts(workspace_id,local_user_id,email,username,name,phone,state,verified_channel) VALUES(?,?,?,?,?,?,'active',?)",[$link['workspace_id'],$localId,$link['email'],$identity['username'],$identity['name'],$link['phone'],$link['channel']]);
            $db->commit();if($db!==$this->db)$this->db->commit();
        }catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            if($db->inTransaction())$db->rollBack();
            // Best effort compensation of an unpublished local row; never reopen the token.
            elseif($localId!==null){try{$db->prepare('DELETE FROM users WHERE id=? AND email=?')->execute([$localId,$link['email']]);}catch(\Throwable $ignored){}}
            throw new \DomainException('Undangan belum dapat diterima. Minta undangan baru kepada pemilik workspace.');
        }
    }
    public function publicSettings():array{PlatformContext::requirePlatformAdmin();return $this->settings->publicSettings();}
    public function saveSettings(array $input):void{PlatformContext::requirePlatformAdmin();$this->settings->save($input);}
    public function testNotification(string $channel,string $destination):array{$actor=PlatformContext::requirePlatformAdmin();$this->throttle('notification-test',(string)$actor['id']);return $this->notifications->sendTest($channel,$destination);}
}
