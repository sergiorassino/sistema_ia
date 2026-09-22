<?php

namespace Tests\Unit;

use App\Support\Examenes\ActaVolantePrevios;
use PHPUnit\Framework\TestCase;

class ActaVolantePreviosTest extends TestCase
{
    public function test_clave_usa_id_matplan_cuando_existe(): void
    {
        $this->assertSame('88:RE:B-t1', ActaVolantePrevios::claveActa(88, 'RE', 'B-t1'));
        $this->assertSame('88:PR', ActaVolantePrevios::claveActa(88, 'PR'));
    }

    public function test_clave_cae_a_id_materias_si_no_hay_matplan(): void
    {
        $this->assertSame('m456:RE:B-t1', ActaVolantePrevios::claveActa(0, 'RE', 'B-t1', 456));
        $this->assertSame('m456:PR', ActaVolantePrevios::claveActa(0, 'PR', null, 456));
    }

    public function test_parse_acepta_matplan_y_materia(): void
    {
        $matplan = ActaVolantePrevios::parseClaveActa('88:RE:B-T1');
        $this->assertNotNull($matplan);
        $this->assertSame(88, $matplan['idMatPlan']);
        $this->assertSame(0, $matplan['idMaterias']);
        $this->assertSame('RE', $matplan['condAdeuda']);
        $this->assertSame('B-T1', $matplan['seccionKey']);

        $materia = ActaVolantePrevios::parseClaveActa('m456:RE:B-T1');
        $this->assertNotNull($materia);
        $this->assertSame(0, $materia['idMatPlan']);
        $this->assertSame(456, $materia['idMaterias']);
        $this->assertSame('RE', $materia['condAdeuda']);
    }

    public function test_id_matplan_prioriza_el_resuelto_desde_materias(): void
    {
        $fila = (object) [
            'idMatPlanResuelto' => 77,
            'matplan_id' => 77,
            'idMatPlan' => 0,
            'idMaterias' => 12,
            'matPlanMateria' => 'Matemática',
            'materia' => 'MATEMATICA',
        ];

        $this->assertSame(77, ActaVolantePrevios::idMatPlanDesdeFila($fila));
        $this->assertSame('MATEMÁTICA', ActaVolantePrevios::materiaLabelDesdeFila($fila));
    }

    public function test_id_matplan_cero_si_solo_hay_materia(): void
    {
        $fila = (object) [
            'idMatPlanResuelto' => 0,
            'idMatPlan' => 0,
            'idMaterias' => 12,
            'materia' => 'Matemática',
        ];

        $this->assertSame(0, ActaVolantePrevios::idMatPlanDesdeFila($fila));
        $this->assertSame('MATEMÁTICA', ActaVolantePrevios::materiaLabelDesdeFila($fila));
    }

    public function test_seccion_key_usa_letra_del_curso(): void
    {
        $this->assertSame('B-T0', ActaVolantePrevios::seccionKeyDesdeFilaCurso((object) [
            's' => 'B',
            'cursec' => 'CUARTO B',
            'c' => 4,
            'idTurnoClase' => 0,
        ]));
    }
}
