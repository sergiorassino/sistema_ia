<?php

namespace App\Support\MatrizAnaliticos;

use App\Models\AnaliticoDato;
use App\Models\Ento;

/**
 * Datos para el PDF «reverso» del certificado analítico (5.º y 6.º año + pie).
 */
final class AnaliticoReversoDatos
{
    /** @var array<int, string> */
    private const CURSOS_TITULOS = [
        5 => 'QUINTO AÑO',
        6 => 'SEXTO AÑO',
    ];

    /**
     * @return array{
     *     legajo: array{apellido: string, nombre: string, dni: string},
     *     anios: list<array{titulo: string, filas: list<array<string, mixed>>}>,
     *     pie: array{
     *         analCohorte: string,
     *         analObservaciones: string,
     *         analParaCompletar: string,
     *         analValidez: string,
     *         serie: string,
     *         analLibroFolio: string,
     *         diaEmision: string,
     *         mesEmision: string,
     *         anioEmision: string,
     *         localidadEmision: string,
     *         analParaPre: string
     *     }
     * }|null
     */
    public static function paraLegajo(int $idLegajos, int $idNivel): ?array
    {
        if ($idLegajos < 1 || $idNivel < 1) {
            return null;
        }

        $legajo = AnaliticoCalificacionesDatos::legajoIdentificacion($idLegajos);
        if ($legajo === null) {
            return null;
        }

        $analitico = AnaliticoDato::paraLegajo($idLegajos);

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['localidad']);

        $header = schoolPdfHeaderData();
        $localidad = trim((string) ($ento?->localidad ?? $header['localidad'] ?? ''));

        $emision = AnaliticoCalificacionesDatos::partesFechaEspanol($analitico?->analFechaEmision ?? null);

        return [
            'legajo' => $legajo,
            'anios' => AnaliticoCalificacionesDatos::bloquesPorCursos($idLegajos, $idNivel, self::CURSOS_TITULOS),
            'pie' => [
                'analCohorte' => self::cohorteEtiqueta($analitico?->analCohorte ?? null),
                'analObservaciones' => trim((string) ($analitico?->analObservaciones ?? '')),
                'analParaCompletar' => trim((string) ($analitico?->analParaCompletar ?? '')),
                'analValidez' => trim((string) ($analitico?->analValidez ?? '')),
                'serie' => trim((string) ($analitico?->serie ?? '')),
                'analLibroFolio' => trim((string) ($analitico?->analLibroFolio ?? '')),
                'diaEmision' => $emision['dia'],
                'mesEmision' => $emision['mes'],
                'anioEmision' => $emision['anio'],
                'localidadEmision' => $localidad,
                'analParaPre' => trim((string) ($analitico?->analParaPre ?? '')),
            ],
        ];
    }

    private static function cohorteEtiqueta(mixed $valor): string
    {
        $t = trim((string) $valor);

        return $t === '0' ? '' : $t;
    }
}
