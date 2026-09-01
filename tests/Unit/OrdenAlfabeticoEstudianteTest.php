<?php

namespace Tests\Unit;

use App\Support\OrdenAlfabeticoEstudiante;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OrdenAlfabeticoEstudianteTest extends TestCase
{
    public function test_caceres_va_con_las_c_antes_de_castro_y_corzo(): void
    {
        $apellidos = ['CORZO FLORES', 'CÁCERES', 'CASTRO NEMESSI', 'CORNEJO-TELLO', 'DIATTO'];
        usort($apellidos, fn (string $a, string $b): int => OrdenAlfabeticoEstudiante::comparar($a, '', $b, ''));

        $this->assertSame(
            ['CÁCERES', 'CASTRO NEMESSI', 'CORNEJO-TELLO', 'CORZO FLORES', 'DIATTO'],
            $apellidos,
        );
    }

    public function test_tilde_no_separa_de_la_misma_letra(): void
    {
        $this->assertSame(0, OrdenAlfabeticoEstudiante::comparar('Cáceres', 'Ana', 'Caceres', 'Ana'));
        $this->assertTrue(OrdenAlfabeticoEstudiante::comparar('Álvarez', 'Ana', 'Benítez', 'Ana') < 0);
        $this->assertTrue(OrdenAlfabeticoEstudiante::comparar('Álvarez', 'Ana', 'Zabaleta', 'Ana') < 0);
    }

    public function test_enye_va_despues_de_n_y_antes_de_o(): void
    {
        $this->assertTrue(OrdenAlfabeticoEstudiante::comparar('Nuñez', 'Ana', 'Ñancucheo', 'Ana') < 0);
        $this->assertTrue(OrdenAlfabeticoEstudiante::comparar('Ñancucheo', 'Ana', 'Olmos', 'Ana') < 0);
    }

    public function test_desempate_por_nombre(): void
    {
        $this->assertTrue(OrdenAlfabeticoEstudiante::comparar('Pérez', 'Ana', 'Pérez', 'Juan') < 0);
        $this->assertTrue(OrdenAlfabeticoEstudiante::comparar('Pérez', 'Juan', 'Pérez', 'Ana') > 0);
    }

    public function test_sql_usa_collation_espanola(): void
    {
        $sql = OrdenAlfabeticoEstudiante::sql('legajos.apellido');

        $this->assertStringContainsString('CONVERT(legajos.apellido USING utf8)', $sql);
        $this->assertStringContainsString('utf8_spanish_ci', $sql);
    }

    public function test_sql_rechaza_columna_invalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OrdenAlfabeticoEstudiante::sql('apellido; DROP TABLE legajos');
    }
}
