<?php

namespace App\Support\Listados;

use App\Support\Alumnos\ArancelesEscolares;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Filas del listado de familias: en pantalla y PDF la familia se agrupa; en Excel se repite en cada hijo.
 */
final class ListadoFamiliasExport
{
    public const COLS_FAMILIA = 5;

    /**
     * @return array<string, mixed>
     */
    public static function datosPdf(ListadoFamiliasFiltros $filtros): array
    {
        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'tituloDocumento' => 'Listado de familias',
            'tituloInforme' => 'LISTADO DE FAMILIAS',
            'fechaInforme' => Carbon::now()->format('d/m/Y'),
            'filtrosLinea' => $filtros->etiqueta(),
            'encabezados' => self::encabezados(),
            'anchos' => [8.0, 22.0, 26.0, 18.0, 32.0, 22.0, 22.0, 18.0, 11.0, 11.0],
            'grupos' => self::gruposTexto($filtros),
        ];
    }

    /**
     * @return array{spreadsheet: Spreadsheet, filename: string}
     */
    public static function excel(ListadoFamiliasFiltros $filtros): array
    {
        $ano = schoolCtx()->terlecAno();
        $filename = 'listado_familias_'.($ano !== '' ? $ano : 'ciclo').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Familias');

        $encabezados = self::encabezados();
        $col = 1;
        foreach ($encabezados as $encabezado) {
            $hoja->setCellValue([$col, 1], $encabezado);
            $col++;
        }

        $fila = 2;
        foreach (self::grupos($filtros) as $grupo) {
            $hijos = $grupo['hijos'];
            if ($hijos === []) {
                $hijos = [['', '', '', '', '']];
            }

            foreach ($hijos as $hijo) {
                $valores = array_merge($grupo['familia'], $hijo);
                $col = 1;
                foreach ($valores as $valor) {
                    $hoja->setCellValue([$col, $fila], $valor);
                    $col++;
                }
                $fila++;
            }
        }

        self::estilizar($hoja, count($encabezados), max(1, $fila - 1));

        return [
            'spreadsheet' => $spreadsheet,
            'filename' => $filename,
        ];
    }

    public static function escribirEnSalida(Spreadsheet $spreadsheet): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        (new Xlsx($spreadsheet))->save('php://output');
    }

    /**
     * @return list<string>
     */
    public static function encabezados(): array
    {
        return [
            'Nº',
            'Familia',
            'Responsable',
            'DNI responsable',
            'Email',
            'Apellido',
            'Nombre',
            'DNI',
            'Curso',
            'Sección',
        ];
    }

    /**
     * @return list<array{familia: list<string|int>, hijos: list<list<string>>}>
     */
    public static function grupos(ListadoFamiliasFiltros $filtros): array
    {
        $familias = ListadoFamiliasConsulta::coleccion($filtros->search, $filtros->idNivel);
        $grupos = [];
        $nro = 0;

        foreach ($familias as $familia) {
            $nro++;
            $hijos = [];
            foreach ($familia->legajos as $estudiante) {
                $cursoSeccion = ListadoFamiliasConsulta::cursoYSeccionDeLegajo($estudiante);
                $hijos[] = [
                    trim((string) ($estudiante->apellido ?? '')),
                    trim((string) ($estudiante->nombre ?? '')),
                    ArancelesEscolares::formatearDni($estudiante->dni),
                    $cursoSeccion['curso'],
                    $cursoSeccion['seccion'],
                ];
            }

            $grupos[] = [
                'familia' => [
                    $nro,
                    trim((string) ($familia->apellido ?? '')),
                    trim((string) ($familia->responsable ?? '')),
                    ListadoFamiliasConsulta::tieneDniResp()
                        ? ArancelesEscolares::formatearDni($familia->dniResp ?? '')
                        : '',
                    trim((string) ($familia->email ?? '')),
                ],
                'hijos' => $hijos,
            ];
        }

        return $grupos;
    }

    /**
     * @return list<array{familia: list<string>, hijos: list<list<string>>}>
     */
    public static function gruposTexto(ListadoFamiliasFiltros $filtros): array
    {
        $grupos = [];
        foreach (self::grupos($filtros) as $grupo) {
            $grupos[] = [
                'familia' => array_map(static fn ($v) => (string) $v, $grupo['familia']),
                'hijos' => array_map(
                    static fn (array $hijo): array => array_map(static fn ($v) => (string) $v, $hijo),
                    $grupo['hijos'],
                ),
            ];
        }

        return $grupos;
    }

    private static function estilizar(Worksheet $hoja, int $totalColumnas, int $ultimaFila): void
    {
        $ultimaCol = self::indiceColumnaExcel($totalColumnas);
        $hoja->getStyle('A1:'.$ultimaCol.'1')->getFont()->setBold(true);
        $hoja->freezePane('A2');
        $hoja->getStyle('A2:A'.$ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        for ($c = 1; $c <= $totalColumnas; $c++) {
            $hoja->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
    }

    private static function indiceColumnaExcel(int $numeroColumna): string
    {
        $letras = '';
        $n = $numeroColumna;
        while ($n > 0) {
            $n--;
            $letras = chr(65 + ($n % 26)).$letras;
            $n = intdiv($n, 26);
        }

        return $letras;
    }
}
