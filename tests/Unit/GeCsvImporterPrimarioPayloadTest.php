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

    public function test_inicial_usa_nota_final_por_espacio_no_observaciones_de_sala(): void
    {
        $cells = array_fill(0, 59, '');
        $cells[0] = 'SALA DE 5 AÑOS';
        $cells[1] = 'A';
        $cells[5] = '12345678';
        $cells[11] = 'EDUCACIÓN ARTÍSTICA';
        $cells[12] = 'INI002';
        $cells[13] = 'Texto de sala repetido en todas las materias';
        $cells[14] = '';
        $cells[27] = 'Texto específico de Educación Artística para el IPE.';
        $cells[41] = 'Texto etapa 2 de Educación Artística para el IPE.';

        $payload = $this->payloadFromCsvLine(implode(';', $cells), importarObservacionesInicial: true);

        $this->assertSame(['obs01', 'obs02'], array_keys($payload));
        $this->assertSame('Texto específico de Educación Artística para el IPE.', $payload['obs01']);
        $this->assertSame('Texto etapa 2 de Educación Artística para el IPE.', $payload['obs02']);
        $this->assertStringNotContainsString('sala repetido', $payload['obs01']);
    }

    public function test_inicial_fallback_a_n_si_nota_final_vacia(): void
    {
        $cells = array_fill(0, 59, '');
        $cells[0] = 'SALA DE 5 AÑOS';
        $cells[5] = '12345678';
        $cells[12] = 'INI001';
        $cells[13] = 'Solo hay texto en Observaciones N';
        $cells[27] = '';

        $payload = $this->payloadFromCsvLine(implode(';', $cells), importarObservacionesInicial: true);

        $this->assertSame('Solo hay texto en Observaciones N', $payload['obs01']);
    }

    public function test_primario_no_incluye_obs(): void
    {
        $cells = array_fill(0, 59, '');
        $cells[0] = 'PRIMER GRADO';
        $cells[5] = '12345678';
        $cells[12] = 'N00';
        $cells[27] = 'No debe importarse en primario';
        $cells[41] = 'MB';

        $payload = $this->payloadFromCsvLine(implode(';', $cells), importarObservacionesInicial: false);

        $this->assertArrayNotHasKey('obs01', $payload);
        $this->assertSame('MB', $payload['ic01']);
    }

    public function test_repara_punto_y_coma_en_nota_final_etapa_1(): void
    {
        $cells = array_fill(0, 59, '');
        $cells[0] = 'SALA DE TRES AÑOS';
        $cells[5] = '59628400';
        $cells[12] = 'INI001';
        $cells[13] = 'Texto de sala';
        $cells[27] = 'Bruno ingresó al jardín sin presentar dificultades';
        array_splice($cells, 28, 0, [
            ' reconoce y espera su turno para conversar',
            ' durante el proyecto de piedras.',
        ]);
        $this->assertCount(61, $cells);
        $cells[30] = '13970095';

        $importer = new GeCsvImporterPrimario;
        $cells[28] = trim($cells[28]);
        $cells[29] = trim($cells[29]);

        $obs = $importer->extraerObservacionesInicial($cells);

        $this->assertStringContainsString('Bruno ingresó al jardín', $obs['obs01']);
        $this->assertStringContainsString('espera su turno', $obs['obs01']);
        $this->assertStringContainsString('proyecto de piedras', $obs['obs01']);
        $this->assertStringNotContainsString('Texto de sala', $obs['obs01']);
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
