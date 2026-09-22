<?php

namespace Tests\Unit;

use App\Support\Examenes\MateriasAdeudadasCondicionRecalculo;
use App\Support\Examenes\MateriasAdeudadasFiltros;
use PHPUnit\Framework\TestCase;

class MateriasAdeudadasCondicionRecalculoTest extends TestCase
{
    public function test_eq_y_tm_no_se_tocan_aunque_el_alumno_sea_egresado(): void
    {
        $this->assertNull(MateriasAdeudadasCondicionRecalculo::decidirCondicion('EQ', false, true, true));
        $this->assertNull(MateriasAdeudadasCondicionRecalculo::decidirCondicion('tm', false, true, true));
        $this->assertNull(MateriasAdeudadasCondicionRecalculo::decidirCondicion('EQ', true, true, true));
    }

    public function test_regular_del_ciclo_actual_queda_previa(): void
    {
        $this->assertSame('PR', MateriasAdeudadasCondicionRecalculo::decidirCondicion('PR', true, false, true));
        $this->assertSame('PR', MateriasAdeudadasCondicionRecalculo::decidirCondicion('', true, true, true));
        $this->assertSame('PR', MateriasAdeudadasCondicionRecalculo::decidirCondicion('RE', true, true, true));
    }

    public function test_egresado_en_febrero_abril_julio_setiembre_queda_regular(): void
    {
        $this->assertSame('RE', MateriasAdeudadasCondicionRecalculo::decidirCondicion('PR', false, true, true));
        $this->assertSame('RE', MateriasAdeudadasCondicionRecalculo::decidirCondicion('', false, true, true));
    }

    public function test_egresado_fuera_de_ventana_o_diciembre_queda_previa(): void
    {
        $this->assertSame('PR', MateriasAdeudadasCondicionRecalculo::decidirCondicion('RE', false, true, false));
        $this->assertSame('PR', MateriasAdeudadasCondicionRecalculo::decidirCondicion('PR', false, false, true));
        $this->assertSame('PR', MateriasAdeudadasCondicionRecalculo::decidirCondicion('PR', false, false, false));
    }

    public function test_turno_ventana_febrero_abril_julio_setiembre(): void
    {
        $this->assertTrue(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('Febrero'));
        $this->assertTrue(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('FEB'));
        $this->assertTrue(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('Abril'));
        $this->assertTrue(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('Julio'));
        $this->assertTrue(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('Setiembre'));
        $this->assertTrue(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('SEPTIEMBRE'));
        $this->assertTrue(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('Turno 4 · Sep'));
    }

    public function test_turno_diciembre_y_desconocido_no_son_ventana_re(): void
    {
        $this->assertFalse(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('Diciembre'));
        $this->assertFalse(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('DIC'));
        $this->assertFalse(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado('Turno 5'));
        $this->assertFalse(MateriasAdeudadasCondicionRecalculo::turnoEsVentanaRegularEgresado(''));
    }

    public function test_filtros_reconocen_re_y_etiquetan_acta_y_matriz(): void
    {
        $this->assertSame('RE', MateriasAdeudadasFiltros::normalizeCondicion('re'));
        $this->assertContains('RE', MateriasAdeudadasFiltros::CONDICIONES);
        $this->assertSame('Regular', MateriasAdeudadasFiltros::tituloCondicionActa('RE'));
        $this->assertSame('Regular', MateriasAdeudadasFiltros::condCalificacionDesdeExamen('RE'));
        $this->assertSame('Prev.', MateriasAdeudadasFiltros::condCalificacionDesdeExamen('PR'));
        $this->assertSame('Equiv.', MateriasAdeudadasFiltros::condCalificacionDesdeExamen('EQ'));
        $this->assertSame('Regular', MateriasAdeudadasFiltros::condCalificacionDesdeExamen('TM'));
    }

    public function test_exam_todos_inscribe_regular_y_egresado_re_en_todas_las_materias(): void
    {
        $this->assertSame(
            ['condAdeuda' => 'PR', 'inscri' => 1],
            MateriasAdeudadasCondicionRecalculo::payloadActualizacion('PR', true, 'T'),
        );
        $this->assertSame(
            ['condAdeuda' => 'RE', 'inscri' => 1],
            MateriasAdeudadasCondicionRecalculo::payloadActualizacion('RE', false, 'T'),
        );
        $this->assertSame(
            ['condAdeuda' => 'PR'],
            MateriasAdeudadasCondicionRecalculo::payloadActualizacion('PR', false, 'T'),
        );
        $this->assertSame(
            ['condAdeuda' => 'RE'],
            MateriasAdeudadasCondicionRecalculo::payloadActualizacion('RE', false, 'F'),
        );
    }
}
