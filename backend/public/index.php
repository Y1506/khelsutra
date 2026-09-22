<?php

define('LARAVEL_START', microtime(true));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/../app/Helpers/ApiResponse.php';

// Helper env function
if (!function_exists('env')) {
    /**
     * Get an environment variable value.
     *
     * @param string $key The environment variable key
     * @param mixed $default The default value if key not found
     * @return mixed The environment variable value or default
     */
    function env($key, $default = null) {
        static $envCache = null;
        if ($envCache === null) {
            $envCache = [];
            $envFile = __DIR__ . '/../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
                    list($name, $value) = explode('=', $line, 2);
                    $envCache[trim($name)] = trim($value);
                }
            }
        }
        return $envCache[$key] ?? getenv($key) ?: $default;
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Handle CORS Pre-flight
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Organization-ID');
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Web Auth Action Handlers
if ($uri === '/login' && $method === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $authService = new \App\Services\Auth\AuthService();
    $auth = $authService->login($email, $password);
    if ($auth) {
        $_SESSION['auth'] = $auth;
        $_SESSION['role_slug'] = $auth['role']['slug'] ?? 'sports_admin';
        header('Location: /dashboard');
        exit;
    } else {
        header('Location: /login?error=' . urlencode('Invalid email or password.') . '&email=' . urlencode($email));
        exit;
    }
}

if ($uri === '/logout') {
    if (isset($_SESSION['auth']['user']['id'])) {
        (new \App\Services\Auth\AuthService())->logout((int)$_SESSION['auth']['user']['id'], (int)($_SESSION['auth']['organization']['id'] ?? 1));
    }
    unset($_SESSION['auth'], $_SESSION['role_slug']);
    session_destroy();
    header('Location: /login');
    exit;
}

// 2. API Route Handler
if (str_starts_with($uri, '/api/')) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Organization-ID');
    
    // Parse JSON or Form payload
    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput, true) ?? [];
    $requestData = array_merge($_GET, $_POST, $body);
    $requestData['headers'] = array_change_key_case(getallheaders() ?: [], CASE_LOWER);

    try {
        $apiRouter = require __DIR__ . '/../routes/api.php';
        $response = $apiRouter($uri, $method, $requestData);
        \App\Helpers\ApiResponse::send($response);
    } catch (\Throwable $e) {
        $error = \App\Exceptions\Handler::render($e);
        \App\Helpers\ApiResponse::send($error);
    }
    exit;
}

// 3. Web RBAC Protection Guards
if (str_starts_with($uri, '/super-admin/')) {
    $currentRoleSlug = $_SESSION['auth']['role']['slug'] ?? ($_SESSION['role_slug'] ?? 'sports_admin');
    $currentRoleId = (int)($_SESSION['auth']['role']['id'] ?? 2);
    if ($currentRoleId !== 1 && $currentRoleSlug !== 'super_admin') {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 Forbidden — KhelSutra Platform</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
            <link rel="stylesheet" href="/assets/css/khelsutra-design-system.css">
            <style>
                body {
                    font-family: 'Inter', sans-serif;
                    background: var(--ks-page-bg, #F8FAFC);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 24px;
                    color: var(--ks-text, #1E293B);
                }
                .ks-error-box {
                    max-width: 480px;
                    text-align: center;
                    background: #fff;
                    padding: 40px 32px;
                    border-radius: var(--ks-radius-modal, 16px);
                    border: 1px solid var(--ks-border, #E2E8F0);
                    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
                }
            </style>
        </head>
        <body>
            <div class="ks-error-box">
                <div style="font-size: 56px; font-weight: 800; color: #DC2626; line-height: 1;">403</div>
                <h3 class="fw-bold mt-3 mb-2" style="color: #0B192C;">Access Forbidden</h3>
                <p class="text-muted small mb-4">You do not have the required permissions to access this platform administration area. Your current authenticated role is strictly isolated to your organisation context.</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="/dashboard" class="btn btn-primary px-4 py-2" style="border-radius: 8px; font-weight: 600; font-size: 13px;">Return to Dashboard</a>
                    <a href="/logout" class="btn btn-outline-secondary px-3 py-2" style="border-radius: 8px; font-size: 13px;">Sign Out</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// 2. Web UI Route Handler
$webRoutes = require __DIR__ . '/../routes/web.php';
$viewTarget = $webRoutes[$uri] ?? null;
$routeParams = [];

if (!$viewTarget) {
    foreach ($webRoutes as $pattern => $handler) {
        if (str_contains($pattern, '{')) {
            $regex = '#^' . preg_replace('#\{[a-zA-Z0-9_]+\}#', '([a-zA-Z0-9_-]+)', $pattern) . '$#';
            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                $routeParams = $matches;
                $viewTarget = $handler;
                break;
            }
        }
    }
}
$viewTarget = $viewTarget ?? $webRoutes['/'] ?? null;

if ($viewTarget && is_callable($viewTarget)) {
    $result = $viewTarget(...$routeParams);
    if (!empty($result['view'])) {
        $viewPath = __DIR__ . '/../resources/views/' . $result['view'] . '.blade.php';
        if (file_exists($viewPath)) {
            extract($result['data'] ?? []);
            include $viewPath;
            exit;
        }
    }
}

// Fallback to Dashboard
$dashboardView = __DIR__ . '/../resources/views/dashboard/index.blade.php';
if (file_exists($dashboardView)) {
    include $dashboardView;
    exit;
}

echo "<h1>KhelSutra Sports Management Platform</h1><p>API is active at /api/v1/health</p>";
