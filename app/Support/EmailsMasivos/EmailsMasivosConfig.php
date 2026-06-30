<?php

namespace App\Support\EmailsMasivos;

final class EmailsMasivosConfig
{
    public static function maxDestinatariosPorEnvio(): int
    {
        return max(1, (int) config('emails_masivos.max_destinatarios_por_envio', 500));
    }

    public static function maxDestinatariosAviso(): int
    {
        return max(1, (int) config('emails_masivos.max_destinatarios_aviso', 400));
    }

    public static function mailBccChunk(): int
    {
        return max(1, (int) config('emails_masivos.mail_bcc_chunk', 100));
    }

    public static function adjuntoNombreMaxChars(): int
    {
        return max(1, (int) config('emails_masivos.adjunto_nombre_max_chars', 30));
    }

    public static function attachedFieldMaxChars(): int
    {
        return max(1, (int) config('emails_masivos.attached_field_max_chars', 150));
    }

    public static function adjuntoMaxBytes(): int
    {
        $mb = max(1, (int) config('emails_masivos.adjunto_max_mb', 10));

        return $mb * 1024 * 1024;
    }

    public static function adjuntosMaxCount(): int
    {
        return max(1, (int) config('emails_masivos.adjuntos_max_count', 5));
    }

    public static function simulado(): bool
    {
        if (config('tenant.emails_masivos.simulado') !== null) {
            return (bool) config('tenant.emails_masivos.simulado');
        }

        return (bool) config('emails_masivos.simulado', false);
    }
}
