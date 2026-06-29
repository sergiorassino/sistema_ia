<?php

namespace App\Support\Cooperadora;

use App\Mail\ReciboCooperadoraMail;
use App\Models\CoopIngreso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envío de recibos por correo al pagador designado.
 *
 * Usa el mailer `cooperadora` (COOP_MAIL_* en .env), distinto del cuaderno de comunicados.
 * Modo simulado y flags por colegio: `config('tenant.cooperadora.recibo_email')`.
 */
final class EnvioReciboCooperadora
{
    /** @var list<string> */
    public const ESTADOS = ['pendiente', 'simulado', 'enviado', 'error'];

    public static function modoSimulado(): bool
    {
        return tenantCooperadoraReciboEmailSimulado();
    }

    public static function enviar(int $idReferenciaRecibo, bool $reenvio = false): bool
    {
        if (! tenantCooperadoraReciboEmailHabilitado()) {
            return false;
        }

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

        if (self::modoSimulado()) {
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

        $from = tenantCooperadoraReciboEmailFrom();
        if ($from === null) {
            self::marcarGrupo(
                $ingresos->pluck('id')->all(),
                'error',
                'Correo cooperadora no configurado (COOP_MAIL_* en .env).'
            );

            return false;
        }

        try {
            ['binario' => $pdfBinario, 'nombre_archivo' => $nombreArchivo] = self::pdfRecibo($ingresos, $lider);
            $numeroTexto = NumeroDocumentoCooperadora::formatearRecibo((int) $lider->recibo_numero);
            $institucion = trim((string) (CooperadoraConfig::datosPdfHeader()['nombre'] ?? ''));
            if ($institucion === '') {
                $institucion = trim((string) config('tenant.nombre', 'Cooperadora'));
            }

            $mailer = tenantCooperadoraReciboEmailMailer();
            Mail::mailer($mailer)->to($email)->send(new ReciboCooperadoraMail(
                nombrePagador: $nombre !== '' ? $nombre : 'Familia',
                numeroReciboTexto: $numeroTexto,
                fechaTexto: $lider->fecha->format('d/m/Y'),
                nombreInstitucion: $institucion,
                pdfBinario: $pdfBinario,
                nombreArchivoPdf: $nombreArchivo,
                asunto: tenantCooperadoraReciboEmailAsunto($numeroTexto),
                fromAddress: $from['address'],
                fromName: $from['name'],
            ));

            Log::info('Cooperadora recibo email enviado', [
                'recibo_numero' => $lider->recibo_numero,
                'destinatario' => $email,
                'reenvio' => $reenvio,
            ]);
            self::marcarGrupo($ingresos->pluck('id')->all(), 'enviado');

            return true;
        } catch (Throwable $e) {
            Log::error('Cooperadora recibo email falló', [
                'recibo_numero' => $lider->recibo_numero,
                'destinatario' => $email,
                'error' => $e->getMessage(),
            ]);
            self::marcarGrupo($ingresos->pluck('id')->all(), 'error', mb_substr($e->getMessage(), 0, 500));

            return false;
        }
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
     * @param  Collection<int, CoopIngreso>  $ingresos
     * @return array{binario: string, nombre_archivo: string}
     */
    private static function pdfRecibo(Collection $ingresos, CoopIngreso $lider): array
    {
        $pdfDatos = ReciboIngresosGrupo::datosPdf($ingresos);
        $numeroTexto = NumeroDocumentoCooperadora::formatearRecibo((int) $lider->recibo_numero);

        $pdf = ReciboTcpdf::generar([
            'header' => CooperadoraConfig::datosPdfHeader(),
            'recibo_numero_texto' => $numeroTexto,
            'fecha_texto' => $lider->fecha->format('d/m/Y'),
            'pagador_nombre' => $lider->pagador_nombre,
            'importe_letras' => $pdfDatos['importe_letras'],
            'importe' => $pdfDatos['importe_total'],
            'lineas' => $pdfDatos['lineas'],
        ]);

        $nombreArchivo = 'recibo-cooperadora-'.preg_replace('/\D+/', '', $numeroTexto).'.pdf';

        return [
            'binario' => $pdf->Output($nombreArchivo, 'S'),
            'nombre_archivo' => $nombreArchivo,
        ];
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
