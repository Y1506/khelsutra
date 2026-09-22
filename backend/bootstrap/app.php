<?php

// KhelSutra Application Bootstrap

$basePath = dirname(__DIR__);

// Global env helper
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
            $envFile = dirname(__DIR__) . '/.env';
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

// Global storage_path helper
if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage directory.
     *
     * @param string $path Optional path to append
     * @return string The full storage path
     */
    function storage_path($path = '') {
        return dirname(__DIR__) . '/storage/' . ltrim($path, '/');
    }
}

// Global base_path helper
if (!function_exists('base_path')) {
    /**
     * Get the path to the application base directory.
     *
     * @param string $path Optional path to append
     * @return string The full base path
     */
    function base_path($path = '') {
        return dirname(__DIR__) . '/' . ltrim($path, '/');
    }
}

// Autoloader for App namespace
require_once __DIR__ . "/database.php";

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = dirname(__DIR__) . '/app/';
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

// Load configuration files
$config = [];
foreach (glob($basePath . '/config/*.php') as $configFile) {
    $key = basename($configFile, '.php');
    $config[$key] = require $configFile;
}

return [
    'base_path' => $basePath,
    'config' => $config,
    'app_name' => 'KhelSutra'
];
