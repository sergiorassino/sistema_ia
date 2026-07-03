<?php

namespace App\Support\CalificacionesInicial;

use Illuminate\Http\Response;
use TCPDF;

/**
 * Despacha TCPDF del Informe de Progreso Escolar (inicial) según implementación del tenant.
 */
final class InformeProgresoInicialGenerador
{
    public static function implementacion(): string
    {
        return tenantCalificacionesInicialInformeProgresoImplementacion();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generar(array $datos, array $header, bool $mostrarMarcaAgua = false): TCPDF
    {
        return InformeProgresoInicialTcpdf::generar($datos, $header, $mostrarMarcaAgua, self::implementacion());
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(array $hojas, array $header, bool $mostrarMarcaAgua = false): TCPDF
    {
        return InformeProgresoInicialTcpdf::generarLote($hojas, $header, $mostrarMarcaAgua, self::implementacion());
    }

    public static function respuestaHttp(TCPDF $pdf, string $nombreArchivo): Response
    {
        return InformeProgresoInicialTcpdf::respuestaHttp($pdf, $nombreArchivo);
    }
}
