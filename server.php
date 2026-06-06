<?php

/**
 * Router para `php artisan serve`.
 * /favicon.ico debe pasar por Laravel (1.png / 2.png según tema del navegador).
 */
$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri === '/favicon.ico') {
    require_once $publicPath.'/index.php';

    return true;
}

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
