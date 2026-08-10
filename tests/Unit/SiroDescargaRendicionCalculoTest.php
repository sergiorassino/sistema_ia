<?php

namespace Tests\Unit;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\ImputacionPagoCalculo;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionCalculo;
use ReflectionClass;
use Tests\TestCase;

class SiroDescargaRendicionCalculoTest extends TestCase
{
    protected function tearDown(): void
    {
        ImputacionPagoCalculo::limpiarCacheFormulas();
        parent::tearDown();
    }

    public function test_pago_con_intereses_usa_capital_completo_y_deriva_recargo(): void
    {
        $this->inyectarFormula([
            'signo1' => '-', 'valor1' => 0, 'porcan1' => '%',
            'signo2' => '+', 'valor2' => 15.6, 'porcan2' => '%',
            'signo3' => '+', 'valor3' => 0, 'porcan3' => '%',
            'signo4' => '+', 'valor4' => 0, 'porcan4' => '%',
        ]);

        $cuota = new CuotaGenerada([
            'id' => 1,
            'idCuotas' => 10,
            'idCursos' => 20,
            'faltapa' => 108000.0,
            'venc1' => '2026-04-10',
            'venc2' => '2026-05-10',
            'venc3' => '2026-06-24',
        ]);

        $linea = [
            'importePagadoCentavos' => 12355200,
            'fechaPago' => '20260624',
        ];

        $resultado = SiroDescargaRendicionCalculo::calcular($linea, $cuota, null);

        $this->assertSame(108000.0, $resultado['importe']);
        $this->assertSame(123552.0, $resultado['pagado']);
        $this->assertSame(15552.0, $resultado['interes']);
        $this->assertSame(0.0, $resultado['bonificacion']);
        $this->assertSame([], $resultado['advertencias']);
    }

    public function test_pago_sin_recargo_usa_formula_directa(): void
    {
        $this->inyectarFormula([
            'signo1' => '-', 'valor1' => 0, 'porcan1' => '%',
            'signo2' => '+', 'valor2' => 0, 'porcan2' => '%',
            'signo3' => '+', 'valor3' => 0, 'porcan3' => '%',
            'signo4' => '+', 'valor4' => 0, 'porcan4' => '%',
        ]);

        $cuota = new CuotaGenerada([
            'id' => 2,
            'idCuotas' => 11,
            'idCursos' => 21,
            'faltapa' => 122000.0,
            'venc1' => '2026-06-30',
        ]);

        $linea = [
            'importePagadoCentavos' => 12200000,
            'fechaPago' => '20260620',
        ];

        $resultado = SiroDescargaRendicionCalculo::calcular($linea, $cuota, null);

        $this->assertSame(122000.0, $resultado['importe']);
        $this->assertSame(122000.0, $resultado['pagado']);
        $this->assertSame(0.0, $resultado['interes']);
        $this->assertSame([], $resultado['advertencias']);
    }

    public function test_respeta_saldo_pagar_del_cupon(): void
    {
        $this->inyectarFormula([
            'signo1' => '-', 'valor1' => 0, 'porcan1' => '%',
            'signo2' => '+', 'valor2' => 0, 'porcan2' => '%',
            'signo3' => '+', 'valor3' => 0, 'porcan3' => '%',
            'signo4' => '+', 'valor4' => 0, 'porcan4' => '%',
        ]);

        $cuota = new CuotaGenerada([
            'id' => 3,
            'idCuotas' => 12,
            'idCursos' => 22,
            'faltapa' => 50000.0,
            'venc1' => '2026-06-30',
        ]);

        $cupon = new CuponAPagar([
            'saldo_pagar' => 40000.0,
        ]);

        $linea = [
            'importePagadoCentavos' => 4000000,
            'fechaPago' => '20260620',
        ];

        $resultado = SiroDescargaRendicionCalculo::calcular($linea, $cuota, $cupon);

        $this->assertSame(40000.0, $resultado['importe']);
        $this->assertSame(40000.0, $resultado['pagado']);
    }

    public function test_cuota_ya_saldada_imputa_capital_completo_como_pago_doble(): void
    {
        $cuota = new CuotaGenerada([
            'id' => 4,
            'idCuotas' => 13,
            'idCursos' => 23,
            'faltapa' => 0.0,
            'venc1' => '2026-06-30',
        ]);

        $linea = [
            'importePagadoCentavos' => 10980000,
            'fechaPago' => '20260806',
        ];

        $resultado = SiroDescargaRendicionCalculo::calcular($linea, $cuota, null);

        $this->assertSame(109800.0, $resultado['importe']);
        $this->assertSame(109800.0, $resultado['pagado']);
        $this->assertSame(0.0, $resultado['interes']);
        $this->assertSame(0.0, $resultado['bonificacion']);
        $this->assertContains(
            'La cuota ya estaba saldada al descargar; posible pago doble.',
            $resultado['advertencias'],
        );
    }

    /**
     * @param  array<string, mixed>  $formula
     */
    private function inyectarFormula(array $formula): void
    {
        $ref = new ReflectionClass(ImputacionPagoCalculo::class);
        $prop = $ref->getProperty('formulaCache');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            '10:20' => $formula,
            '11:21' => $formula,
            '12:22' => $formula,
        ]);
    }
}
