<?php

namespace App\Support\Certificados;

use App\Models\Ento;
use App\Support\MatrizAnaliticos\AnaliticoCalificacionesDatos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Arma el payload para el PDF de constancia de documentos.
 */
final class ConstanciaDocumentosDatos
{
    /**
     * @param  array{
     *     certifde: string,
     *     otorpor: string,
     *     fechotor: string,
     *     parnacop: string,
     *     parapre: string,
     *     fechemis: string
     * }  $formulario
     * @return array<string, mixed>|null
     */
    public static function paraLegajo(
        int $idLegajos,
        int $idNivel,
        array $formulario,
    ): ?array {
        if (ConstanciaDocumentos::alumnoDelNivel($idLegajos, $idNivel) === null) {
            return null;
        }

        $legajo = DB::table('legajos as l')
            ->join('matricula as m', function ($join) use ($idNivel): void {
                $join->on('m.idLegajos', '=', 'l.id')
                    ->where('m.idNivel', $idNivel);
            })
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->where('l.id', $idLegajos)
            ->orderByDesc('t.ano')
            ->orderByDesc('m.id')
            ->first([
                'l.apellido',
                'l.nombre',
                'l.dni',
                'l.fechnaci',
                'l.nombremad',
                'l.nombrepad',
                'l.ln_ciudad',
                'l.libro',
                'l.folio',
                'l.legajo',
                'm.nroMatricula',
            ]);

        if ($legajo === null) {
            return null;
        }

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['insti', 'cue', 'logo_path']);

        $header = schoolPdfHeaderData();
        $logoAbs = $header['logo_file'] ?? null;
        if ($logoAbs === null || ! is_file($logoAbs)) {
            $fallback = public_path('img/3.png');
            $logoAbs = is_file($fallback) ? $fallback : null;
        }

        $nacimiento = AnaliticoCalificacionesDatos::partesFechaEspanol($legajo->fechnaci ?? null);
        $emision = AnaliticoCalificacionesDatos::partesFechaEspanol($formulario['fechemis'] ?? null);
        $fechotorTxt = self::fechaCortaEspanol($formulario['fechotor'] ?? null);

        return [
            'institucion' => [
                'insti' => trim((string) ($ento?->insti ?? $header['insti'] ?? '')),
                'cue' => trim((string) ($ento?->cue ?? $header['cue'] ?? '')),
                'logo_abs' => $logoAbs,
            ],
            'legajo' => [
                'apellido' => trim((string) ($legajo->apellido ?? '')),
                'nombre' => trim((string) ($legajo->nombre ?? '')),
                'dni' => trim((string) ($legajo->dni ?? '')),
                'libro' => trim((string) ($legajo->libro ?? '')),
                'folio' => trim((string) ($legajo->folio ?? '')),
                'matricula' => trim((string) ($legajo->nroMatricula ?? '')),
                'legajo' => trim((string) ($legajo->legajo ?? '')),
                'nombremad' => trim((string) ($legajo->nombremad ?? '')),
                'nombrepad' => trim((string) ($legajo->nombrepad ?? '')),
                'ln_ciudad' => trim((string) ($legajo->ln_ciudad ?? '')),
                'diaNac' => $nacimiento['dia'],
                'mesNac' => $nacimiento['mes'],
                'anioNac' => $nacimiento['anio'],
            ],
            'constancia' => [
                'certifde' => trim((string) ($formulario['certifde'] ?? '')),
                'otorpor' => trim((string) ($formulario['otorpor'] ?? '')),
                'fechotor' => $fechotorTxt,
                'parnacop' => trim((string) ($formulario['parnacop'] ?? '')),
                'parapre' => trim((string) ($formulario['parapre'] ?? '')),
                'diaEmision' => $emision['dia'],
                'mesEmision' => $emision['mes'],
                'anioEmision' => $emision['anio'],
            ],
        ];
    }

    private static function fechaCortaEspanol(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        try {
            $carbon = $fecha instanceof \DateTimeInterface
                ? Carbon::instance($fecha)
                : Carbon::parse((string) $fecha);
        } catch (\Throwable) {
            return '';
        }

        if ($carbon->year < 1) {
            return '';
        }

        return $carbon->format('d/m/Y');
    }
}
