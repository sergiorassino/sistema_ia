<?php

namespace App\Support\Alumnos;

use App\Support\InformeInasistencias;
use Carbon\Carbon;

/**
 * Datos de la constancia de libre deuda (autogestión familia, ciclo de autogestión).
 */
final class LibreDeudaDatos
{
    /**
     * @return array{
     *     id_legajo: int,
     *     apellido: string,
     *     nombre: string,
     *     apenom: string,
     *     dni: string,
     *     cursec: string,
     *     nivel: string,
     *     fecha: string,
     *     lugar: string,
     *     header: array{insti:string,direccion:string,localidad:string,provincia:string,cue:string,ee:string,logo_file:?string},
     *     firma_file: ?string,
     *     sello_file: ?string
     * }|null
     */
    public static function paraAutogestion(): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $matricula = InformeInasistencias::matriculaAutogestion();
        $legajo = $matricula?->legajo;
        if ($matricula === null || $legajo === null) {
            return null;
        }

        $apellido = trim((string) ($legajo->apellido ?? ''));
        $nombre = trim((string) ($legajo->nombre ?? ''));
        $apenom = trim($apellido.' '.$nombre);

        $curso = $matricula->curso;
        $cursec = '';
        if ($curso !== null) {
            $curso->loadMissing(['curplan', 'turnoClase']);
            $cursec = trim((string) ($curso->cursec ?? ''));
            if ($cursec === '' || $cursec === '0') {
                $cursec = trim((string) $curso->nombreParaListado());
            }
        }
        if ($cursec === '0' || strcasecmp($cursec, 'Curso') === 0) {
            $cursec = '';
        } elseif ($cursec !== '') {
            $cursec = mb_strtoupper($cursec);
        }

        $header = studentPdfHeaderData();
        $lugar = trim((string) config('tenant.autogestion.libre_deuda.lugar', ''));
        if ($lugar === '') {
            $lugar = trim((string) ($header['localidad'] ?? ''));
        }
        if ($lugar === '') {
            $lugar = 'Monte Cristo';
        }

        return [
            'id_legajo' => (int) $legajo->id,
            'apellido' => $apellido,
            'nombre' => $nombre,
            'apenom' => $apenom,
            'dni' => trim((string) ($legajo->dni ?? '')),
            'cursec' => $cursec,
            'nivel' => self::etiquetaNivel(trim((string) $ctx->nivelNombre())),
            'fecha' => Carbon::now()->timezone(config('app.timezone'))->format('d/m/Y'),
            'lugar' => $lugar,
            'header' => $header,
            'firma_file' => self::archivoPublico(config('tenant.autogestion.libre_deuda.firma')),
            'sello_file' => self::archivoPublico(config('tenant.autogestion.libre_deuda.sello')),
        ];
    }

    /**
     * El FPDF legacy usaba glo_nombreNivel (“Nivel Secundario”).
     */
    private static function etiquetaNivel(string $nivel): string
    {
        if ($nivel === '') {
            return '';
        }
        if (preg_match('/^nivel\b/iu', $nivel) === 1) {
            return $nivel;
        }

        return 'Nivel '.$nivel;
    }

    private static function archivoPublico(mixed $relativa): ?string
    {
        $rel = trim((string) $relativa);
        if ($rel === '') {
            return null;
        }

        $abs = public_path($rel);

        return is_file($abs) ? $abs : null;
    }
}
