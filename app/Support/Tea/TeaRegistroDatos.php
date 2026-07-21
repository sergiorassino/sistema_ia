<?php

namespace App\Support\Tea;

use App\Models\ReincoRegistro;
use App\Support\InformeInasistencias;
use App\Support\InasistenciasResumen;

/**
 * Datos para impresos TEA (reinco2025) a partir de un registro y su matrícula.
 */
final class TeaRegistroDatos
{
    /**
     * @return array{
     *     idTipo: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     fecha: string,
     *     ano: int,
     *     totalInasistencias: float,
     *     injustificadas: float,
     *     justificadas: float,
     *     header: array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}
     * }
     */
    public static function desdeRegistro(ReincoRegistro $registro): array
    {
        $matricula = $registro->matricula;
        abort_unless($matricula !== null, 404);

        $legajo = $matricula->legajo;
        $curso = $matricula->curso;
        $ano = ReincoTea::anoLectivo();

        $inasistencias = InformeInasistencias::inasistenciasDelAno((int) $matricula->id, null, $ano);
        $resumen = InasistenciasResumen::desdeColeccion($inasistencias);

        return [
            'idTipo' => (int) $registro->idReinco_tipo,
            'apellido' => trim((string) ($legajo?->apellido ?? '')),
            'nombre' => trim((string) ($legajo?->nombre ?? '')),
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'curso' => trim((string) ($curso?->nombreParaListado() ?? '')),
            'fecha' => $registro->fecha ? $registro->fecha->format('d/m/Y') : now()->format('d/m/Y'),
            'ano' => $ano,
            'totalInasistencias' => $resumen->totalClase(),
            'injustificadas' => $resumen->injustificadas,
            'justificadas' => $resumen->justificadas,
            'header' => schoolPdfHeaderData(),
        ];
    }
}
