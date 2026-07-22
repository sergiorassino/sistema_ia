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

    public function test_merge_padded_continuation_nssc_operaciones_basicas(): void
    {
        $incomplete = array_fill(0, 82, '');
        $incomplete[0] = 'PRIMER AÑO';
        $incomplete[5] = '53266812';
        $incomplete[11] = 'MATEMÁTICA';
        $incomplete[12] = 'A76868';
        $incomplete[15] = '8';
        $incomplete[71] = 'Números Naturales. Sistema de numeración decimal posicional. Recta numérica.';

        $ghost = array_fill(0, 82, '');
        $ghost[0] = 'Operaciones básicas. Propiedades. Resolución de problemas.';
        $ghost[10] = 'INSCRIPTO';

        $merged = GeCsvImporter::mergePaddedContinuation($incomplete, $ghost);

        $this->assertCount(82, $merged);
        $this->assertSame('PRIMER AÑO', $merged[0]);
        $this->assertSame('A76868', $merged[12]);
        $this->assertSame('8', $merged[15]);
        $this->assertStringContainsString('Números Naturales', (string) $merged[71]);
        $this->assertStringContainsString('Operaciones básicas', (string) $merged[72]);
        $this->assertSame('INSCRIPTO', $merged[81]);
    }

    public function test_read_logical_csv_row_absorbe_continuaciones_rellenadas_nssc(): void
    {
        $incomplete = 'PRIMER AÑO;B   ;MAÑANA;687197747;CICLO BASICO;53266812;AGUIRRE BONINA;ALFONSINA;;;07/11/2013;MATEMÁTICA;A76868;;;8;79433825;;;;;9;82380670;;;;;9;87956354;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;Números Naturales. Sistema de numeración decimal posicional. Recta numérica.;;;;;;;;;;';
        $ghost = 'Operaciones básicas. Propiedades. Resolución de problemas. ;;;;;;;;;;INSCRIPTO;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;';
        $nextOk = 'PRIMER AÑO;B   ;MAÑANA;687197747;CICLO BASICO;53265329;AHUMADA;ISABELLA;;;16/09/2013;CATEQUESIS;ADGIPE101;;;10;84051245;;;;;10;84055137;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;INSCRIPTO';

        $this->assertCount(82, str_getcsv($incomplete, ';'));
        $this->assertCount(82, str_getcsv($ghost, ';'));
        $this->assertSame('', str_getcsv($incomplete, ';')[81]);

        $csv = implode("\r\n", [
            $this->headerLine(),
            $incomplete,
            $ghost,
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
        $this->assertSame('A76868', $row1[12]);
        $this->assertSame('INSCRIPTO', $row1[81]);
        $this->assertStringContainsString('Operaciones básicas', (string) $row1[72]);

        $row2 = $method->invoke($importer, $handle);
        $this->assertIsArray($row2);
        $this->assertSame('ADGIPE101', $row2[12]);
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
