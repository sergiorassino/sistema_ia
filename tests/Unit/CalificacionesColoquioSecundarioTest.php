<?php

namespace Tests\Unit;

use App\Support\CalificacionesColoquioSecundario;
use PHPUnit\Framework\TestCase;

class CalificacionesColoquioSecundarioTest extends TestCase
{
    public function test_tea_de_la_materia_entra_en_diciembre_aunque_los_modulos_esten_aprobados(): void
    {
        $row = $this->fila(['tea' => 1, 'ic01' => '8', 'ic02' => '9']);

        $this->assertTrue(CalificacionesColoquioSecundario::apareceEnListadoColoquio('dic', $row, '8'));
        $this->assertSame('TEA', CalificacionesColoquioSecundario::motivoElegibilidad($row));
    }

    public function test_tea_de_la_materia_en_febrero_solo_si_no_aprobo_diciembre(): void
    {
        $row = $this->fila(['tea' => 1, 'ic01' => '8']);

        $this->assertFalse(CalificacionesColoquioSecundario::apareceEnListadoColoquio('feb', $row, '7'));
        $this->assertFalse(CalificacionesColoquioSecundario::apareceEnListadoColoquio('feb', $row, '10'));
        $this->assertTrue(CalificacionesColoquioSecundario::apareceEnListadoColoquio('feb', $row, '6'));
        $this->assertTrue(CalificacionesColoquioSecundario::apareceEnListadoColoquio('feb', $row, ''));
    }

    public function test_sin_tea_ni_modulo_desaprobado_no_entra(): void
    {
        $row = $this->fila(['tea' => 0, 'ic01' => '8', 'ic02' => '7']);

        $this->assertFalse(CalificacionesColoquioSecundario::apareceEnListadoColoquio('dic', $row, ''));
        $this->assertFalse(CalificacionesColoquioSecundario::apareceEnListadoColoquio('feb', $row, ''));
    }

    public function test_modulo_desaprobado_entra_en_diciembre_y_en_febrero_si_no_aprobo_dic(): void
    {
        $row = $this->fila(['tea' => 0, 'ic01' => '4']);

        $this->assertTrue(CalificacionesColoquioSecundario::apareceEnListadoColoquio('dic', $row, ''));
        $this->assertTrue(CalificacionesColoquioSecundario::apareceEnListadoColoquio('feb', $row, '5'));
        $this->assertFalse(CalificacionesColoquioSecundario::apareceEnListadoColoquio('feb', $row, '7'));
        $this->assertSame('Módulos desaprobados', CalificacionesColoquioSecundario::motivoElegibilidad($row));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fila(array $overrides): array
    {
        $row = ['tea' => 0];
        foreach ([
            'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
            'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
            'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28',
        ] as $campo) {
            $row[$campo] = '';
        }

        return array_merge($row, $overrides);
    }
}
