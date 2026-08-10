<?php

namespace App\Support\Mail;

use App\Models\Ento;
use App\Support\Database\PersistenciaColumnas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales del correo institucional (Gmail / Google Workspace) por nivel.
 *
 * Fuente de verdad: `ento.ctaEnvioMail` y `ento.passEnvioMail` (fila del idNivel).
 * Nombre visible del remitente: `ento.insti` (fallback MAIL_FROM_NAME / cuenta).
 *
 * Compatibilidad: si el nivel no tiene cuenta en ento, se intenta el JSON legacy
 * en storage y luego MAIL_* del .env.
 */
final class MailInstitucionalConfig
{
    public static function path(): string
    {
        return storage_path('app/private/mail-institucional.json');
    }

    /**
     * @return array{username: string, password: string, from_name: string, fuente: string}
     */
    public static function leer(?int $idNivel = null): array
    {
        $idNivel = self::resolverIdNivel($idNivel);

        if ($idNivel > 0 && self::columnasEntoDisponibles()) {
            $ento = Ento::query()->where('idNivel', $idNivel)->first();
            if ($ento !== null) {
                $user = trim((string) ($ento->ctaEnvioMail ?? ''));
                $pass = (string) ($ento->passEnvioMail ?? '');
                if ($user !== '' || trim($pass) !== '') {
                    return [
                        'username' => $user,
                        'password' => $pass,
                        'from_name' => self::nombreRemitenteDesdeEnto($ento),
                        'fuente' => 'ento',
                    ];
                }
            }
        }

        $legacy = self::leerLegacyJsonOEnv();

        return $legacy + ['fuente' => $legacy['fuente'] ?? 'env'];
    }

    public static function estaConfigurado(?int $idNivel = null): bool
    {
        $c = self::leer($idNivel);

        return $c['username'] !== '' && trim($c['password']) !== '';
    }

    /**
     * Persiste cuenta/contraseña en `ento` del nivel y aplica SMTP en runtime.
     * El nombre visible del remitente es siempre `ento.insti`.
     *
     * @throws QueryException
     * @throws \RuntimeException si faltan columnas o el nivel
     */
    public static function guardar(string $username, string $password, ?int $idNivel = null): void
    {
        $idNivel = self::resolverIdNivel($idNivel);
        if ($idNivel < 1) {
            throw new \RuntimeException('Sin nivel activo para guardar el correo institucional.');
        }

        if (! self::columnasEntoDisponibles()) {
            throw new \RuntimeException(
                'Faltan columnas ento.ctaEnvioMail / ento.passEnvioMail. Ejecutá la migración o el SQL idempotente.'
            );
        }

        $user = trim($username);
        $pass = (string) $password;

        $payload = [
            'ctaEnvioMail' => $user !== '' ? $user : null,
            'passEnvioMail' => trim($pass) !== '' ? $pass : null,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('ento', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            throw new \RuntimeException(
                PersistenciaColumnas::mensajeColumnasInexistentes('ento', $preparado['columnas_con_valor_sin_columna'])
            );
        }

        /** @var Ento $ento */
        $ento = Ento::query()->firstOrNew(['idNivel' => $idNivel]);
        if (! $ento->exists) {
            $ento->idNivel = $idNivel;
        }

        try {
            $ento->fill($preparado['payload']);
            $ento->save();
        } catch (QueryException $e) {
            throw new \RuntimeException(
                PersistenciaColumnas::mensajeDesdeQueryException($e) ?? $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        $where = ['idNivel' => $idNivel];
        $esperados = array_filter(
            [
                'ctaEnvioMail' => $payload['ctaEnvioMail'],
                'passEnvioMail' => $payload['passEnvioMail'],
            ],
            static fn ($v) => $v !== null && $v !== ''
        );
        $noOk = PersistenciaColumnas::columnasNoPersistidas('ento', $where, $esperados);
        if ($noOk !== []) {
            throw new \RuntimeException(
                'No se pudo verificar el guardado de: '.implode(', ', $noOk).'.'
            );
        }

        $ento->refresh();

        self::aplicar([
            'username' => $user,
            'password' => $pass,
            'from_name' => self::nombreRemitenteDesdeEnto($ento),
        ]);
    }

    /**
     * Aplica credenciales SMTP del nivel (o del array dado) a la config de Laravel.
     *
     * @param  array{username?: string, password?: string, from_name?: string}|null  $datos
     */
    public static function aplicar(?array $datos = null, ?int $idNivel = null): void
    {
        $c = $datos ?? self::leer($idNivel);
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

    /** Aplica SMTP según el nivel del contexto o el indicado (p. ej. del hilo). */
    public static function aplicarParaNivel(?int $idNivel = null): void
    {
        self::aplicar(null, $idNivel);
    }

    public static function columnasEntoDisponibles(): bool
    {
        return Schema::hasTable('ento')
            && Schema::hasColumn('ento', 'ctaEnvioMail')
            && Schema::hasColumn('ento', 'passEnvioMail');
    }

    private static function resolverIdNivel(?int $idNivel): int
    {
        if ($idNivel !== null && $idNivel > 0) {
            return $idNivel;
        }

        try {
            return (int) (schoolCtx()->idNivel ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function nombreRemitenteDesdeEnto(Ento $ento): string
    {
        $insti = trim((string) ($ento->insti ?? ''));
        if ($insti !== '') {
            return $insti;
        }

        $fromEnv = trim((string) (config('mail.from.name') ?: env('MAIL_FROM_NAME', '')), " \t\n\r\0\x0B\"'");

        return $fromEnv;
    }

    /**
     * @return array{username: string, password: string, from_name: string, fuente: string}
     */
    private static function leerLegacyJsonOEnv(): array
    {
        $path = self::path();
        if (is_file($path)) {
            $raw = File::get($path);
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $user = trim((string) ($data['username'] ?? ''));
                $pass = (string) ($data['password'] ?? '');
                if ($user !== '' || trim($pass) !== '') {
                    return [
                        'username' => $user,
                        'password' => $pass,
                        'from_name' => trim((string) ($data['from_name'] ?? '')),
                        'fuente' => 'json',
                    ];
                }
            }
        }

        return [
            'username' => trim((string) (config('mail.mailers.smtp.username') ?: env('MAIL_USERNAME', ''))),
            'password' => (string) (config('mail.mailers.smtp.password') ?: env('MAIL_PASSWORD', '')),
            'from_name' => trim((string) (config('mail.from.name') ?: env('MAIL_FROM_NAME', '')), " \t\n\r\0\x0B\"'"),
            'fuente' => 'env',
        ];
    }
}
