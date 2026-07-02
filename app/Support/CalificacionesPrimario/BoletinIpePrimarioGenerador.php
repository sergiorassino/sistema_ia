<?php

namespace App\Support\CalificacionesPrimario;

use Illuminate\Http\Response;
use TCPDF;

/**
 * Despacha datos y TCPDF del boletín IPE primario según implementación del tenant.
 */
final class BoletinIpePrimarioGenerador
{
    public static function implementacion(): string
    {
        return tenantBoletinPrimarioIpeImplementacion();
    }

    public static function usaSelectorEtapa(): bool
    {
        return in_array(self::implementacion(), ['estandar', 'montecristo'], true);
    }

    /** Boletín único sin selector de etapa (p. ej. San José primario). */
    public static function usaBoletinUnico(): bool
    {
        return self::implementacion() === 'sanjose';
    }

    public static function etiquetaPdf(): string
    {
        return match (self::implementacion()) {
            'montecristo' => 'BOLETÍN DE CALIFICACIONES',
            default => 'INFORME DE PROGRESO ESCOLAR',
        };
    }

    public static function prefijoArchivoPdf(): string
    {
        return match (self::implementacion()) {
            'montecristo' => 'boletin_calificaciones',
            default => 'informe_progreso_escolar',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDatos(int $idMatricula, int $etapa = 1): array
    {
        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseDatos::buildForMatriculaEnContextoEscolar($idMatricula),
            'montecristo' => BoletinIpeMontecristoDatos::buildForMatriculaEnContextoEscolar($idMatricula, $etapa),
            default => BoletinIpeDatos::buildForMatriculaEnContextoEscolar($idMatricula, $etapa),
        };
    }

    /**
     * Mismos datos que {@see buildDatos()} para la matrícula del alumno en sesión (portal familia).
     */
    public static function buildDatosParaAlumno(int $etapa = 1): array
    {
        $etapa = $etapa === 2 ? 2 : 1;

        $mat = CalificacionesPrimarioDatos::matriculaAlumnoEnSesion();
        if ($mat === null) {
            return ['ok' => false, 'error' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.'];
        }

        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseDatos::buildDesdeMatricula($mat),
            'montecristo' => BoletinIpeMontecristoDatos::buildDesdeMatricula($mat, $etapa),
            default => BoletinIpeDatos::buildDesdeMatricula($mat, $etapa),
        };
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarHoja(array $datos, array $header, bool $mostrarMarcaAgua = false): TCPDF
    {
        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseTcpdf::generarHoja($datos, $header),
            'montecristo' => BoletinIpeMontecristoTcpdf::generarHoja($datos, $header, $mostrarMarcaAgua),
            default => BoletinIpeTcpdf::generarHoja($datos, $header),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(array $hojas, array $header, bool $mostrarMarcaAgua = false): TCPDF
    {
        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseTcpdf::generarLote($hojas, $header),
            'montecristo' => BoletinIpeMontecristoTcpdf::generarLote($hojas, $header, $mostrarMarcaAgua),
            default => BoletinIpeTcpdf::generarLote($hojas, $header),
        };
    }

    public static function respuestaHttp(TCPDF $pdf, string $nombreArchivo): Response
    {
        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseTcpdf::respuestaHttp($pdf, $nombreArchivo),
            'montecristo' => BoletinIpeMontecristoTcpdf::respuestaHttp($pdf, $nombreArchivo),
            default => BoletinIpeTcpdf::respuestaHttp($pdf, $nombreArchivo),
        };
    }
}
