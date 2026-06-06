<?php

namespace App\Support\Certificados;

use App\Models\Ento;
use App\Support\MatrizAnaliticos\AnaliticoCalificacionesDatos;

/**
 * Arma el payload para el PDF del certificado de asistencia del profesor.
 */
final class CertificadoAsistenciaProfesorDatos
{
    /**
     * @param  array{
     *     fecha: string,
     *     texto: string,
     *     parapre: string
     * }  $formulario
     * @return array<string, mixed>|null
     */
    public static function paraProfesor(int $idProfesores, int $idNivel, array $formulario): ?array
    {
        $profesor = CertificadoAsistenciaProfesor::profesorElegible($idProfesores);
        if ($profesor === null) {
            return null;
        }

        $ento = null;
        if ($idNivel > 0) {
            $ento = Ento::query()
                ->where('idNivel', $idNivel)
                ->first(['insti', 'cue', 'localidad', 'logo_path']);
        }

        $header = schoolPdfHeaderData();
        $logoAbs = $header['logo_file'] ?? null;
        if ($logoAbs === null || ! is_file($logoAbs)) {
            $fallback = public_path('img/3.png');
            $logoAbs = is_file($fallback) ? $fallback : null;
        }

        $emisionPartes = AnaliticoCalificacionesDatos::partesFechaEspanol($formulario['fecha'] ?? null);

        return [
            'institucion' => [
                'insti' => trim((string) ($ento?->insti ?? $header['insti'] ?? '')),
                'cue' => trim((string) ($ento?->cue ?? $header['cue'] ?? '')),
                'logo_abs' => $logoAbs,
            ],
            'profesor' => [
                'apellido' => $profesor['apellido'],
                'nombre' => $profesor['nombre'],
                'dni' => $profesor['dni'],
            ],
            'certificado' => [
                'texto' => trim((string) ($formulario['texto'] ?? '')),
                'parapre' => trim((string) ($formulario['parapre'] ?? '')),
                'diaEmision' => $emisionPartes['dia'],
                'mesEmision' => $emisionPartes['mes'],
                'anioEmision' => $emisionPartes['anio'],
            ],
        ];
    }
}
