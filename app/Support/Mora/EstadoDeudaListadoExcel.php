<?php

namespace App\Support\Mora;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel genérico del listado de estado de deuda (familia / estudiante).
 */
final class EstadoDeudaListadoExcel
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string|int|float>>  $filas
     * @param  list<int>  $columnasImporte  Índices 1-based de columnas numéricas de importe
     * @return array{spreadsheet: Spreadsheet, filename: string}
     */
    public static function build(
        string $tituloHoja,
        string $filename,
        array $encabezados,
        array $filas,
        array $columnasImporte = [],
    ): array {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle(mb_substr($tituloHoja, 0, 31));

        $col = 1;
        foreach ($encabezados as $encabezado) {
            $hoja->setCellValue([$col, 1], $encabezado);
            $col++;
        }

        $fila = 2;
        foreach ($filas as $valores) {
            $col = 1;
            foreach ($valores as $valor) {
                $hoja->setCellValue([$col, $fila], $valor);
                $col++;
            }
            $fila++;
        }

        self::estilizar($hoja, count($encabezados), max(1, $fila - 1), $columnasImporte);

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
     * @param  list<int>  $columnasImporte
     */
    private static function estilizar(Worksheet $hoja, int $totalColumnas, int $ultimaFila, array $columnasImporte): void
    {
        $ultimaCol = self::indiceColumnaExcel($totalColumnas);
        $hoja->getStyle('A1:'.$ultimaCol.'1')->getFont()->setBold(true);
        $hoja->freezePane('A2');

        for ($c = 1; $c <= $totalColumnas; $c++) {
            $hoja->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        foreach ($columnasImporte as $colImporte) {
            $colLetra = self::indiceColumnaExcel((int) $colImporte);
            $rango = $colLetra.'2:'.$colLetra.$ultimaFila;
            $hoja->getStyle($rango)->getNumberFormat()->setFormatCode('#,##0.00');
            $hoja->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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
