<?php

namespace Tests\Unit;

use App\Support\StudentContext;
use PHPUnit\Framework\TestCase;

class StudentContextCicloAutogestionTest extends TestCase
{
    public function test_preinscripcion_en_otro_nivel_no_desplaza_el_ciclo_abierto(): void
    {
        $autorizados = [
            1 => 10, // inicial, ciclo 2026
            2 => 10, // primario, ciclo 2026
            3 => 10, // secundario, ciclo 2026
        ];

        $matriculas = [
            ['id' => 100, 'idNivel' => 2, 'idTerlec' => 10, 'ano' => 2026],
            ['id' => 200, 'idNivel' => 3, 'idTerlec' => 11, 'ano' => 2027],
        ];

        $elegida = StudentContext::elegirMatriculaCicloAutogestion(0, $matriculas, $autorizados);

        $this->assertNotNull($elegida);
        $this->assertSame(2, $elegida['idNivel']);
        $this->assertSame(10, $elegida['idTerlec']);
        $this->assertSame(2026, $elegida['ano']);
    }

    public function test_sala_de_5_inscripta_en_primero_de_primario_entra_al_ciclo_abierto(): void
    {
        $autorizados = [
            1 => 10,
            2 => 10,
        ];

        $matriculas = [
            ['id' => 50, 'idNivel' => 1, 'idTerlec' => 10, 'ano' => 2026],
            ['id' => 80, 'idNivel' => 2, 'idTerlec' => 11, 'ano' => 2027],
        ];

        $elegida = StudentContext::elegirMatriculaCicloAutogestion(2, $matriculas, $autorizados);

        $this->assertNotNull($elegida);
        $this->assertSame(1, $elegida['idNivel']);
        $this->assertSame(10, $elegida['idTerlec']);
    }

    public function test_cuando_el_ciclo_abierto_pasa_al_anio_nuevo_usa_esa_matricula(): void
    {
        $autorizados = [
            2 => 11,
            3 => 11,
        ];

        $matriculas = [
            ['id' => 100, 'idNivel' => 2, 'idTerlec' => 10, 'ano' => 2026],
            ['id' => 200, 'idNivel' => 3, 'idTerlec' => 11, 'ano' => 2027],
        ];

        $elegida = StudentContext::elegirMatriculaCicloAutogestion(2, $matriculas, $autorizados);

        $this->assertNotNull($elegida);
        $this->assertSame(3, $elegida['idNivel']);
        $this->assertSame(11, $elegida['idTerlec']);
        $this->assertSame(2027, $elegida['ano']);
    }

    public function test_sin_matricula_en_el_ciclo_abierto_usa_la_posterior_si_existe(): void
    {
        $autorizados = [
            3 => 10,
        ];

        $matriculas = [
            ['id' => 200, 'idNivel' => 3, 'idTerlec' => 11, 'ano' => 2027],
        ];

        $elegida = StudentContext::elegirMatriculaCicloAutogestion(0, $matriculas, $autorizados, [3 => 2026]);

        $this->assertNotNull($elegida);
        $this->assertSame(200, $elegida['id']);
        $this->assertSame(11, $elegida['idTerlec']);
    }

    public function test_ingreso_nuevo_solo_con_matricula_posterior_entra_a_ese_ciclo(): void
    {
        $autorizados = [
            1 => 10,
            2 => 10,
            3 => 10,
        ];
        $anosAbiertos = [
            1 => 2026,
            2 => 2026,
            3 => 2026,
        ];

        $matriculas = [
            ['id' => 400, 'idNivel' => 2, 'idTerlec' => 11, 'ano' => 2027],
        ];

        $elegida = StudentContext::elegirMatriculaCicloAutogestion(0, $matriculas, $autorizados, $anosAbiertos);

        $this->assertNotNull($elegida);
        $this->assertSame(400, $elegida['id']);
        $this->assertSame(2, $elegida['idNivel']);
        $this->assertSame(11, $elegida['idTerlec']);
        $this->assertSame(2027, $elegida['ano']);
    }

    public function test_matricula_de_un_ciclo_anterior_no_habilita_ingreso(): void
    {
        $autorizados = [
            3 => 10,
        ];
        $anosAbiertos = [
            3 => 2026,
        ];

        $matriculas = [
            ['id' => 10, 'idNivel' => 3, 'idTerlec' => 8, 'ano' => 2025],
        ];

        $this->assertNull(
            StudentContext::elegirMatriculaCicloAutogestion(3, $matriculas, $autorizados, $anosAbiertos)
        );
    }

    public function test_mismo_anio_en_dos_niveles_prioriza_el_nivel_del_legajo(): void
    {
        $autorizados = [
            2 => 10,
            3 => 10,
        ];

        $matriculas = [
            ['id' => 100, 'idNivel' => 2, 'idTerlec' => 10, 'ano' => 2026],
            ['id' => 300, 'idNivel' => 3, 'idTerlec' => 10, 'ano' => 2026],
        ];

        $elegida = StudentContext::elegirMatriculaCicloAutogestion(2, $matriculas, $autorizados);

        $this->assertNotNull($elegida);
        $this->assertSame(100, $elegida['id']);
        $this->assertSame(2, $elegida['idNivel']);
    }
}
