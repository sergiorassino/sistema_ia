<?php

namespace App\Support\Mail;

use App\Models\Ento;
use App\Support\Database\PersistenciaColumnas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales del correo institucional (Gmail / Google Workspace) por nivel.
 *
 * Única fuente: `ento.ctaEnvioMail` y `ento.passEnvioMail` de ese idNivel.
 * Nombre visible: `ento.insti`; si está vacío, la propia cuenta.
 *
 * No hay fallback a MAIL_* del .env ni a archivos. Sin cuenta del nivel, no se envía:
 * usar otra cuenta mandaría correo a nombre de otro colegio.
 *
 * En APP_ENV=local no fuerza SMTP (MailDesarrollo): si hay cuenta, el envío queda en el log.
 */
final class MailInstitucionalConfig
{
    public const MOTIVO_SIN_CUENTA = 'Sin cuenta de correo del nivel (Parámetros → Correo institucional). No se envió.';

    /**
     * @return array{username: string, password: string, from_name: string, fuente: string}
     */
    public static function leer(?int $idNivel = null): array
    {
        $vacio = [
            'username' => '',
            'password' => '',
            'from_name' => '',
            'fuente' => 'ninguna',
        ];

        $idNivel = self::resolverIdNivel($idNivel);
        if ($idNivel < 1 || ! self::columnasEntoDisponibles()) {
            return $vacio;
        }

        $ento = Ento::query()->where('idNivel', $idNivel)->first();
        if ($ento === null) {
            return $vacio;
        }

        $user = trim((string) ($ento->ctaEnvioMail ?? ''));
        $pass = (string) ($ento->passEnvioMail ?? '');

        return [
            'username' => $user,
            'password' => $pass,
            'from_name' => self::nombreRemitenteDesdeEnto($ento, $user),
            'fuente' => 'ento',
        ];
    }

    public static function estaConfigurado(?int $idNivel = null): bool
    {
        return self::credencialesCompletas(self::leer($idNivel));
    }

    /**
     * Usuario y contraseña presentes (apto para SMTP).
     *
     * @param  array{username?: string, password?: string}  $c
     */
    public static function credencialesCompletas(array $c): bool
    {
        return trim((string) ($c['username'] ?? '')) !== '' && trim((string) ($c['password'] ?? '')) !== '';
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

        if ($user === '' || trim($pass) === '') {
            self::anularCredencialesMailerPorDefecto();

            return;
        }

        $fromName = $name !== '' ? $name : $user;

        if (MailDesarrollo::bloquearSmtp()) {
            Config::set([
                'mail.default' => 'log',
                'mail.mailers.smtp.transport' => 'log',
                'mail.mailers.smtp.username' => $user,
                'mail.from.address' => $user,
                'mail.from.name' => $fromName,
            ]);

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
            'mail.from.name' => $fromName,
        ]);
    }

    /**
     * Aplica SMTP del nivel. false si no hay cuenta en ento: no se debe enviar.
     * En ese caso borra usuario/remitente del mailer por defecto para que no quede MAIL_* de otro colegio.
     */
    public static function aplicarParaNivel(?int $idNivel = null): bool
    {
        $c = self::leer($idNivel);
        if (! self::credencialesCompletas($c)) {
            self::anularCredencialesMailerPorDefecto();

            return false;
        }

        self::aplicar($c, $idNivel);

        return true;
    }

    /**
     * Diagnóstico para la UI. Vacío si el nivel no tiene cuenta propia
     * (no informa MAIL_USERNAME del .env).
     *
     * @return array{mailer: string, username: string}
     */
    public static function diagnosticoEnvio(?int $idNivel = null): array
    {
        if (! self::estaConfigurado($idNivel)) {
            return ['mailer' => '', 'username' => ''];
        }

        return [
            'mailer' => (string) config('mail.default'),
            'username' => trim((string) (self::leer($idNivel)['username'] ?? '')),
        ];
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

    private static function nombreRemitenteDesdeEnto(Ento $ento, string $cuenta = ''): string
    {
        $insti = trim((string) ($ento->insti ?? ''));
        if ($insti !== '') {
            return $insti;
        }

        return trim($cuenta);
    }

    private static function anularCredencialesMailerPorDefecto(): void
    {
        Config::set([
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => null,
            'mail.from.name' => null,
        ]);
    }
}
