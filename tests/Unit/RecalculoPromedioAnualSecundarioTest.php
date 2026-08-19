<?php

namespace Tests\Unit;

use App\Support\CalificacionesSecundario\RecalculoPromedioAnualSecundario;
use PHPUnit\Framework\TestCase;

class RecalculoPromedioAnualSecundarioTest extends TestCase
{
    public function test_promedio_con_modulos_aprobados(): void
    {
        $row = $this->filaVacia();
        $row['ic01'] = '8';
        $row['ic04'] = '9';
        $row['ic07'] = '7';

        $this->assertSame('8.00', RecalculoPromedioAnualSecundario::califDesdeFilaModulos($row));
    }

    public function test_sin_promedio_si_un_modulo_desaprueba(): void
    {
        $row = $this->filaVacia();
        $row['ic01'] = '8';
        $row['ic04'] = '6';

        $this->assertSame('', RecalculoPromedioAnualSecundario::califDesdeFilaModulos($row));
    }

    public function test_toma_el_maximo_del_modulo(): void
    {
        $row = $this->filaVacia();
        $row['ic01'] = '5';
        $row['ic02'] = '8';

        $this->assertSame('8.00', RecalculoPromedioAnualSecundario::califDesdeFilaModulos($row));
    }

    public function test_diez_sin_decimales(): void
    {
        $row = $this->filaVacia();
        $row['ic01'] = '10';
        $row['ic04'] = '10';

        $this->assertSame('10', RecalculoPromedioAnualSecundario::califDesdeFilaModulos($row));
    }

    public function test_omite_coloquio_aprobado(): void
    {
        $this->assertTrue(RecalculoPromedioAnualSecundario::omitirPorColoquioAprobado([
            'dic' => '7',
            'feb' => '',
        ]));
        $this->assertTrue(RecalculoPromedioAnualSecundario::omitirPorColoquioAprobado([
            'dic' => '6',
            'feb' => '8',
        ]));
        $this->assertFalse(RecalculoPromedioAnualSecundario::omitirPorColoquioAprobado([
            'dic' => '6',
            'feb' => '',
        ]));
    }

    /** @return array<string, string> */
    private function filaVacia(): array
    {
        $row = [];
        for ($i = 1; $i <= 28; $i++) {
            $row[sprintf('ic%02d', $i)] = '';
        }

        return $row;
    }
}
