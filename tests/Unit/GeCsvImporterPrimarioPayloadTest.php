<?php

namespace Tests\Unit;

use App\Services\SincroGe\GeCsvImporterPrimario;
use ReflectionMethod;
use Tests\TestCase;

class GeCsvImporterPrimarioPayloadTest extends TestCase
{
    public function test_build_grade_payload_mapea_etapa_2_cidi_a_primera_etapa_sistema(): void
    {
        $line = 'PRIMER GRADO;A   ;TARDE;PLAN2026;NIVEL PRIMARIO;58162093;BARTOLUCCI;JUAN FRANCISCO;;;18/02/2020;CIENCIAS, TECNOLOGÍA Y CIUDADANÍA;N00;;;;;;;;;;;;;;;;;MB;371989795;E;373820177;;;;;;;;;E;373247228;;;;;;;;;;;;;;;';
        $payload = $this->payloadFromCsvLine($line);

        $this->assertSame('E', $payload['ic01']);
        $this->assertSame('', $payload['ic02']);
        $this->assertSame('', $payload['ic03']);
        $this->assertSame('MB', $payload['ic05']);
        $this->assertSame('E', $payload['ic06']);
    }

    public function test_final_etapa_queda_vacia_si_solo_hay_parcial_en_cidi(): void
    {
        $line = file('d:/SCRIPTCASE_DEPLOY/ia/EE1110053.csv')[2];
        $payload = $this->payloadFromCsvLine(rtrim($line));

        $this->assertSame('', $payload['ic01']);
        $this->assertSame('B', $payload['ic05']);
        $this->assertSame('', $payload['ic02']);
    }

    /**
     * @return array<string, string>
     */
    private function payloadFromCsvLine(string $line): array
    {
        $row = str_getcsv($line, ';');
        $importer = new GeCsvImporterPrimario;

        $normalize = new ReflectionMethod($importer, 'normalizeRow');
        $normalize->setAccessible(true);
        $row = $normalize->invoke($importer, $row);

        $build = new ReflectionMethod($importer, 'buildGradePayload');
        $build->setAccessible(true);

        return $build->invoke($importer, $row);
    }
}
