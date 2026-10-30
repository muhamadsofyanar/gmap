<?php
/**
 * Application Router & Front-Controller Dispatcher
 * Gmap Prospek MVC Architecture
 */

namespace App\Core;

class App
{
    protected string $controller = 'DashboardController';
    protected string $method = 'index';
    protected array $params = [];

    // Named routing aliases for friendly URLs
    protected array $routeAliases = [
        'team'         => ['controller' => 'TeamController', 'method' => 'index'],
        'login'        => ['controller' => 'AuthController', 'method' => 'login'],
        'logout'       => ['controller' => 'AuthController', 'method' => 'logout'],
        'dashboard'    => ['controller' => 'DashboardController', 'method' => 'index'],
        'projects'     => ['controller' => 'ProjectController', 'method' => 'index'],
        'project'      => ['controller' => 'ProjectController', 'method' => 'index'],
        'crm'          => ['controller' => 'CrmController', 'method' => 'index'],
        'templates'    => ['controller' => 'TemplateController', 'method' => 'index'],
        'settings'     => ['controller' => 'SettingsController', 'method' => 'index'],
        'prospect'     => ['controller' => 'ProspectController', 'method' => 'index'],
        'prospek'      => ['controller' => 'DashboardController', 'method' => 'index'],
        'search'       => ['controller' => 'ProjectController', 'method' => 'create'],
        'export'       => ['controller' => 'ProspectController', 'method' => 'export'],
        'delete'       => ['controller' => 'ProspectController', 'method' => 'delete'],
        'clear-cache'  => ['controller' => 'ProspectController', 'method' => 'clearCache'],
    ];

    public function __construct()
    {
        $url = $this->parseUrl();

        // Root URL opens the dashboard (the guard sends signed-out visitors to the login page)
        if (empty($url) || empty($url[0])) {
            $this->controller = 'DashboardController';
            $this->method = 'index';
        } elseif (isset($this->routeAliases[strtolower($url[0])])) {
            $alias = $this->routeAliases[strtolower($url[0])];
            $this->controller = $alias['controller'];
            $this->method = $alias['method'];
            array_shift($url);

            // If additional method passed
            if (!empty($url[0]) && method_exists('\\App\\Controllers\\' . $this->controller, $url[0])) {
                $this->method = $url[0];
                array_shift($url);
            }
            $this->params = $url;
        } else {
            // Standard Controller/Method convention
            $potentialController = ucfirst($url[0]) . 'Controller';
            $controllerFile = APP_PATH . '/Controllers/' . $potentialController . '.php';

            if (file_exists($controllerFile)) {
                $this->controller = $potentialController;
                array_shift($url);

                if (!empty($url[0])) {
                    $potentialMethod = $url[0];
                    $this->method = $potentialMethod;
                    array_shift($url);
                }
                $this->params = $url;
            } else {
                // If controller file not found, try method on ProspectController
                if (method_exists('\\App\\Controllers\\ProspectController', $url[0])) {
                    $this->controller = 'ProspectController';
                    $this->method = $url[0];
                    array_shift($url);
                    $this->params = $url;
                } else {
                    $this->render404("Halaman '{$url[0]}' tidak ditemukan.");
                    return;
                }
            }
        }

        // Fully qualified controller class
        $controllerClass = '\\App\\Controllers\\' . $this->controller;
        $controllerFilePath = APP_PATH . '/Controllers/' . $this->controller . '.php';

        if (!file_exists($controllerFilePath)) {
            $this->render404("Controller '{$this->controller}' tidak ditemukan.");
            return;
        }

        \App\Services\AuthGuard::guard($this->controller);
        $controllerInstance = new $controllerClass();

        // Check if method exists
        if (!method_exists($controllerInstance, $this->method)) {
            $this->render404("Aksi '{$this->method}' pada {$this->controller} tidak ditemukan.");
            return;
        }

        $action = new \ReflectionMethod($controllerInstance, $this->method);
        if (!$action->isPublic() || $action->isStatic() || $action->isConstructor() || $action->getDeclaringClass()->getName() !== ltrim($controllerClass, '\\')) {
            $this->render404();
        }

        // Call controller action with parameters
        call_user_func_array([$controllerInstance, $this->method], $this->params);
    }

    /**
     * Parse requested URL from query string or request URI
     *
     * @return array
     */
    protected function parseUrl(): array
    {
        if (isset($_GET['url'])) {
            $raw = trim($_GET['url'], '/');
            // Remove leading public/ or index.php if passed by rewrite
            $raw = preg_replace('#^(public/|index\.php/?)+#i', '', $raw);
            $raw = filter_var($raw, FILTER_SANITIZE_URL);
            $segments = array_values(array_filter(explode('/', (string)$raw), 'strlen'));

            // If first segment matches project folder name "prospek", shift it
            if (!empty($segments) && strtolower($segments[0]) === 'prospek') {
                array_shift($segments);
            }

            return $segments;
        }

        // Fallback parsing from REQUEST_URI if .htaccess query string is omitted
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');

        // Remove base paths from uri
        if ($scriptDir !== '/' && !empty($scriptDir) && str_starts_with($requestUri, $scriptDir)) {
            $requestUri = substr($requestUri, strlen($scriptDir));
        }

        // Also check stripping /prospek prefix
        $requestUri = preg_replace('#^/prospek(/public)?#i', '', $requestUri);
        $requestUri = preg_replace('#^/index\.php#i', '', $requestUri);
        $requestUri = trim($requestUri, '/');

        if (!empty($requestUri)) {
            $segments = array_values(array_filter(explode('/', $requestUri), 'strlen'));
            return $segments;
        }

        return [];
    }

    /**
     * Render 404 page
     *
     * @param string $message
     */
    protected function render404(string $message = 'Halaman tidak ditemukan'): void
    {
        http_response_code(404);
        $errorFile = APP_PATH . '/Views/errors/404.php';
        if (file_exists($errorFile)) {
            $title = "404 - Halaman Tidak Ditemukan";
            require $errorFile;
        } else {
            echo "<h1>404 Not Found</h1><p>" . e($message) . "</p>";
        }
        exit;
    }
}
