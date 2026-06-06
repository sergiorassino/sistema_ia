<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('push:vapid {--write : Escribe VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY en .env}', function () {
    $keys = VAPID::createVapidKeys();

    $public = (string) ($keys['publicKey'] ?? '');
    $private = (string) ($keys['privateKey'] ?? '');

    if ($public === '' || $private === '') {
        $this->error('No se pudieron generar las claves VAPID.');
        return 1;
    }

    $this->info('VAPID keys generadas.');
    $this->line('VAPID_PUBLIC_KEY=' . $public);
    $this->line('VAPID_PRIVATE_KEY=' . $private);

    if (! $this->option('write')) {
        $this->line('');
        $this->comment('Para escribirlas automáticamente en el .env: php artisan push:vapid --write');
        return 0;
    }

    $envPath = base_path('.env');
    if (! is_file($envPath)) {
        $this->error('No existe el archivo .env en ' . $envPath);
        return 1;
    }

    $env = (string) file_get_contents($envPath);
    $env = preg_replace('/^VAPID_PUBLIC_KEY=.*$/m', 'VAPID_PUBLIC_KEY=' . $public, $env, -1, $countPub);
    if (! $countPub) {
        $env = rtrim($env) . PHP_EOL . 'VAPID_PUBLIC_KEY=' . $public . PHP_EOL;
    }

    $env = preg_replace('/^VAPID_PRIVATE_KEY=.*$/m', 'VAPID_PRIVATE_KEY=' . $private, $env, -1, $countPriv);
    if (! $countPriv) {
        $env = rtrim($env) . PHP_EOL . 'VAPID_PRIVATE_KEY=' . $private . PHP_EOL;
    }

    file_put_contents($envPath, $env);
    $this->info('Escrito en .env. Ejecutá: php artisan config:clear');

    return 0;
})->purpose('Genera claves VAPID para notificaciones push');
