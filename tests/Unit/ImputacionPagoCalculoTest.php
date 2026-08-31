<?php

namespace Tests\Unit;

use App\Models\CuotaGenerada;
use App\Support\Cuotas\ImputacionPagoCalculo;
use Carbon\Carbon;
use Tests\TestCase;

class ImputacionPagoCalculoTest extends TestCase
{
    protected function tearDown(): void
    {
        ImputacionPagoCalculo::limpiarCacheFormulas();
        parent::tearDown();
    }

    public function test_pago_entre_1er_y_2do_vencimiento_usa_p_mensual_no_diez_pesos(): void
    {
        $registro = $this->cuotaCscMayoConVencimientoActualizado();
        $this->definirFormulaCsc();

        $calc = ImputacionPagoCalculo::calcular(
            $registro,
            90000.0,
            Carbon::parse('2026-05-29')->startOfDay(),
            null,
        );

        $this->assertSame('2', $calc['tramo']);
        $this->assertSame('p', $calc['porcan']);
        $this->assertSame(1, $calc['mesesMora']);
        $this->assertSame(9000.0, $calc['interes']);
        $this->assertSame(99000.0, $calc['aPagar']);
    }

    public function test_pago_en_vencimiento_actualizado_usa_formula_despues_del_3er(): void
    {
        $registro = $this->cuotaCscMayoConVencimientoActualizado();
        $this->definirFormulaCsc();

        $calc = ImputacionPagoCalculo::calcular(
            $registro,
            90000.0,
            Carbon::parse('2026-08-15')->startOfDay(),
            null,
        );

        $meses = ImputacionPagoCalculo::mesesMoraAcumuladaDesdeVenc1(
            Carbon::parse('2026-05-16')->startOfDay(),
            Carbon::parse('2026-08-15')->startOfDay(),
        );

        $this->assertSame('4', $calc['tramo']);
        $this->assertSame('p', $calc['porcan']);
        $this->assertSame($meses, $calc['mesesMora']);
        $this->assertSame(round(90000.0 * 10.0 * $meses / 100.0, 2), $calc['interes']);
    }

    public function test_antes_del_1er_vencimiento_de_plantilla_no_hay_interes(): void
    {
        $registro = $this->cuotaCscMayoConVencimientoActualizado();
        $this->definirFormulaCsc();

        $calc = ImputacionPagoCalculo::calcular(
            $registro,
            90000.0,
            Carbon::parse('2026-05-10')->startOfDay(),
            null,
        );

        $this->assertSame('1', $calc['tramo']);
        $this->assertSame('$', $calc['porcan']);
        $this->assertSame(0.0, $calc['interes']);
        $this->assertSame(90000.0, $calc['aPagar']);
    }

    public function test_diez_por_ciento_manual_en_tramo_al_dia_no_se_convierte_en_diez_pesos(): void
    {
        $registro = $this->cuotaCscMayoConVencimientoActualizado();
        $this->definirFormulaCsc();

        $calc = ImputacionPagoCalculo::calcular(
            $registro,
            90000.0,
            Carbon::parse('2026-05-10')->startOfDay(),
            10.0,
        );

        $this->assertSame(9000.0, $calc['interes']);
        $this->assertSame(99000.0, $calc['aPagar']);
        $this->assertSame('%', $calc['porcan']);
    }

    public function test_monto_fijo_no_cero_sigue_en_pesos(): void
    {
        $registro = $this->cuotaCscMayoConVencimientoActualizado();
        ImputacionPagoCalculo::definirVenc1Plantilla(105, '2026-05-16');
        ImputacionPagoCalculo::definirFormula(105, 189, [
            'signo1' => '+',
            'valor1' => 500.0,
            'porcan1' => '$',
            'signo2' => '+',
            'valor2' => 10.0,
            'porcan2' => 'p',
            'signo3' => '+',
            'valor3' => 10.0,
            'porcan3' => 'p',
            'signo4' => '+',
            'valor4' => 10.0,
            'porcan4' => 'p',
        ]);

        $calc = ImputacionPagoCalculo::calcular(
            $registro,
            90000.0,
            Carbon::parse('2026-05-10')->startOfDay(),
            500.0,
        );

        $this->assertSame('$', $calc['porcan']);
        $this->assertSame(500.0, $calc['interes']);
        $this->assertSame(90500.0, $calc['aPagar']);
    }

    private function cuotaCscMayoConVencimientoActualizado(): CuotaGenerada
    {
        return new CuotaGenerada([
            'idCuotas' => 105,
            'idCursos' => 189,
            'venc1' => '2026-08-15',
            'venc2' => '2026-05-31',
            'venc3' => '2026-05-31',
            'nueVenc' => '2026-08-15',
            'faltapa' => 90000,
        ]);
    }

    private function definirFormulaCsc(): void
    {
        ImputacionPagoCalculo::definirVenc1Plantilla(105, '2026-05-16');
        ImputacionPagoCalculo::definirFormula(105, 189, [
            'signo1' => '+',
            'valor1' => 0.0,
            'porcan1' => '$',
            'signo2' => '+',
            'valor2' => 10.0,
            'porcan2' => 'p',
            'signo3' => '+',
            'valor3' => 10.0,
            'porcan3' => 'p',
            'signo4' => '+',
            'valor4' => 10.0,
            'porcan4' => 'p',
        ]);
    }
}
