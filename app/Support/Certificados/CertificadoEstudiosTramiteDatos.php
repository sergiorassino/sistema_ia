<?php

namespace App\Support\Certificados;

use App\Models\Ento;
use App\Support\MatrizAnaliticos\AnaliticoCalificacionesDatos;
use Illuminate\Support\Facades\DB;

/**
 * Arma el payload para el PDF de constancia de certificado de estudios en trámite.
 */
final class CertificadoEstudiosTramiteDatos
{
    /**
     * @param  array{
     *     mateAdeud: string,
     *     idiomaCursado: string,
     *     preAnte: string,
     *     fechaEmision: string
     * }  $formulario
     * @return array<string, mixed>|null
     */
    public static function paraLegajo(
        int $idLegajos,
        int $idNivel,
        int $idTerlec,
        array $formulario,
    ): ?array {
        $alumno = CertificadoEstudiosTramite::alumnoMatriculado($idLegajos, $idNivel, $idTerlec);
        if ($alumno === null) {
            return null;
        }

        $legajo = DB::table('legajos')
            ->where('id', $idLegajos)
            ->first(['apellido', 'nombre', 'dni']);

        if ($legajo === null) {
            return null;
        }

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['insti', 'cue', 'localidad', 'logo_path']);

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

        $planTxt = trim((string) ($alumno['plan'] ?? ''));
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
            'constancia' => [
                'curso' => $cursoTxt,
                'plan' => $planTxt,
                'mateAdeud' => trim((string) ($formulario['mateAdeud'] ?? '')),
                'idiomaCursado' => trim((string) ($formulario['idiomaCursado'] ?? '')),
                'preAnte' => trim((string) ($formulario['preAnte'] ?? '')),
                'diaEmision' => $emisionPartes['dia'],
                'mesEmision' => $emisionPartes['mes'],
                'anioEmision' => $emisionPartes['anio'],
            ],
        ];
    }
}
