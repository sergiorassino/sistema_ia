<?php

namespace App\Support\Mail;

use Illuminate\Support\Facades\Config;

/**
 * En APP_ENV=local no se envía SMTP real (comunicados, recuperación, cooperadora, masivos).
 * Los mensajes quedan en storage/logs. Producción no se ve afectada.
 *
 * Escape hatch: MAIL_FORCE_REAL=true en .env (solo para una prueba puntual).
 */
final class MailDesarrollo
{
    public static function bloquearSmtp(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        return ! (bool) config('mail.forzar_smtp_en_local', false);
    }

    /** Transport a usar al armar un mailer SMTP en runtime. */
    public static function transporteSmtp(): string
    {
        return self::bloquearSmtp() ? 'log' : 'smtp';
    }

    /** Redirige el mailer por defecto y todos los transport smtp a log. */
    public static function aplicarMailersLog(): void
    {
        if (! self::bloquearSmtp()) {
            return;
        }

        Config::set('mail.default', 'log');

        foreach (array_keys(config('mail.mailers', [])) as $nombre) {
            if (config("mail.mailers.{$nombre}.transport") === 'smtp') {
                Config::set("mail.mailers.{$nombre}.transport", 'log');
            }
        }
    }
}
