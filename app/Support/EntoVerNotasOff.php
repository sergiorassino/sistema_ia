<?php

namespace App\Support;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Parámetros de `ento` que controlan la visualización de calificaciones en autogestión familia.
 */
final class EntoVerNotasOff
{
    public const TITULO_AVISO_CONSULTA = 'Consulta de calificaciones';

    /**
     * @return array{bloqueada: bool, mensaje: string}
     */
    public static function paraNivel(int $idNivel): array
    {
        if ($idNivel < 1) {
            return ['bloqueada' => false, 'mensaje' => ''];
        }

        $row = DB::table('ento')
            ->where('idNivel', $idNivel)
            ->first(['verNotasOff', 'verOffMensaje']);

        if (! $row) {
            return ['bloqueada' => false, 'mensaje' => ''];
        }

        $bloqueada = (int) ($row->verNotasOff ?? 0) === 1;
        $mensaje = self::normalizarMensaje((string) ($row->verOffMensaje ?? ''));

        if ($bloqueada && $mensaje === '') {
            $mensaje = 'La consulta de calificaciones no está habilitada en este momento.';
        }

        return [
            'bloqueada' => $bloqueada,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @return array{bloqueada: bool, mensaje: string}
     */
    public static function paraEstudianteActual(): array
    {
        return self::paraNivel((int) (studentCtx()->idNivel ?? 0));
    }

    public static function consultaEstudianteBloqueada(): bool
    {
        return self::paraEstudianteActual()['bloqueada'];
    }

    public static function mensajeConsultaEstudianteBloqueada(): string
    {
        return self::paraEstudianteActual()['mensaje'];
    }

    /**
     * Si la consulta está bloqueada, respuesta 403 con el mensaje de parámetros.
     */
    public static function respuestaPdfSiConsultaBloqueada(): ?Response
    {
        $bloqueo = self::paraEstudianteActual();
        if (! $bloqueo['bloqueada']) {
            return null;
        }

        return response()->view('errors.alumno-pdf', [
            'mensaje' => $bloqueo['mensaje'],
        ], 403);
    }

    /**
     * Intercepta el acceso rápido del escritorio cuando `verNotasOff` está activo.
     *
     * @param  array<string, mixed>  $acceso
     * @param  array{bloqueada: bool, mensaje: string}|null  $bloqueo
     * @return array<string, mixed>
     */
    public static function aplicarAvisoAAcceso(array $acceso, ?array $bloqueo = null): array
    {
        $bloqueo ??= self::paraEstudianteActual();
        if ($bloqueo['bloqueada']) {
            $acceso['aviso'] = $bloqueo['mensaje'];
            $acceso['aviso_titulo'] = self::TITULO_AVISO_CONSULTA;
            $acceso['externo'] = false;
        }

        return $acceso;
    }

    /**
     * Convierte saltos `<br>` del mensaje configurado en texto plano para SweetAlert2.
     */
    public static function normalizarMensaje(string $mensaje): string
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            return '';
        }

        return trim(preg_replace('/<br\s*\/?>/i', "\n", $mensaje) ?? $mensaje);
    }
}
