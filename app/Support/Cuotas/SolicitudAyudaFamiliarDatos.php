<?php

namespace App\Support\Cuotas;

use App\Models\Legajo;

/**
 * Datos para el PDF «Solicitud de Ayuda Familiar».
 */
final class SolicitudAyudaFamiliarDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraPdf(int $idLegajo, int $nroSolicitud): ?array
    {
        if ($nroSolicitud < 1 || GestionAranceles::legajoParaGestion($idLegajo) === null) {
            return null;
        }

        $legajo = Legajo::query()
            ->whereKey($idLegajo)
            ->first(['id', 'apellido', 'nombre']);

        if ($legajo === null) {
            return null;
        }

        $mat = GestionAranceles::matriculaCicloActivo($idLegajo)
            ?? GestionAranceles::ultimaMatricula($idLegajo);

        $idNivelHeader = (int) ($mat?->idNivel ?? 0);
        $header = $idNivelHeader > 0
            ? LibroArancelesDatos::headerParaNivel($idNivelHeader)
            : schoolPdfHeaderData();
        if (! isset($header['cue']) && isset($header['cuit'])) {
            $header['cue'] = $header['cuit'];
        }

        $apellido = trim((string) ($legajo->apellido ?? ''));
        $nombre = trim((string) ($legajo->nombre ?? ''));
        $apenom = $apellido !== '' && $nombre !== ''
            ? mb_strtoupper($apellido.', '.$nombre)
            : mb_strtoupper(trim($apellido.$nombre));

        return [
            'nroSolicitud' => $nroSolicitud,
            'fechaEmision' => now()->format('d/m/Y'),
            'anoLectivo' => (string) schoolCtx()->terlecAno(),
            'apenom' => $apenom,
            'pdfHeader' => $header,
        ];
    }
}
