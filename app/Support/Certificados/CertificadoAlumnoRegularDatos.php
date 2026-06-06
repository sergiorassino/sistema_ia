<?php

namespace App\Support\Certificados;

use App\Models\Ento;
use App\Support\MatrizAnaliticos\AnaliticoCalificacionesDatos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Arma el payload para el PDF del certificado de alumno/a regular.
 */
final class CertificadoAlumnoRegularDatos
{
    /**
     * @param  array{
     *     iniFin: int,
     *     fechIniFin: string,
     *     prePor: string,
     *     prePorDni: string,
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
        $alumno = CertificadoAlumnoRegular::alumnoMatriculado($idLegajos, $idNivel, $idTerlec);
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

        $iniFin = (int) ($formulario['iniFin'] ?? CertificadoAlumnoRegular::INI_FIN_INICIO);
        $fechIniFinFmt = self::formatearFecha($formulario['fechIniFin'] ?? null);
        $emisionPartes = AnaliticoCalificacionesDatos::partesFechaEspanol($formulario['fechaEmision'] ?? null);

        $localidad = trim((string) ($ento?->localidad ?? $header['localidad'] ?? ''));
        if ($localidad === '') {
            $localidad = 'la localidad del establecimiento';
        }

        $cursoTxt = trim((string) ($alumno['curso'] ?? ''));
        if ($cursoTxt === '') {
            $cursoTxt = 'el curso correspondiente';
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
            'certificado' => [
                'iniFin' => $iniFin,
                'verboIniFin' => $iniFin === CertificadoAlumnoRegular::INI_FIN_FIN ? 'ha finalizado' : 'ha iniciado',
                'fechIniFin' => $fechIniFinFmt,
                'anoLectivo' => (int) ($alumno['anoLectivo'] ?? 0),
                'curso' => $cursoTxt,
                'prePor' => trim((string) ($formulario['prePor'] ?? '')),
                'prePorDni' => trim((string) ($formulario['prePorDni'] ?? '')),
                'preAnte' => trim((string) ($formulario['preAnte'] ?? '')),
                'diaEmision' => $emisionPartes['dia'],
                'mesEmision' => $emisionPartes['mes'],
                'anioEmision' => $emisionPartes['anio'],
            ],
        ];
    }

    private static function formatearFecha(mixed $fecha): string
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

        return $carbon->format('d/m/Y');
    }
}
