<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Parámetros de `ento` que controlan la visualización de calificaciones en autogestión familia.
 */
final class EntoVerNotasOff
{
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
