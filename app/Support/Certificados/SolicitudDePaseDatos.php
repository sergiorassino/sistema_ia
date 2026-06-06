<?php

namespace App\Support\Certificados;

use App\Models\Ento;
use App\Support\MatrizAnaliticos\AnaliticoCalificacionesDatos;
use Illuminate\Support\Facades\DB;

/**
 * Arma el payload para el PDF de solicitud de pase.
 */
final class SolicitudDePaseDatos
{
    /**
     * @param  array{
     *     fechaEmision: string,
     *     cursosCompletos: string,
     *     mateAdeud: string,
     *     cursar: string,
     *     preAnte: string
     * }  $formulario
     * @return array<string, mixed>|null
     */
    public static function paraLegajo(int $idLegajos, array $formulario): ?array
    {
        $alumno = SolicitudDePase::alumnoElegible($idLegajos);
        if ($alumno === null) {
            return null;
        }

        $legajo = DB::table('legajos')
            ->where('id', $idLegajos)
            ->first(['apellido', 'nombre', 'dni']);

        if ($legajo === null) {
            return null;
        }

        $idNivel = (int) ($alumno['idNivel'] ?? PaseParcial::idNivelParaPdf());
        $ento = $idNivel > 0
            ? Ento::query()->where('idNivel', $idNivel)->first(['insti', 'cue', 'localidad', 'logo_path'])
            : null;

        $header = schoolPdfHeaderData();
        $logoAbs = $header['logo_file'] ?? null;
        if ($logoAbs === null || ! is_file($logoAbs)) {
            $fallback = public_path('img/3.png');
            $logoAbs = is_file($fallback) ? $fallback : null;
        }

        $emisionPartes = AnaliticoCalificacionesDatos::partesFechaEspanol($formulario['fechaEmision'] ?? null);

        $localidad = trim((string) ($ento?->localidad ?? $header['localidad'] ?? ''));
        if ($localidad === '') {
            $localidad = 'la localidad del establecimiento';
        }

        $cursoTxt = trim((string) ($alumno['curso'] ?? ''));
        if ($cursoTxt === '') {
            $cursoTxt = 'el curso correspondiente';
        }

        $planTxt = self::planDesdeUltimaMatricula($idLegajos, $idNivel);
        if ($planTxt === '') {
            $planTxt = 'el plan de estudios correspondiente';
        }

        return [
            'institucion' => [
                'insti' => trim((string) ($ento?->insti ?? $header['insti'] ?? '')),
                'cue' => trim((string) ($ento?->cue ?? $header['cue'] ?? '')),
                'localidad' => $localidad,
                'logo_abs' => $logoAbs,
            ],
            'legajo' => [
                'apellido' => trim((string) ($legajo->apellido ?? '')),
                'nombre' => trim((string) ($legajo->nombre ?? '')),
                'dni' => trim((string) ($legajo->dni ?? '')),
            ],
            'solicitud' => [
                'curso' => $cursoTxt,
                'plan' => $planTxt,
                'cursosCompletos' => trim((string) ($formulario['cursosCompletos'] ?? '')),
                'mateAdeud' => trim((string) ($formulario['mateAdeud'] ?? '')),
                'cursar' => trim((string) ($formulario['cursar'] ?? '')),
                'preAnte' => trim((string) ($formulario['preAnte'] ?? '')),
                'diaEmision' => $emisionPartes['dia'],
                'mesEmision' => $emisionPartes['mes'],
                'anioEmision' => $emisionPartes['anio'],
            ],
        ];
    }

    private static function planDesdeUltimaMatricula(int $idLegajos, int $idNivel): string
    {
        $idsNivel = PaseParcial::idsNivelMedio();
        if ($idsNivel === []) {
            return '';
        }

        $q = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->join('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('planes as pl', 'pl.id', '=', 'cp.idPlan')
            ->where('m.idLegajos', $idLegajos)
            ->whereIn('m.idNivel', $idsNivel)
            ->orderByDesc('t.ano')
            ->orderByDesc('m.id');

        if ($idNivel > 0) {
            $q->where('m.idNivel', $idNivel);
        }

        $plan = $q->value('pl.plan');

        return trim((string) ($plan ?? ''));
    }
}
