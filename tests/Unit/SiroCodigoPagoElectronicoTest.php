<?php

namespace Tests\Unit;

use App\Models\Ento;
use App\Support\Cuotas\Siro\SiroCodigoPagoElectronico;
use Tests\TestCase;

class SiroCodigoPagoElectronicoTest extends TestCase
{
    public function test_prefijo_default_00_sin_ento_ni_tenant(): void
    {
        config(['tenant.cuotas.siro.habilitado' => false]);

        $this->assertSame('00', SiroCodigoPagoElectronico::prefijoDosDigitos(null));
    }

    public function test_prefijo_desde_siro_prefijo_cpe_del_ento(): void
    {
        $ento = new Ento(['siroPrefijoCPE' => '09', 'siroSecu' => '12']);

        $this->assertSame('09', SiroCodigoPagoElectronico::prefijoDosDigitos($ento));
    }

    public function test_prefijo_desde_siro_secu_del_ento_si_no_hay_prefijo_cpe(): void
    {
        $ento = new Ento(['siroSecu' => '09']);

        $this->assertSame('09', SiroCodigoPagoElectronico::prefijoDosDigitos($ento));
    }

    public function test_prefijos_distintos_por_nivel_de_ento(): void
    {
        config(['tenant.cuotas.siro.habilitado' => false]);

        $entoInicial = new Ento(['siroPrefijoCPE' => '00']);
        $entoSecundario = new Ento(['siroPrefijoCPE' => '09']);

        $this->assertSame('00', SiroCodigoPagoElectronico::prefijoDosDigitos($entoInicial));
        $this->assertSame('09', SiroCodigoPagoElectronico::prefijoDosDigitos($entoSecundario));
        $this->assertSame(
            '000000123',
            SiroCodigoPagoElectronico::bloqueLegajoNueveDigitosDesdeEnto(123, $entoInicial),
        );
        $this->assertSame(
            '090000123',
            SiroCodigoPagoElectronico::bloqueLegajoNueveDigitosDesdeEnto(123, $entoSecundario),
        );
    }

    public function test_bloque_legajo_san_fra_coincide_con_archivo_legacy(): void
    {
        $entoNivel = new Ento(['siroPrefijoCPE' => '09', 'siroIdentCuenta' => '5150011052']);

        $bloque = SiroCodigoPagoElectronico::bloqueLegajoNueveDigitosDesdeEnto(3054, $entoNivel);

        $this->assertSame('090003054', $bloque);
        $this->assertSame('0900030545150011052', $bloque.'5150011052');
        $this->assertSame(19, strlen($bloque.'5150011052'));
    }
}
