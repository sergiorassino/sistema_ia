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
    /** Año del curso en palabras (secundario) para el PDF oficial. */
    private const ANIO_EN_PALABRAS = [
        1 => 'PRIMERO',
        2 => 'SEGUNDO',
        3 => 'TERCERO',
        4 => 'CUARTO',
        5 => 'QUINTO',
        6 => 'SEXTO',
    ];

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

        // PDF oficial: «SEXTO B» (año en palabras + sección), no la etiqueta de listado.
        $cursoTxt = self::cursoAnioYSeccion($idLegajos, $idNivel);
        if ($cursoTxt === '') {
            $cursoTxt = trim((string) ($alumno['curso'] ?? ''));
        }
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

    /**
     * Curso para el PDF: año en palabras + sección en mayúscula (ej. «SEXTO B»).
     */
    private static function cursoAnioYSeccion(int $idLegajos, int $idNivel): string
    {
        $idsNivel = PaseParcial::idsNivelMedio();
        if ($idsNivel === []) {
            return '';
        }

        $q = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->where('m.idLegajos', $idLegajos)
            ->whereIn('m.idNivel', $idsNivel)
            ->orderByDesc('t.ano')
            ->orderByDesc('m.id')
            ->select([
                'cu.c',
                'cu.s',
                'cu.cursec',
                'cp.curPlanCurso',
            ]);

        if ($idNivel > 0) {
            $q->where('m.idNivel', $idNivel);
        }

        $row = $q->first();
        if ($row === null) {
            return '';
        }

        return self::formatearCursoAnioSeccion($row);
    }

    private static function formatearCursoAnioSeccion(object $r): string
    {
        $anio = self::anioEnPalabrasDesdeFila($r);
        $seccion = mb_strtoupper(trim((string) ($r->s ?? '')), 'UTF-8');

        if ($seccion === '') {
            $cursec = mb_strtoupper(trim((string) ($r->cursec ?? '')), 'UTF-8');
            if ($cursec !== '' && preg_match('/\b([A-Z])\s*$/u', $cursec, $m) === 1) {
                $seccion = $m[1];
            }
        }

        if ($anio !== '' && $seccion !== '') {
            return $anio.' '.$seccion;
        }

        return $anio;
    }

    private static function anioEnPalabrasDesdeFila(object $r): string
    {
        $c = trim((string) ($r->c ?? ''));
        if ($c !== '' && ctype_digit($c)) {
            $n = (int) $c;
            if (isset(self::ANIO_EN_PALABRAS[$n])) {
                return self::ANIO_EN_PALABRAS[$n];
            }
        }

        foreach ([trim((string) ($r->curPlanCurso ?? '')), trim((string) ($r->cursec ?? ''))] as $texto) {
            $upper = mb_strtoupper($texto, 'UTF-8');
            if ($upper === '') {
                continue;
            }

            if (preg_match('/\b(SEXTO|QUINTO|CUARTO|TERCERO|TERCER|SEGUNDO|PRIMERO|PRIMER)\b/u', $upper, $m) !== 1) {
                continue;
            }

            return match ($m[1]) {
                'PRIMER' => 'PRIMERO',
                'TERCER' => 'TERCERO',
                default => $m[1],
            };
        }

        return '';
    }
}
