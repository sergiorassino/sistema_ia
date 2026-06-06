<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Subdirectorio sin /public en la URL: alinear REQUEST_URI con APP_URL (Apache deja SCRIPT_NAME en public/).
if (is_file($envFile = dirname(__DIR__).'/.env')) {
    \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}
$appBasePath = rtrim((string) parse_url($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '', PHP_URL_PATH), '/');
if ($appBasePath !== '') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    if (str_starts_with($path, $appBasePath)) {
        $pathInfo = substr($path, strlen($appBasePath)) ?: '/';
        if (str_starts_with($pathInfo, '/public/')) {
            $pathInfo = substr($pathInfo, 7) ?: '/';
        } elseif ($pathInfo === '/public') {
            $pathInfo = '/';
        }
        $query = parse_url($requestUri, PHP_URL_QUERY);
        $_SERVER['REQUEST_URI'] = $pathInfo.($query !== null && $query !== '' ? '?'.$query : '');
    }
    $_SERVER['SCRIPT_NAME'] = $appBasePath.'/index.php';
}

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
