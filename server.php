<?php

/**
 * Router para `php artisan serve`.
 * favicon.ico: servir el ICO estático (regenerado desde favicon-32.png) si existe.
 */
$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri === '/favicon.ico' && is_file($publicPath.'/favicon.ico')) {
    return false;
}

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
