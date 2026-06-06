<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Parámetros de `ento` que controlan la carga de notas desde el Menú de Docentes.
 */
final class EntoCargaNotas
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
            ->first(['cargaNotasOff', 'notasOffMensaje']);

        if (! $row) {
            return ['bloqueada' => false, 'mensaje' => ''];
        }

        $bloqueada = (int) ($row->cargaNotasOff ?? 0) === 1;
        $mensaje = trim((string) ($row->notasOffMensaje ?? ''));

        if ($bloqueada && $mensaje === '') {
            $mensaje = 'La carga de calificaciones no está habilitada en este momento.';
        }

        return [
            'bloqueada' => $bloqueada,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @return array{bloqueada: bool, mensaje: string}
     */
    public static function paraNivelActual(): array
    {
        return self::paraNivel((int) (schoolCtx()->idNivel ?? 0));
    }
}
