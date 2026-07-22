<?php

namespace Tests\Unit;

use App\Services\SincroGe\GeCsvImporter;
use ReflectionMethod;
use Tests\TestCase;

class GeCsvImporterRowStitchTest extends TestCase
{
    public function test_merge_row_fragments_reesambla_apren_con_salto_de_linea(): void
    {
        $part1 = str_getcsv(
            'PRIMER AÑO;B   ;TARDE;687197747;CICLO BASICO;53574366;MONSALBO TARQUINI;VALENTIN;;;13/12/2013;CIENCIAS NATURALES - BIOLOGÍA;A76869;;;8;79570997;;;;;8;83451222;;;;;5;85437513;7;86531165;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;Preparación y observación de muestras simples al microscopio. Comprensión de la célula como unidad integrada, no como suma de partes.',
            ';'
        );
        $part2 = [''];
        $part3 = str_getcsv(';;;;;;;;INSCRIPTO', ';');

        $this->assertCount(74, $part1);
        $this->assertCount(9, $part3);

        $merged = GeCsvImporter::mergeRowFragments($part1, $part2);
        $merged = GeCsvImporter::mergeRowFragments($merged, $part3);

        $this->assertCount(GeCsvImporter::EXPECTED_COLUMN_COUNT, $merged);
        $this->assertSame('PRIMER AÑO', $merged[0]);
        $this->assertSame('53574366', $merged[5]);
        $this->assertSame('INSCRIPTO', $merged[81]);
        $this->assertSame('', $merged[GeCsvImporter::COL_NOTA_FINAL]);
        $this->assertStringContainsString('célula como unidad integrada', (string) $merged[73]);
    }

    public function test_read_logical_csv_row_une_fragmentos_del_export_ge(): void
    {
        $broken = 'PRIMER AÑO;B   ;TARDE;687197747;CICLO BASICO;53574366;MONSALBO TARQUINI;VALENTIN;;;13/12/2013;CIENCIAS NATURALES - BIOLOGÍA;A76869;;;8;79570997;;;;;8;83451222;;;;;5;85437513;7;86531165;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;Preparación y observación de muestras simples al microscopio. Comprensión de la célula como unidad integrada, no como suma de partes.';
        $nextOk = 'PRIMER AÑO;B   ;TARDE;687197747;CICLO BASICO;53574366;MONSALBO TARQUINI;VALENTIN;;;13/12/2013;CIENCIAS NATURALES - FÍSICA;A76870;;;9;79133593;;;;;5;84632627;7;84632637;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;,;;;;;;;;;INSCRIPTO';

        $this->assertCount(74, str_getcsv($broken, ';'));
        $this->assertCount(GeCsvImporter::EXPECTED_COLUMN_COUNT, str_getcsv($nextOk, ';'));

        $csv = implode("\r\n", [
            $this->headerLine(),
            $broken,
            '',
            ';;;;;;;;INSCRIPTO',
            $nextOk,
        ])."\r\n";

        $path = tempnam(sys_get_temp_dir(), 'gecsv');
        $this->assertNotFalse($path);
        file_put_contents($path, $csv);

        $handle = fopen($path, 'rb');
        $this->assertNotFalse($handle);
        fgetcsv($handle, 0, ';');

        $importer = new GeCsvImporter;
        $method = new ReflectionMethod(GeCsvImporter::class, 'readLogicalCsvRow');
        $method->setAccessible(true);

        $row1 = $method->invoke($importer, $handle);
        $this->assertIsArray($row1);
        $this->assertCount(GeCsvImporter::EXPECTED_COLUMN_COUNT, $row1);
        $this->assertSame('A76869', $row1[12]);
        $this->assertSame('INSCRIPTO', $row1[81]);

        $row2 = $method->invoke($importer, $handle);
        $this->assertIsArray($row2);
        $this->assertCount(GeCsvImporter::EXPECTED_COLUMN_COUNT, $row2);
        $this->assertSame('A76870', $row2[12]);
        $this->assertSame('INSCRIPTO', $row2[81]);

        $this->assertFalse($method->invoke($importer, $handle));

        fclose($handle);
        @unlink($path);
    }

    private function headerLine(): string
    {
        $cols = array_fill(0, GeCsvImporter::EXPECTED_COLUMN_COUNT, 'X');
        $cols[0] = 'Grado/Año';
        $cols[11] = 'Espacio Curricular';
        $cols[15] = 'Nota Eval 1';
        $cols[GeCsvImporter::COL_NOTA_FINAL] = 'NOTA FINAL';
        $cols[81] = 'ESTADO';

        return implode(';', $cols);
    }
}
