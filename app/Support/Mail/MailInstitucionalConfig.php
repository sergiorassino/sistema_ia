<?php

namespace App\Support\Mail;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

/**
 * Credenciales del correo institucional (Gmail / Google Workspace) del tenant.
 *
 * Se guardan en storage (no en .env) para que el guardado desde Parámetros
 * no dispare reinicio de Vite ni Artisan config:clear (FOUC en local).
 */
final class MailInstitucionalConfig
{
    public static function path(): string
    {
        return storage_path('app/private/mail-institucional.json');
    }

    /**
     * @return array{username: string, password: string, from_name: string}
     */
    public static function leer(): array
    {
        $path = self::path();
        if (is_file($path)) {
            $raw = File::get($path);
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return [
                    'username' => trim((string) ($data['username'] ?? '')),
                    'password' => (string) ($data['password'] ?? ''),
                    'from_name' => trim((string) ($data['from_name'] ?? '')),
                ];
            }
        }

        // Compatibilidad: instalación previa con MAIL_* en .env / config
        return [
            'username' => trim((string) (config('mail.mailers.smtp.username') ?: env('MAIL_USERNAME', ''))),
            'password' => (string) (config('mail.mailers.smtp.password') ?: env('MAIL_PASSWORD', '')),
            'from_name' => trim((string) (config('mail.from.name') ?: env('MAIL_FROM_NAME', '')), " \t\n\r\0\x0B\"'"),
        ];
    }

    public static function estaConfigurado(): bool
    {
        $c = self::leer();

        return $c['username'] !== '' && trim($c['password']) !== '';
    }

    public static function guardar(string $username, string $password, string $fromName): void
    {
        $dir = dirname(self::path());
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $payload = [
            'username' => trim($username),
            'password' => $password,
            'from_name' => trim($fromName),
        ];

        File::put(
            self::path(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );

        self::aplicar($payload);
    }

    /**
     * @param  array{username?: string, password?: string, from_name?: string}|null  $datos
     */
    public static function aplicar(?array $datos = null): void
    {
        $c = $datos ?? self::leer();
        $user = trim((string) ($c['username'] ?? ''));
        $pass = (string) ($c['password'] ?? '');
        $name = trim((string) ($c['from_name'] ?? ''));

        if ($user === '') {
            return;
        }

        Config::set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.encryption' => 'tls',
            'mail.mailers.smtp.username' => $user,
            'mail.mailers.smtp.password' => $pass,
            'mail.from.address' => $user,
            'mail.from.name' => $name !== '' ? $name : $user,
        ]);
    }
}
