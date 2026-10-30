<?php
/**
 * Auth Controller
 * Admin Session Management & Authentication
 * Gmap Prospek
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class AuthController extends Controller
{
    protected User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Display login form or process login submission
     */
    public function login(): void
    {
        $loginPath = 'login';
        // Already signed in: go straight to the dashboard
        if (\App\Services\AuthGuard::identity()) {
            $this->redirect(($_SESSION['role'] ?? '') === 'admin' ? 'dashboard' : 'team');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            if (!\App\Services\RateLimit::allow('login', 20, 900)) {
                set_flash('error', 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.');
                $this->redirect($loginPath);
            }

            $identifier = trim(filter_input(INPUT_POST, 'identifier', FILTER_DEFAULT) ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($identifier) || empty($password) || !is_string($password) || mb_strlen($identifier) > 254 || strlen($password) > 4096) {
                set_flash('error', 'Silakan masukkan username/email dan kata sandi Anda.');
                $this->redirect($loginPath);
            }

            // Per-account limit shared across IPs, so a distributed attack cannot brute-force one login.
            if (!\App\Services\RateLimit::allow('login-account|' . mb_strtolower($identifier), 10, 900, false)) {
                set_flash('error', 'Terlalu banyak percobaan masuk untuk akun ini. Coba lagi dalam 15 menit.');
                $this->redirect($loginPath);
            }

            // Authenticate user credentials
            $user = $this->userModel->authenticate($identifier, $password);

            if ($user) {
                // Initialize admin session
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                session_regenerate_id(true);
                unset($_SESSION['_csrf_token']); // fresh anti-CSRF token for the authenticated session

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['session_version']=(int)$user['session_version'];
                $_SESSION['last_activity'] = time();

                set_flash('success', "Selamat datang kembali, {$user['name']}! Berhasil login ke sistem Gmap Prospek.");
                $this->redirect(($_SESSION['role'] ?? '') === 'admin' ? 'dashboard' : 'team');
            } else {
                set_flash('error', 'Kombinasi username/email atau password salah.');
                $this->redirect($loginPath);
            }
        }

        // Render standalone login page
        $this->view('auth/login', [
            'pageTitle' => 'Masuk - Gmap Prospek'
        ], false);
    }

    /**
     * Logout and destroy session
     */
    public function logout(): void
    {
        $this->validateCsrf();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Unset all session variables
        $_SESSION = [];

        // Destroy session cookie if set
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        set_flash('info', 'Anda telah berhasil keluar (logout) dari sistem Gmap Prospek.');
        $this->redirect('login');
    }
}
