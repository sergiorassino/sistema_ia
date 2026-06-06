<?php

namespace App\Support\MatrizAnaliticos;

use App\Models\AnaliticoDato;
use App\Models\Ento;
use Illuminate\Support\Facades\DB;

/**
 * Datos para el PDF «frente» del certificado analítico (secundario).
 */
final class AnaliticoFrenteDatos
{
    /** @var array<int, string> */
    private const CURSOS_TITULOS = [
        1 => 'PRIMER AÑO',
        2 => 'SEGUNDO AÑO',
        3 => 'TERCER AÑO',
        4 => 'CUARTO AÑO',
    ];

    /**
     * @return array{
     *     identificacion: array{serie: string, numero: string, analLibroFolio: string},
     *     legajo: array{
     *         apellido: string,
     *         nombre: string,
     *         dni: string,
     *         dia: string,
     *         mes: string,
     *         anio: string,
     *         ln_ciudad: string,
     *         ln_provincia: string
     *     },
     *     institucion: array{
     *         insti: string,
     *         cue: string,
     *         direccion: string,
     *         localidad: string,
     *         departamento: string,
     *         provincia: string,
     *         logo_abs: ?string
     *     },
     *     anios: list<array{titulo: string, filas: list<array<string, mixed>>}>
     * }|null
     */
    public static function paraLegajo(int $idLegajos, int $idNivel): ?array
    {
        if ($idLegajos < 1 || $idNivel < 1) {
            return null;
        }

        $legajo = DB::table('legajos')
            ->where('id', $idLegajos)
            ->first(['id', 'apellido', 'nombre', 'dni', 'fechnaci', 'ln_ciudad', 'ln_provincia']);

        if ($legajo === null) {
            return null;
        }

        $analitico = AnaliticoDato::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first(['serie', 'numero', 'analLibroFolio']);

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['insti', 'cue', 'direccion', 'localidad', 'departamento', 'provincia', 'logo_path']);

        $header = schoolPdfHeaderData();
        $logoAbs = $header['logo_file'] ?? null;
        if ($logoAbs === null || ! is_file($logoAbs)) {
            $fallback = public_path('img/3.png');
            $logoAbs = is_file($fallback) ? $fallback : null;
        }

        $nacimiento = AnaliticoCalificacionesDatos::partesFechaEspanol($legajo->fechnaci ?? null);

        return [
            'identificacion' => [
                'serie' => trim((string) ($analitico?->serie ?? '')),
                'numero' => trim((string) ($analitico?->numero ?? '')),
                'analLibroFolio' => trim((string) ($analitico?->analLibroFolio ?? '')),
            ],
            'legajo' => [
                'apellido' => trim((string) ($legajo->apellido ?? '')),
                'nombre' => trim((string) ($legajo->nombre ?? '')),
                'dni' => trim((string) ($legajo->dni ?? '')),
                'dia' => $nacimiento['dia'],
                'mes' => $nacimiento['mes'],
                'anio' => $nacimiento['anio'],
                'ln_ciudad' => trim((string) ($legajo->ln_ciudad ?? '')),
                'ln_provincia' => trim((string) ($legajo->ln_provincia ?? '')),
            ],
            'institucion' => [
                'insti' => trim((string) ($ento?->insti ?? $header['insti'] ?? '')),
                'cue' => trim((string) ($ento?->cue ?? $header['cue'] ?? '')),
                'direccion' => trim((string) ($ento?->direccion ?? $header['direccion'] ?? '')),
                'localidad' => trim((string) ($ento?->localidad ?? $header['localidad'] ?? '')),
                'departamento' => trim((string) ($ento?->departamento ?? '')),
                'provincia' => trim((string) ($ento?->provincia ?? '')),
                'logo_abs' => $logoAbs,
            ],
            'anios' => AnaliticoCalificacionesDatos::bloquesPorCursos($idLegajos, $idNivel, self::CURSOS_TITULOS),
        ];
    }
}
