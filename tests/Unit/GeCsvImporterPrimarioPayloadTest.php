<?php

namespace Tests\Unit;

use App\Services\SincroGe\GeCsvImporterPrimario;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class GeCsvImporterPrimarioPayloadTest extends TestCase
{
    public function test_build_grade_payload_mapea_etapa_2_cidi_a_primera_etapa_sistema(): void
    {
        $line = 'PRIMER GRADO;A   ;TARDE;PLAN2026;NIVEL PRIMARIO;58162093;BARTOLUCCI;JUAN FRANCISCO;;;18/02/2020;CIENCIAS, TECNOLOGÍA Y CIUDADANÍA;N00;;;;;;;;;;;;;;;;;MB;371989795;E;373820177;;;;;;;;;E;373247228;;;;;;;;;;;;;;;';
        $payload = $this->payloadFromCsvLine($line, importarObservacionesInicial: false);

        $this->assertSame('E', $payload['ic01']);
        $this->assertSame('', $payload['ic02']);
        $this->assertSame('', $payload['ic03']);
        $this->assertSame('MB', $payload['ic05']);
        $this->assertSame('E', $payload['ic06']);
        $this->assertArrayNotHasKey('obs01', $payload);
        $this->assertArrayNotHasKey('obs02', $payload);
    }

    public function test_inicial_mapea_columnas_n_y_o_a_obs01_obs02(): void
    {
        $cells = array_fill(0, 58, '');
        $cells[0] = 'SALA DE 5 AÑOS';
        $cells[1] = 'A';
        $cells[5] = '12345678';
        $cells[6] = 'PEREZ';
        $cells[7] = 'ANA';
        $cells[11] = 'IDENTIDAD Y AUTONOMIA';
        $cells[12] = 'N01';
        $cells[13] = 'Texto etapa 1 desde CIDI';
        $cells[14] = 'Texto etapa 2 desde CIDI';
        $cells[41] = 'E';

        $payload = $this->payloadFromCsvLine(implode(';', $cells), importarObservacionesInicial: true);

        $this->assertSame('Texto etapa 1 desde CIDI', $payload['obs01']);
        $this->assertSame('Texto etapa 2 desde CIDI', $payload['obs02']);
        $this->assertSame('E', $payload['ic01']);
    }

    public function test_primario_no_incluye_obs_aunque_haya_textos_en_n_y_o(): void
    {
        $cells = array_fill(0, 58, '');
        $cells[0] = 'PRIMER GRADO';
        $cells[1] = 'A';
        $cells[5] = '12345678';
        $cells[12] = 'N00';
        $cells[13] = 'No debe importarse en primario';
        $cells[14] = 'Tampoco esto';
        $cells[41] = 'MB';

        $payload = $this->payloadFromCsvLine(implode(';', $cells), importarObservacionesInicial: false);

        $this->assertArrayNotHasKey('obs01', $payload);
        $this->assertArrayNotHasKey('obs02', $payload);
        $this->assertSame('MB', $payload['ic01']);
    }

    /**
     * @return array<string, string>
     */
    private function payloadFromCsvLine(string $line, bool $importarObservacionesInicial = false): array
    {
        $row = str_getcsv($line, ';');
        $importer = new GeCsvImporterPrimario;

        $flag = new ReflectionProperty($importer, 'importarObservacionesInicial');
        $flag->setAccessible(true);
        $flag->setValue($importer, $importarObservacionesInicial);

        $normalize = new ReflectionMethod($importer, 'normalizeRow');
        $normalize->setAccessible(true);
        $row = $normalize->invoke($importer, $row);

        $build = new ReflectionMethod($importer, 'buildGradePayload');
        $build->setAccessible(true);

        return $build->invoke($importer, $row);
    }
}
