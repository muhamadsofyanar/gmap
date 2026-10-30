<?php
/**
 * Base Controller
 * Gmap Prospek MVC Architecture
 */

namespace App\Core;

class Controller
{
    /**
     * Render view template with data
     * 
     * @param string $view e.g. 'prospect/index' or 'auth/login'
     * @param array $data Data passed to view
     * @param bool $useLayout Whether to wrap inside layouts/header and footer
     */
    protected function view(string $view, array $data = [], bool $useLayout = true): void
    {
        // Extract variables for view access
        extract($data);

        $viewFile = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            die("View file [{$viewFile}] tidak ditemukan.");
        }

        if ($useLayout) {
            $headerFile = APP_PATH . '/Views/layouts/header.php';
            $footerFile = APP_PATH . '/Views/layouts/footer.php';

            if (file_exists($headerFile)) {
                require $headerFile;
            }
            require $viewFile;
            if (file_exists($footerFile)) {
                require $footerFile;
            }
        } else {
            require $viewFile;
        }
    }

    /**
     * Send JSON response
     * 
     * @param mixed $data
     * @param int $statusCode
     */
    protected function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirect to another URL or route
     * 
     * @param string $path
     */
    protected function redirect(string $path): void
    {
        $target = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
            ? $path
            : base_url($path);

        header("Location: {$target}");
        exit;
    }

    /**
     * Guard route for authenticated Admin only (PRD Section 1 & 6)
     */
    protected function requireAuth(): void
    {
        if (!is_authenticated()) {
            set_flash('warning', 'Sesi login telah berakhir atau Anda belum login. Silakan masuk.');
            $this->redirect('login');
        }
    }

    /**
     * Validate CSRF token from POST request
     * 
     * @return bool
     */
    protected function validateCsrf(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $this->json(['success' => false, 'message' => 'Gunakan metode POST untuk aksi ini.'], 405);
        }
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!is_string($token) || !verify_csrf_token($token)) {
            if ($this->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => 'Token CSRF tidak valid atau sudah kadaluarsa. Silakan refresh halaman.'
                ], 403);
            }
            set_flash('error', 'Token keamanan CSRF tidak valid. Silakan coba kembali.');
            $this->redirect('');
            return false;
        }
        return true;
    }

    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    protected function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));
    }
}
