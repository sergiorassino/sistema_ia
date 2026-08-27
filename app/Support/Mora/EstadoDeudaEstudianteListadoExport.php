<?php

namespace App\Support\Mora;

use App\Support\Cuotas\CuotasFormato;
use Carbon\Carbon;

/**
 * Filas del listado de estado de deuda por estudiante (PDF y Excel).
 */
final class EstadoDeudaEstudianteListadoExport
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
            ];
        }

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'tituloDocumento' => 'Listado estado de deuda por estudiante',
            'tituloInforme' => 'LISTADO DE ESTADO DE DEUDA POR ESTUDIANTE',
            'fechaInforme' => Carbon::now()->format('d/m/Y'),
            'filtrosLinea' => $filtros->etiqueta(),
            'encabezados' => self::encabezados(),
            'anchos' => [10.0, 40.0, 22.0, 30.0, 22.0, 33.0, 33.0],
            'alignDerecha' => [4],
            'filas' => $filasPdf,
        ];
    }

    /**
     * @return array{spreadsheet: \PhpOffice\PhpSpreadsheet\Spreadsheet, filename: string}
     */
    public static function excel(EstadoDeudaListadoFiltros $filtros): array
    {
        return EstadoDeudaListadoExcel::build(
            'Deuda estudiante',
            'estado_deuda_estudiante_'.schoolCtx()->terlecAno().'.xlsx',
            self::encabezados(),
            self::filas($filtros),
            [5],
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
            'Deuda',
            'Familia',
            'Responsable',
        ];
    }

    /**
     * @return list<list<string|int|float>>
     */
    public static function filas(EstadoDeudaListadoFiltros $filtros): array
    {
        $estudiantes = EstadoDeudaEstudianteListado::coleccionEstudiantes(
            $filtros->search,
            $filtros->idNivel,
            $filtros->soloConDeuda,
        );
        $ids = $estudiantes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $totales = EstadoDeudaEstudianteDatos::totalesAPagarPorLegajos($ids);

        $filas = [];
        $nro = 0;
        foreach ($estudiantes as $estudiante) {
            $nro++;
            $familia = EstadoDeudaEstudianteListado::familiaAsignada($estudiante->familia);
            $etiquetaFamilia = trim((string) ($familia?->apellido ?? ''));
            $etiquetaResponsable = trim((string) ($familia?->responsable ?? ''));

            $filas[] = [
                $nro,
                EstadoDeudaEstudianteListado::apellidoNombre($estudiante),
                CuotasFormato::formatearDni($estudiante->dni),
                EstadoDeudaEstudianteListado::cursoCicloActivo($estudiante),
                round((float) ($totales[$estudiante->id] ?? 0), 2),
                $etiquetaFamilia !== '' ? mb_strtoupper($etiquetaFamilia) : 'Sin familia',
                $etiquetaResponsable !== '' ? mb_strtoupper($etiquetaResponsable) : '',
            ];
        }

        return $filas;
    }
}
