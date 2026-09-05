<?php

namespace Tests\Unit;

use App\Support\PlanillaResumenCalificacionesSecundario;
use PHPUnit\Framework\TestCase;

class PlanillaResumenContarReprobadasTest extends TestCase
{
    public function test_cuenta_materias_con_al_menos_un_modulo_menor_a_siete(): void
    {
        $alzari = [
            $this->fila(['ic04' => '5']),
            $this->fila(['ic13' => '4', 'ic16' => '5']),
            $this->fila(['ic01' => '8']),
        ];
        $this->assertSame(2, PlanillaResumenCalificacionesSecundario::contarReprobadas($alzari));

        $balquinta = [
            $this->fila(['ic16' => '3']),
            $this->fila(['ic04' => '3']),
            $this->fila(['ic01' => '5']),
        ];
        $this->assertSame(3, PlanillaResumenCalificacionesSecundario::contarReprobadas($balquinta));
    }

    public function test_no_cuenta_si_el_modulo_se_aprobo_con_recuperatorio(): void
    {
        $filas = [
            $this->fila(['ic01' => '5', 'ic02' => '8']),
        ];
        $this->assertSame(0, PlanillaResumenCalificacionesSecundario::contarReprobadas($filas));
    }

    public function test_no_usa_el_promedio_anual_calif(): void
    {
        $filas = [
            $this->fila(['ic01' => '8', 'ic04' => '9', 'calif' => '6']),
            $this->fila(['ic01' => '5', 'calif' => '']),
        ];
        $this->assertSame(1, PlanillaResumenCalificacionesSecundario::contarReprobadas($filas));
    }

    public function test_dos_modulos_bajos_en_la_misma_materia_cuentan_una(): void
    {
        $filas = [
            $this->fila(['ic01' => '4', 'ic13' => '5']),
        ];
        $this->assertSame(1, PlanillaResumenCalificacionesSecundario::contarReprobadas($filas));
    }

    /**
     * @param  array<string, string>  $notas
     * @return array<string, string>
     */
    private function fila(array $notas): array
    {
        $row = ['dic' => '', 'feb' => '', 'calif' => ''];
        for ($i = 1; $i <= 28; $i++) {
            $row[sprintf('ic%02d', $i)] = '';
        }

        return array_merge($row, $notas);
    }
}
