<?php

namespace App\Support\Cooperadora;

use App\Models\CoopIngreso;
use Illuminate\Support\Facades\Log;

/**
 * Envío de recibos por correo al pagador designado.
 *
 * Mientras RECIBO_EMAIL_SIMULADO sea true no se invoca Mail ni SMTP:
 * solo se registra el intento y se actualiza el estado en coop_ingresos.
 */
final class EnvioReciboCooperadora
{
    /** @var bool Cambiar a false cuando se habilite el envío real en producción. */
    public const RECIBO_EMAIL_SIMULADO = true;

    /** @var list<string> */
    public const ESTADOS = ['pendiente', 'simulado', 'enviado', 'error'];

    public static function enviar(int $idReferenciaRecibo, bool $reenvio = false): bool
    {
        $ingresos = ReciboIngresosGrupo::ingresosDelRecibo($idReferenciaRecibo);
        if ($ingresos->isEmpty()) {
            return false;
        }

        $lider = $ingresos->first();
        $email = trim((string) ($lider->pagador_email ?? ''));
        $nombre = trim((string) ($lider->pagador_nombre ?? ''));

        if ($email === '') {
            self::marcarGrupo($ingresos->pluck('id')->all(), 'error', 'Sin email del pagador.');

            return false;
        }

        if (self::RECIBO_EMAIL_SIMULADO) {
            Log::info('Cooperadora recibo email (SIMULADO)', [
                'recibo_numero' => $lider->recibo_numero,
                'id_referencia' => $idReferenciaRecibo,
                'destinatario' => $email,
                'pagador' => $nombre,
                'reenvio' => $reenvio,
            ]);
            self::marcarGrupo($ingresos->pluck('id')->all(), 'simulado');

            return true;
        }

        // Punto de extensión: adjuntar PDF (ReciboTcpdf) y enviar con Mail/MailAdapter.
        Log::warning('Cooperadora recibo email: envío real aún no implementado.', [
            'recibo_numero' => $lider->recibo_numero,
            'destinatario' => $email,
        ]);
        self::marcarGrupo($ingresos->pluck('id')->all(), 'error', 'Envío real pendiente de implementación.');

        return false;
    }

    public static function etiquetaEstado(?string $estado): string
    {
        return match ($estado) {
            'simulado' => 'Simulado',
            'enviado' => 'Enviado',
            'error' => 'Error',
            default => 'Pendiente',
        };
    }

    /**
     * @param  list<int|string>  $idsIngreso
     */
    private static function marcarGrupo(array $idsIngreso, string $estado, ?string $error = null): void
    {
        if (! in_array($estado, self::ESTADOS, true)) {
            $estado = 'pendiente';
        }

        $update = [
            'recibo_email_estado' => $estado,
            'recibo_email_enviado_at' => now(),
        ];
        if ($error !== null) {
            $update['recibo_email_error'] = mb_substr($error, 0, 500);
        } elseif ($estado !== 'error') {
            $update['recibo_email_error'] = null;
        }

        CoopIngreso::query()
            ->whereIn('id', $idsIngreso)
            ->update($update);
    }
}
