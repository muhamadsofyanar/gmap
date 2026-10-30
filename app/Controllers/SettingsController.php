<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\GatewayModel;

class SettingsController extends Controller
{
    public function funnel(): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $projects = (new \App\Models\ProjectModel())->getAllProjects();
        $selected = filter_var($_GET['project_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $projectMap = array_column($projects, null, 'id');
        if ($selected === false || ($selected > 0 && !isset($projectMap[$selected]))) {
            set_flash('error', 'Proyek tidak ditemukan. Pilih proyek dari workspace Anda.');
            $this->redirect('settings/funnel');
            return;
        }
        if (!$selected && $projects) $selected = (int)$projects[0]['id'];
        $this->view('settings/funnel', [
            'pageTitle' => 'Sales Funnel - Pengaturan Gmap Prospek',
            'projects' => $projects,
            'selectedProject' => $selected,
            'stages' => $selected ? (new \App\Models\SalesModel())->stages($selected) : []
        ]);
    }

    public function index(): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $model = new GatewayModel();
        try { $gateway = $model->editableSettings(); }
        catch (\Throwable $e) {
            $gateway = $model->settings() + ['api_key' => ''];
            set_flash('error', 'API key tersimpan belum dapat dibaca. Periksa kunci enkripsi atau isi ulang API key.');
        }
        $this->view('settings/index', ['pageTitle' => 'Pengaturan - Gmap Prospek', 'gateway' => $gateway]);
    }

    public function saveGateway(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        header('Cache-Control: no-store');
        $provider = $_POST['provider'] ?? 'xsender';
        $key = $_POST['api_key'] ?? '';
        $sender = $_POST['sender'] ?? '';
        $enabled = $_POST['enabled'] ?? '0';
        if (!is_string($provider) || !isset(GatewayModel::PROVIDERS[$provider]) || !is_string($key) || !is_string($sender) || !in_array($enabled, ['0', '1'], true)) {
            $this->saveGatewayResponse(false, 'Pengaturan gateway tidak valid.', 422);
            return;
        }
        try {
            $model = new GatewayModel();
            $model->save($provider, $key, $sender, $enabled === '1');
            $gateway = $model->editableSettings();
        } catch (\InvalidArgumentException $e) {
            $this->saveGatewayResponse(false, $e->getMessage(), 422);
            return;
        } catch (\Throwable $e) {
            $this->saveGatewayResponse(false, 'Pengaturan belum berhasil disimpan. Periksa konfigurasi enkripsi gateway.', 500);
            return;
        }
        $this->saveGatewayResponse(true, 'Pengaturan WhatsApp Gateway tersimpan.', 200, $gateway);
    }

    private function saveGatewayResponse(bool $success, string $message, int $statusCode = 200, ?array $gateway = null): void
    {
        if ($this->isAjax()) {
            $data = ['success' => $success, 'message' => $message];
            if ($gateway !== null) $data['gateway'] = $gateway;
            $this->json($data, $statusCode);
            return;
        }
        set_flash($success ? 'success' : 'error', $message);
        $this->redirect('settings');
    }

    protected function gatewayService(string $provider): object
    {
        return GatewayModel::service($provider);
    }

    public function testGateway(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $number = $_POST['number'] ?? null;
        $message = $_POST['message'] ?? null;
        $token = $_POST['request_token'] ?? null;
        if (!is_string($number) || !is_string($message) || trim($message) === '' || mb_strlen($message) > 1000 || !is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
            $this->json(['success' => false, 'message' => 'Isi nomor tujuan dan pesan tes (maksimal 1.000 karakter).'], 422);
        }
        try {
            $number = GatewayModel::phone($number);
            $credentials = (new GatewayModel())->credentials();
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Konfigurasi gateway belum dapat dibaca. Simpan ulang pengaturan gateway.'], 500);
        }
        $message = trim($message);
        $fingerprint = hash('sha256', $number . "\n" . $message);
        $previous = $_SESSION['gateway_tests'][$token] ?? null;
        if ($previous && $previous['fingerprint'] !== $fingerprint) {
            $this->json(['success' => false, 'message' => 'Permintaan tes berubah. Muat ulang halaman untuk tes baru.'], 409);
        }
        if (!$previous) {
            // Keep the session lock: duplicate requests wait and reuse the stored outcome.
            $_SESSION['gateway_tests'][$token] = ['fingerprint' => $fingerprint, 'status' => 'unknown', 'http_code' => 0];
            try {
                $result = $this->gatewayService($credentials['provider'])->send($credentials, $number, $message);
                $_SESSION['gateway_tests'][$token] = ['fingerprint' => $fingerprint] + $result;
            } catch (\Throwable $e) {
                // A timeout is ambiguous. Never automatically retry or expose raw provider errors.
            }
        }
        $result = $_SESSION['gateway_tests'][$token];
        $label = GatewayModel::label($credentials['provider']);
        $messages = [
            'accepted' => 'Tes diterima gateway. Periksa WhatsApp nomor tujuan untuk memastikan pesan benar-benar sampai.',
            'failed' => 'Tes ditolak gateway. Periksa API key, nomor pengirim, nomor tujuan, dan koneksi perangkat di ' . $label . '.',
            'unknown' => 'Hasil tes belum dapat dipastikan. Periksa WhatsApp tujuan dan riwayat ' . $label . ' sebelum mencoba lagi.'
        ];
        $this->json(['success' => true, 'delivery_status' => $result['status'], 'http_code' => $result['http_code'], 'message' => $result['diagnostic'] ?? ($messages[$result['status']] ?? $messages['unknown']), 'next_token' => bin2hex(random_bytes(16))]);
    }
}
