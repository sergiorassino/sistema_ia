<?php

namespace App\Support\Certificados;

use App\Models\Ento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Arma el payload para el PDF de solicitud de pase (formato legacy FPDF).
 */
final class PaseParcialDatos
{
    /**
     * @param  array{fecha: string, destino: string}  $formulario
     * @return array<string, mixed>|null
     */
    public static function paraLegajo(
        int $idLegajos,
        int $idTerlec,
        array $formulario,
    ): ?array {
        if ($idTerlec < 1 || ! PaseParcial::alumnoElegible($idLegajos)) {
            return null;
        }

        $matricula = PaseParcial::matriculaEnCiclo($idLegajos, $idTerlec);
        if ($matricula === null) {
            return null;
        }

        $legajo = DB::table('legajos')
            ->where('id', $idLegajos)
            ->first(['apellido', 'nombre', 'dni']);

        if ($legajo === null) {
            return null;
        }

        $idNivel = (int) ($matricula['idNivel'] ?? PaseParcial::idNivelParaPdf());
        $ento = $idNivel > 0
            ? Ento::query()->where('idNivel', $idNivel)->first(['insti', 'cue', 'localidad', 'logo_path'])
            : null;

        $header = schoolPdfHeaderData();
        $logoAbs = $header['logo_file'] ?? null;
        if ($logoAbs === null || ! is_file($logoAbs)) {
            $fallback = public_path('img/3.png');
            $logoAbs = is_file($fallback) ? $fallback : null;
        }

        $localidad = trim((string) ($ento?->localidad ?? $header['localidad'] ?? ''));
        $cursec = trim((string) ($matricula['cursec'] ?? ''));
        if ($cursec === '') {
            $cursec = 'el curso correspondiente';
        }

        $idMatricula = (int) $matricula['idMatricula'];

        return [
            'impreso_en' => now()->format('d-m-y H:i'),
            'institucion' => [
                'insti' => trim((string) ($ento?->insti ?? $header['insti'] ?? '')),
                'cue' => trim((string) ($ento?->cue ?? $header['cue'] ?? '')),
                'localidad' => $localidad,
                'logo_abs' => $logoAbs,
            ],
            'legajo' => [
                'apellido' => trim((string) ($legajo->apellido ?? '')),
                'nombre' => trim((string) ($legajo->nombre ?? '')),
            ],
            'solicitud' => [
                'cursec' => $cursec,
                'destino' => trim((string) ($formulario['destino'] ?? '')),
                'fecha' => self::formatearFecha($formulario['fecha'] ?? null),
            ],
            'informe' => PaseParcial::totalesInforme($idMatricula),
            'calificaciones' => PaseParcial::filasCalificaciones($idMatricula, $idTerlec),
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
