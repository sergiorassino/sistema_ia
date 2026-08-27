<?php

namespace App\Support\Mora;

use App\Support\Cuotas\CuotasFormato;
use Carbon\Carbon;

/**
 * Filas del listado de estado de deuda familiar (PDF y Excel): una fila por estudiante.
 */
final class EstadoDeudaFamiliarListadoExport
{
    /**
     * @return array<string, mixed>
     */
    public static function datosPdf(EstadoDeudaListadoFiltros $filtros): array
    {
        $filasExcel = self::filas($filtros);
        $filasPdf = [];
        foreach ($filasExcel as $fila) {
            $filasPdf[] = [
                (string) $fila[0],
                (string) $fila[1],
                (string) $fila[2],
                (string) $fila[3],
                CuotasFormato::formatearImporte((float) $fila[4]),
                (string) $fila[5],
                (string) $fila[6],
                CuotasFormato::formatearImporte((float) $fila[7]),
            ];
        }

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'tituloDocumento' => 'Listado estado de deuda familiar',
            'tituloInforme' => 'LISTADO DE ESTADO DE DEUDA FAMILIAR',
            'fechaInforme' => Carbon::now()->format('d/m/Y'),
            'filtrosLinea' => $filtros->etiqueta(),
            'encabezados' => self::encabezados(),
            'anchos' => [10.0, 34.0, 20.0, 26.0, 20.0, 28.0, 30.0, 22.0],
            'alignDerecha' => [4, 7],
            'filas' => $filasPdf,
        ];
    }

    /**
     * @return array{spreadsheet: \PhpOffice\PhpSpreadsheet\Spreadsheet, filename: string}
     */
    public static function excel(EstadoDeudaListadoFiltros $filtros): array
    {
        return EstadoDeudaListadoExcel::build(
            'Deuda familiar',
            'estado_deuda_familiar_'.schoolCtx()->terlecAno().'.xlsx',
            self::encabezados(),
            self::filas($filtros),
            [5, 8],
        );
    }

    /**
     * @return list<string>
     */
    public static function encabezados(): array
    {
        return [
            'Nº',
            'Estudiante',
            'DNI',
            'Curso actual',
            'Deuda estudiante',
            'Familia',
            'Responsable',
            'Deuda familia',
        ];
    }

    /**
     * @return list<list<string|int|float>>
     */
    public static function filas(EstadoDeudaListadoFiltros $filtros): array
    {
        $familias = EstadoDeudaFamiliarListado::coleccionFamilias(
            $filtros->search,
            $filtros->idNivel,
            $filtros->soloConDeuda,
        );
        $idsFamilias = $familias->pluck('id')->map(fn ($id) => (int) $id)->all();
        $totales = EstadoDeudaFamiliarDatos::totalesAPagarPorFamilias($idsFamilias);

        $filas = [];
        $nro = 0;
        foreach ($familias as $familia) {
            $etiquetaFamilia = mb_strtoupper(trim((string) ($familia->apellido ?? '')));
            $etiquetaResponsable = mb_strtoupper(trim((string) ($familia->responsable ?? '')));
            $deudaFamilia = round((float) ($totales['porFamilia'][$familia->id] ?? 0), 2);
            $estudiantes = $familia->legajos;

            if ($estudiantes->isEmpty()) {
                $nro++;
                $filas[] = [
                    $nro,
                    '',
                    '',
                    '',
                    0.0,
                    $etiquetaFamilia,
                    $etiquetaResponsable,
                    $deudaFamilia,
                ];

                continue;
            }

            foreach ($estudiantes as $estudiante) {
                $nro++;
                $filas[] = [
                    $nro,
                    EstadoDeudaFamiliarListado::apellidoNombre($estudiante),
                    CuotasFormato::formatearDni($estudiante->dni),
                    EstadoDeudaFamiliarListado::cursoCicloActivo($estudiante),
                    round((float) ($totales['porLegajo'][$estudiante->id] ?? 0), 2),
                    $etiquetaFamilia,
                    $etiquetaResponsable,
                    $deudaFamilia,
                ];
            }
        }

        return $filas;
    }
}
