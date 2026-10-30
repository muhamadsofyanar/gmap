<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Services\RateLimit;

class ProfileController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $account = (new User())->profile((int)$_SESSION['user_id']);
        if (!$account) $this->redirect('login');
        $_SESSION['user_name'] = $account['name'];
        $_SESSION['user_email'] = $account['email'];
        $draft = $_SESSION['profile_draft'] ?? $account;
        unset($_SESSION['profile_draft']);
        $this->view('profile/index', [
            'pageTitle' => 'Profil - Gmap Prospek', 'activeSection' => 'profile',
            'account' => $account, 'draft' => $draft,
        ]);
    }

    public function save(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        $name = is_string($_POST['name'] ?? null) ? $_POST['name'] : '';
        $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        try {
            if (!RateLimit::allow('profile-' . (int)$_SESSION['user_id'], 20, 900)) throw new \InvalidArgumentException('Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
            (new User())->updateProfile((int)$_SESSION['user_id'], $name, $email, $current);
            $_SESSION['user_name'] = trim($name);
            $_SESSION['user_email'] = trim($email);
            unset($_SESSION['profile_draft']);
            session_regenerate_id(true);
            set_flash('success', 'Profil berhasil diperbarui.');
        } catch (\InvalidArgumentException $e) {
            $_SESSION['profile_draft'] = ['name' => $name, 'email' => $email];
            set_flash('error', $e->getMessage());
        }
        $this->redirect('profile');
    }

    public function password(): void
    {
        $this->requireAuth();
        $this->validateCsrf();
        try {
            if (!RateLimit::allow('password-' . (int)$_SESSION['user_id'], 10, 900)) throw new \InvalidArgumentException('Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
            $values = [];
            foreach (['current_password', 'password', 'password_confirmation'] as $key) $values[] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
            $_SESSION['session_version'] = (new User())->changePassword((int)$_SESSION['user_id'], ...$values);
            session_regenerate_id(true);
            set_flash('success', 'Password berhasil diubah. Gunakan password baru saat masuk berikutnya.');
        } catch (\InvalidArgumentException $e) {
            set_flash('error', $e->getMessage());
        }
        $this->redirect('profile#ubah-password');
    }
}
