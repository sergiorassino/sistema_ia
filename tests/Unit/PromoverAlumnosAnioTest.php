<?php

namespace Tests\Unit;

use App\Support\Configuracion\PromoverAlumnosAnio;
use App\Support\NivelSistema;
use PHPUnit\Framework\TestCase;

class PromoverAlumnosAnioTest extends TestCase
{
    public function test_clave_division_normaliza_c_y_s(): void
    {
        $this->assertSame('3|1|A', PromoverAlumnosAnio::claveDivision(3, '1', 'A'));
        $this->assertSame('3|1|A', PromoverAlumnosAnio::claveDivision(3, ' 1 ', ' A '));
        $this->assertSame('2||', PromoverAlumnosAnio::claveDivision(2, null, null));
    }

    public function test_curso_numero(): void
    {
        $this->assertSame(5, PromoverAlumnosAnio::cursoNumero('5'));
        $this->assertSame(5, PromoverAlumnosAnio::cursoNumero(' 5 '));
        $this->assertSame(0, PromoverAlumnosAnio::cursoNumero(null));
    }

    public function test_niveles_validos_excluye_administracion_y_ceros(): void
    {
        $this->assertSame(
            [1, 2, 3],
            PromoverAlumnosAnio::nivelesValidos([1, 2, 2, 3, NivelSistema::ADMINISTRACION, 0, '']),
        );
        $this->assertSame([], PromoverAlumnosAnio::nivelesValidos([]));
    }

    public function test_jardin_cinco_pasa_a_primer_grado(): void
    {
        $this->assertSame(
            ['idNivel' => NivelSistema::PRIMARIO, 'c' => 1],
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::INICIAL, 5, 6, 3),
        );
        $this->assertSame(
            ['idNivel' => NivelSistema::INICIAL, 'c' => 5],
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::INICIAL, 4, 6, 3),
        );
    }

    public function test_sexto_grado_pasa_a_primer_anio(): void
    {
        $this->assertSame(
            ['idNivel' => NivelSistema::SECUNDARIO, 'c' => 1],
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::PRIMARIO, 6, 6, 3),
        );
        $this->assertSame(
            ['idNivel' => NivelSistema::PRIMARIO, 'c' => 6],
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::PRIMARIO, 5, 6, 3),
        );
    }

    public function test_secundario_sexto_no_promueve_y_quinto_si(): void
    {
        $this->assertNull(
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::SECUNDARIO, 6, 6, 3),
        );
        $this->assertTrue(
            PromoverAlumnosAnio::esCursoTerminal(NivelSistema::SECUNDARIO, 6, 6, 3),
        );
        $this->assertSame(
            ['idNivel' => NivelSistema::SECUNDARIO, 'c' => 6],
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::SECUNDARIO, 5, 6, 3),
        );
        $this->assertFalse(
            PromoverAlumnosAnio::esCursoTerminal(NivelSistema::SECUNDARIO, 5, 6, 3),
        );
    }

    public function test_epq_quinto_anio_es_terminal_y_cuarto_promueve_a_quinto(): void
    {
        $this->assertNull(
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::SECUNDARIO, 5, 5, 3),
        );
        $this->assertTrue(
            PromoverAlumnosAnio::esCursoTerminal(NivelSistema::SECUNDARIO, 5, 5, 3),
        );
        $this->assertSame(
            ['idNivel' => NivelSistema::SECUNDARIO, 'c' => 5],
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::SECUNDARIO, 4, 5, 3),
        );
        $this->assertNull(
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::SECUNDARIO, 6, 5, 3),
        );
        $this->assertTrue(
            PromoverAlumnosAnio::esCursoTerminal(NivelSistema::SECUNDARIO, 6, 5, 3),
        );
    }

    public function test_adultos_tercer_anio_no_promueve(): void
    {
        $this->assertNull(
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::ADULTOS, 3, 6, 3),
        );
        $this->assertSame(
            ['idNivel' => NivelSistema::ADULTOS, 'c' => 3],
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::ADULTOS, 2, 6, 3),
        );
    }

    public function test_mismo_nivel_incrementa_curso(): void
    {
        $this->assertSame(
            ['idNivel' => NivelSistema::SECUNDARIO, 'c' => 2],
            PromoverAlumnosAnio::destinoPedagogico(NivelSistema::SECUNDARIO, 1, 6, 3),
        );
    }
}
