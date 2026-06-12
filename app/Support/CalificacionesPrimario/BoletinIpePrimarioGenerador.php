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
        return self::implementacion() === 'estandar';
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDatos(int $idMatricula, int $etapa = 1): array
    {
        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseDatos::buildForMatriculaEnContextoEscolar($idMatricula),
            default => BoletinIpeDatos::buildForMatriculaEnContextoEscolar($idMatricula, $etapa),
        };
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarHoja(array $datos, array $header): TCPDF
    {
        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseTcpdf::generarHoja($datos, $header),
            default => BoletinIpeTcpdf::generarHoja($datos, $header),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(array $hojas, array $header): TCPDF
    {
        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseTcpdf::generarLote($hojas, $header),
            default => BoletinIpeTcpdf::generarLote($hojas, $header),
        };
    }

    public static function respuestaHttp(TCPDF $pdf, string $nombreArchivo): Response
    {
        return match (self::implementacion()) {
            'sanjose' => BoletinIpeSanJoseTcpdf::respuestaHttp($pdf, $nombreArchivo),
            default => BoletinIpeTcpdf::respuestaHttp($pdf, $nombreArchivo),
        };
    }
}
