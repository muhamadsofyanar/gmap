<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\CrmModel;
use App\Models\ProjectModel;

class CrmController extends Controller
{
    protected CrmModel $crmModel;
    protected ProjectModel $projectModel;

    public function __construct()
    {
        $this->crmModel = new CrmModel();
        $this->projectModel = new ProjectModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $projectId = max(0, (int)($_GET['project_id'] ?? 0));
        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all';
        if (!isset(CrmModel::STAGES[$status])) $status = 'all';
        $search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
        if ($projectId === 0) {
            $this->view('crm/projects', [
                'pageTitle' => 'CRM per Proyek - Gmap Prospek',
                'projects' => $this->crmModel->getProjectOverview($search),
                'searchQuery' => $search
            ]);
            return;
        }
        $project = $this->projectModel->getProjectById($projectId);
        if (!$project) {
            set_flash('error', 'Proyek tidak ditemukan. Pilih proyek dari daftar CRM.');
            $this->redirect('crm');
            return;
        }
        $page = max(1, min(100000, (int)($_GET['page'] ?? 1)));
        $dueFilter = in_array($_GET['due'] ?? '', ['today','overdue','unscheduled','repeat'], true) ? $_GET['due'] : 'all';
        $pageSize = 50;
        $viewMode = ($_GET['view'] ?? '') === 'pipeline' ? 'pipeline' : 'list';
        $columnPages = [];
        $requestedPages = is_array($_GET['column_page'] ?? null) ? $_GET['column_page'] : [];
        foreach (CrmModel::STAGES as $key => $_) {
            $value = $requestedPages[$key] ?? 1;
            $columnPages[$key] = is_scalar($value) ? max(1, min(100000, (int)$value)) : 1;
        }
        $pipeline = $viewMode === 'pipeline' ? $this->crmModel->getPipeline($projectId, $search, $columnPages, $status === 'all' ? null : $status, $dueFilter) : [];
        $leads = $viewMode === 'list' ? $this->crmModel->getLeads($projectId, $status === 'all' ? null : $status, $search ?: null, null, $pageSize + 1, ($page - 1) * $pageSize, $dueFilter) : [];
        $leads = $this->crmModel->withNextFollowup(\App\Models\ContactPolicy::annotate($leads));
        $hasNext = count($leads) > $pageSize;
        $this->view('crm/index', [
            'dueCounts' => $this->crmModel->dueCounts($projectId),
            'pageTitle' => 'CRM ' . $project['name'] . ' - Gmap Prospek',
            'project' => $project,
            'templates' => (new \App\Models\MessageTemplateModel())->all(),
            'selectedProject' => $projectId, 'selectedStatus' => $status,
            'viewMode' => $viewMode, 'pipeline' => $pipeline, 'columnPages' => $columnPages,
            'dueFilter' => $dueFilter,
            'searchQuery' => $search, 'page' => $page, 'pageSize' => $pageSize, 'hasNext' => $hasNext,
            'stats' => $this->crmModel->getFunnelStats($projectId ?: null),
            'leads' => array_slice($leads, 0, $pageSize), 'stages' => (new \App\Models\SalesModel())->stages($projectId)
        ]);
    }

    public function detail(): void
    {
        $this->requireAuth();
        $id = max(0, (int)($_GET['id'] ?? 0));
        $lead = $id > 0 ? $this->crmModel->getLeadById($id) : null;
        if (!$lead || !(int)$lead['in_crm']) {
            set_flash('error', 'Prospek tidak ditemukan di CRM.');
            $this->redirect('crm');
            return;
        }
        $status = is_string($_GET['status'] ?? null) && isset(CrmModel::STAGES[$_GET['status']]) ? $_GET['status'] : 'all';
        $search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
        $backUrl = base_url('crm?' . http_build_query([
            'project_id' => (int)$lead['project_id'], 'status' => $status,
            'view' => ($_GET['view'] ?? '') === 'pipeline' ? 'pipeline' : 'list',
            'due' => in_array($_GET['due']??'', ['today','overdue','unscheduled','repeat'],true)?$_GET['due']:'all',
            'search' => $search, 'page' => max(1, min(100000, (int)($_GET['page'] ?? 1)))
        ]));
        $this->view('crm/detail', [
            'conversationPreview' => (new \App\Models\ConversationRepository(\App\Core\Database::getInstance()))->thread(\App\Services\AuthGuard::identity(),null,$id,1,6),
            'contactBlocked' => \App\Models\ContactPolicy::blocked($lead),
            'contactPreference' => \App\Models\ContactPolicy::preference($lead),
            'attachments' => (new \App\Models\WorkspaceExtras())->attachments($id),
            'pageTitle' => $lead['shop_name'] . ' - CRM Prospek',
            'lead' => $lead, 'backUrl' => $backUrl, 'stages' => (new \App\Models\SalesModel())->stages((int)$lead['project_id']),
            'sales' => [
                'profile' => (new \App\Models\SalesModel())->profile($id),
                'tasks' => (new \App\Models\SalesModel())->tasks($id),
                'orders' => (new \App\Models\SalesModel())->orders($id),
                'duplicates' => (new \App\Models\SalesModel())->duplicates([$lead])[$id] ?? []
            ],
            'history' => $this->crmModel->getFunnelHistory($id),
            'gateway' => (new \App\Models\GatewayModel())->settings(),
            'deliveries' => (new \App\Models\GatewayModel())->history($id),
            'templates' => (new \App\Models\MessageTemplateModel())->all(),
            'selectedTemplate' => (int)($_GET['template_id'] ?? 0)
        ]);
    }

    protected function crmLead(int $id): array
    {
        $lead = $id > 0 ? $this->crmModel->getLeadById($id) : null;
        if (!$lead || !(int)$lead['in_crm'] || (int)($lead['workspace_id'] ?? 0)!==1 || (int)(($this->projectModel->getProjectById((int)$lead['project_id']))['workspace_id'] ?? 0)!==1) {
            $this->json(['success' => false, 'message' => 'Prospek tidak ditemukan di CRM.'], 404);
        }
        return $lead;
    }

    public function getLead(): void
    {
        $this->requireAuth();
        $lead = $this->crmLead((int)($_GET['id'] ?? 0));
        $this->json(['success' => true, 'lead' => $lead, 'history' => $this->crmModel->getFunnelHistory((int)$lead['id']), 'deliveries' => (new \App\Models\GatewayModel())->history((int)$lead['id'])]);
    }

    protected function gatewayService(string $provider): object
    {
        return \App\Models\GatewayModel::service($provider);
    }

    public function sendWhatsApp(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $templateId = filter_var($_POST['template_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $token = $_POST['request_token'] ?? '';
        $preview = $_POST['message'] ?? null;
        if (!$id || $templateId === false || !is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token) || !is_string($preview)) {
            $this->json(['success' => false, 'message' => 'Permintaan pengiriman tidak valid. Muat ulang halaman.'], 422);
        }
        $lead = $this->crmLead($id);
        if (\App\Models\ContactPolicy::blocked($lead)) $this->json(['success'=>false,'message'=>'Prospek ditandai Jangan dihubungi. Pengiriman dibatalkan.'],422);
        try {$message=\App\Services\MessageDraft::text($preview);}
        catch(\InvalidArgumentException $e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}
        if($templateId>0){
            $templates=array_column((new \App\Models\MessageTemplateModel())->all(),null,'id');
            if(!isset($templates[$templateId]))$this->json(['success'=>false,'message'=>'Template tidak ditemukan. Pilih Pesan bebas.'],422);
        }
        $model = new \App\Models\GatewayModel();
        try {
            $number = \App\Models\GatewayModel::phone($lead['whatsapp'] ?: ($lead['phone'] ?? ''));
            $credentials = $model->credentials();
            $reservation = $model->reserve($token, $id, $templateId, $number, $message);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Gateway belum dapat digunakan. Periksa pengaturan gateway.'], 500);
        }
        $status = $reservation['delivery']['status'];
        if ($reservation['created']) {
            // Reservation is committed before the network call. The same token is never sent twice.
            try {
                $result = $this->gatewayService($credentials['provider'])->send($credentials, $number, $message);
                $status = $result['status'];
                $model->finish((int)$reservation['delivery']['id'], $status, $result['http_code']);
            } catch (\Throwable $e) {
                $status = 'unknown';
                try { $model->finish((int)$reservation['delivery']['id'], $status, 0); } catch (\Throwable $ignored) {}
            }
        }
        $label = \App\Models\GatewayModel::label($credentials['provider']);
        $messages = [
            'accepted' => 'Permintaan pesan diterima gateway. Ini belum merupakan konfirmasi pesan sampai ke penerima.',
            'failed' => 'Pengiriman ditolak gateway. Periksa API key, nomor pengirim, dan koneksi perangkat di ' . $label . '.',
            'unknown' => 'Hasil pengiriman belum dapat dipastikan. Periksa riwayat di ' . $label . ' sebelum mengirim ulang agar pesan tidak ganda.',
            'pending' => 'Permintaan ini sudah diproses. Periksa riwayat di ' . $label . ' sebelum mengirim ulang.'
        ];
        $this->json(['success' => true, 'delivery_status' => $status, 'message' => $messages[$status] ?? $messages['unknown']]);
    }

    public function broadcastRecipients(): void
    {
        $this->requireAuth();
        $projectId=filter_var($_GET['project_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $project=$projectId?$this->projectModel->getProjectById($projectId):null;
        if(!$project || (int)$project['workspace_id']!==1)$this->json(['success'=>false,'message'=>'Proyek tidak ditemukan.'],404);
        $items=[];
        foreach((new \App\Models\BroadcastModel())->candidates($projectId) as $lead){
            $number='';$reason='';
            try{$number=\App\Models\GatewayModel::phone($lead['whatsapp'] ?: ($lead['phone'] ?? ''));}
            catch(\InvalidArgumentException $e){$reason='Nomor tidak valid';}
            if($lead['contact_blocked'])$reason='Jangan dihubungi';
            $items[]=['id'=>(int)$lead['id'],'name'=>$lead['shop_name'],'number'=>$number,'eligible'=>$reason==='','reason'=>$reason,'stage'=>$lead['lead_status']];
        }
        $this->json(['success'=>true,'recipients'=>$items,'stages'=>(new \App\Models\SalesModel())->stages($projectId)]);
    }

    public function prepareBroadcast(): void
    {
        $this->requireAuth();$this->validateCsrf();
        $projectId=filter_var($_POST['project_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $templateId=filter_var($_POST['template_id'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        $token=$_POST['token'] ?? '';$selected=null;
        if(isset($_POST['recipient_ids_json'])){
            $encoded=$_POST['recipient_ids_json'];$count=filter_var($_POST['selection_count'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>5000]]);
            try{$selected=is_string($encoded) && strlen($encoded)<=80000?json_decode($encoded,true,16,JSON_THROW_ON_ERROR):null;}
            catch(\JsonException $e){$selected=null;}
            if(!is_array($selected) || !array_is_list($selected) || $count===false || count($selected)!==$count)$this->json(['success'=>false,'message'=>'Daftar penerima tidak lengkap. Muat ulang pilihan.'],422);
        }
        if(!$projectId || $templateId===false || !is_string($token) || !preg_match('/^[a-f0-9]{32}$/D',$token) || !is_array($selected))
            $this->json(['success'=>false,'message'=>'Pilih penerima dan isi pesan broadcast.'],422);
        $project=$this->projectModel->getProjectById($projectId);
        if(!$project || (int)$project['workspace_id']!==1)$this->json(['success'=>false,'message'=>'Proyek tidak ditemukan.'],404);
        try{
            $encodedStages=$_POST['stage_filters_json'] ?? '[]';
            if(!is_string($encodedStages) || strlen($encodedStages)>1000)throw new \InvalidArgumentException('Filter tahap pipeline tidak valid.');
            try{$stageFilters=json_decode($encodedStages,false,8,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new \InvalidArgumentException('Filter tahap pipeline tidak valid.');}
            if(!is_array($stageFilters))throw new \InvalidArgumentException('Filter tahap pipeline tidak valid.');
            $stageFilters=\App\Models\BroadcastModel::stageFilters($stageFilters);
            $body=$_POST['body'] ?? '';if(!is_string($body))throw new \InvalidArgumentException('Pesan tidak valid.');
            $body=\App\Models\MessageTemplateModel::validateBody($body);
            $template=['id'=>0,'name'=>'Pesan bebas','body'=>$body];
            if($templateId>0){
                $templates=array_column((new \App\Models\MessageTemplateModel())->all(),null,'id');
                if(!isset($templates[$templateId]))throw new \InvalidArgumentException('Template tidak ditemukan.');
                $template=['id'=>$templateId,'name'=>$templates[$templateId]['name'],'body'=>$body];
            }
            (new \App\Models\GatewayModel())->credentials();
            $model=new \App\Models\BroadcastModel();
            $batch=$model->prepare($token,$projectId,$template,($_POST['mark_contacted'] ?? '')==='1',$selected,$stageFilters);
            $progress=$model->progress($batch);
            // Saving is opt-in and only after the entire selection has been validated.
            if($templateId>0 && ($_POST['save_template'] ?? '')==='1')(new \App\Models\MessageTemplateModel())->save($templateId,$template['name'],$body);
        }catch(\InvalidArgumentException $e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}
        catch(\Throwable $e){$this->json(['success'=>false,'message'=>'Broadcast belum dapat disiapkan. Periksa pengaturan gateway.'],422);}
        unset($progress['next']);$this->json(['success'=>true,'broadcast'=>$progress]);
    }

    public function broadcastStep(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $token = $_POST['token'] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) $this->json(['success' => false, 'message' => 'Broadcast tidak valid.'], 422);
        $model = new \App\Models\BroadcastModel();
        $batch = $model->find($token);
        if (!$batch || (int)(($this->projectModel->getProjectById((int)$batch['project_id']))['workspace_id'] ?? 0)!==1) $this->json(['success' => false, 'message' => 'Broadcast tidak ditemukan.'], 404);
        $progress = $model->progress($batch);
        if (($_POST['send'] ?? '0') === '1' && $progress['next']) {
            $recipient = $progress['next'];
            $gateway = new \App\Models\GatewayModel();
            try { $credentials = $gateway->credentials(); }
            catch (\Throwable $e) { $this->json(['success' => false, 'message' => 'Gateway tidak aktif atau belum siap. Broadcast dijeda.'], 422); }
            $lead = $this->crmModel->getLeadById($recipient['id']);
            $valid = $lead && (int)($lead['workspace_id'] ?? 0)===1 && (int)$lead['in_crm'] && (int)$lead['project_id'] === (int)$batch['project_id'] && !\App\Models\ContactPolicy::blocked($lead);
            $valid = $valid && (!$batch['stage_filters'] || in_array($lead['lead_status'],$batch['stage_filters'],true));
            try { $valid = $valid && \App\Models\GatewayModel::phone($lead['whatsapp'] ?: ($lead['phone'] ?? '')) === $recipient['number']; }
            catch (\InvalidArgumentException $e) { $valid = false; }
            $reservation = $gateway->reserve($recipient['token'], $recipient['id'], (int)$batch['template_id'], $recipient['number'], $recipient['message']);
            if ($reservation['created']) {
                try {
                    $result = $valid ? $this->gatewayService($credentials['provider'])->send($credentials, $recipient['number'], $recipient['message']) : ['status' => 'skipped', 'http_code' => 0];
                    $gateway->finish((int)$reservation['delivery']['id'], $result['status'], $result['http_code']);
                    // Only a message the gateway accepted moves the lead; failed or uncertain sends stay "new".
                    if ($result['status'] === 'accepted' && !empty($batch['mark_contacted'])) {
                        try { $this->crmModel->markContacted($recipient['id'], 'Dihubungi lewat broadcast WhatsApp (' . $batch['template_name'] . ').'); }
                        catch (\Throwable $e) { error_log('Broadcast mark contacted: ' . $e->getMessage()); }
                    }
                } catch (\Throwable $e) {
                    try { $gateway->finish((int)$reservation['delivery']['id'], 'unknown', 0); } catch (\Throwable $ignored) {}
                }
            }
            $progress = $model->progress($batch);
        }
        unset($progress['next']);
        $this->json(['success' => true, 'broadcast' => $progress]);
    }

    public function syncOpeningHours(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $lead = $this->crmLead($id);
        // Release the session while the browser reads Maps so other tabs remain usable.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        try {
            $hours = (new \App\Services\ScraperService())->fetchOpeningHours($lead['gmap_url'] ?? '');
        } catch (\Throwable $e) {
            error_log('Opening hours sync: ' . $e->getMessage());
            $hours = null;
        }
        if (!$hours) {
            $this->json(['success' => false, 'message' => 'Jadwal belum berhasil dibaca dari Google Maps. Coba lagi. Data sebelumnya tetap tersimpan.'], 422);
        }
        $this->crmLead($id);
        $this->crmModel->saveOpeningHours($id, $hours);
        $this->json(['success' => true, 'opening_hours' => $hours]);
    }

    public function updateStatus(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!is_string($status) || !isset(CrmModel::STAGES[$status])) {
            $this->json(['success' => false, 'message' => 'Pilih status prospek yang valid.'], 422);
        }
        $notes = $_POST['notes'] ?? null;
        if ($notes !== null && (!is_string($notes) || mb_strlen($notes) > 10000)) {
            $this->json(['success' => false, 'message' => 'Catatan maksimal 10.000 karakter.'], 422);
        }
        $dealValue = array_key_exists('deal_value', $_POST) ? filter_var($_POST['deal_value'], FILTER_VALIDATE_FLOAT) : null;
        if ($dealValue === false || ($dealValue !== null && (!is_finite($dealValue) || $dealValue < 0 || $dealValue > 9999999999999.99))) {
            $this->json(['success' => false, 'message' => 'Nilai deal tidak valid.'], 422);
        }
        $this->crmLead($id);
        if (!$this->crmModel->updateLead($id, $status, $notes, $dealValue)) {
            $this->json(['success' => false, 'message' => 'Prospek tidak lagi tersedia di CRM.'], 404);
        }
        $this->json(['success' => true, 'message' => 'Perubahan prospek disimpan.']);
    }

    /** A link click is an activity, not proof that a message was sent. */
    public function logContact(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $lead = $this->crmLead($id);
        if (empty($lead['phone']) && empty($lead['whatsapp'])) {
            $this->json(['success' => false, 'message' => 'Nomor kontak belum tersedia.'], 422);
        }
        if (!$this->crmModel->logWhatsAppContact($id)) {
            $this->json(['success' => false, 'message' => 'Prospek tidak lagi tersedia di CRM.'], 404);
        }
        $this->json(['success' => true, 'message' => 'Tautan WhatsApp dibuka.']);
    }

    public function addNote(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $note = $_POST['note'] ?? '';
        if (!is_string($note) || trim($note) === '' || mb_strlen($note) > 10000) {
            $this->json(['success' => false, 'message' => 'Isi catatan maksimal 10.000 karakter.'], 422);
        }
        $this->crmLead($id);
        if (!$this->crmModel->addNote($id, $note)) {
            $this->json(['success' => false, 'message' => 'Prospek tidak lagi tersedia di CRM.'], 404);
        }
        $this->json(['success' => true, 'history' => $this->crmModel->getFunnelHistory($id)]);
    }

    public function removeProjectFromCrm(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $id = (int)($_POST['project_id'] ?? 0);
        if ($id <= 0 || !$this->projectModel->getProjectById($id)) {
            $this->json(['success' => false, 'message' => 'Proyek tidak ditemukan.'], 404);
        }
        try { $removed = $this->crmModel->removeProjectFromCrm($id); }
        catch (\DomainException $e) { $this->json(['success'=>false,'message'=>$e->getMessage()],409); }
        $this->json(['success' => true, 'removed' => $removed]);
    }

    public function removeFromCrm(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $this->crmLead($id);
        try { $removed=$this->crmModel->removeFromCrm($id); }
        catch (\DomainException $e) { $this->json(['success'=>false,'message'=>$e->getMessage()],409); }
        if (!$removed) {
            $this->json(['success' => false, 'message' => 'Prospek tidak lagi tersedia di CRM.'], 404);
        }
        $this->json(['success' => true, 'message' => 'Prospek beserta catatan, riwayat, dan lampirannya dihapus permanen.']);
    }
}
