<?php

namespace Tests\Unit;

use App\Models\Ento;
use App\Support\Cuotas\Siro\SiroCodigoPagoElectronico;
use App\Support\Cuotas\Siro\SiroConfiguracionIncompletaException;
use Tests\TestCase;

class SiroCodigoPagoElectronicoTest extends TestCase
{
    public function test_prefijo_sin_configuracion_lanza_excepcion(): void
    {
        $this->expectException(SiroConfiguracionIncompletaException::class);

        SiroCodigoPagoElectronico::prefijoDosDigitos(new Ento(['siroSecu' => '12']));
    }

    public function test_prefijo_desde_siro_prefijo_cpe_del_ento(): void
    {
        $ento = new Ento(['siroPrefijoCPE' => '09', 'siroSecu' => '12']);

        $this->assertSame('09', SiroCodigoPagoElectronico::prefijoDosDigitos($ento));
    }

    public function test_no_usa_siro_secu_como_fallback_de_prefijo(): void
    {
        $ento = new Ento(['siroSecu' => '09', 'siroIdentCuenta' => '5150011052']);

        $this->assertSame(['Prefijo CPE'], SiroCodigoPagoElectronico::faltantesParaCpe($ento));
    }

    public function test_prefijos_distintos_por_nivel_de_ento(): void
    {
        $entoInicial = new Ento(['siroPrefijoCPE' => '00', 'siroIdentCuenta' => '5150011052']);
        $entoSecundario = new Ento(['siroPrefijoCPE' => '09', 'siroIdentCuenta' => '5150011052']);

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

    public function test_faltantes_para_operacion_incluye_mensaje(): void
    {
        $ento = new Ento([
            'siroPrefijoCPE' => '09',
            'siroIdentCuenta' => '5150011052',
        ]);

        $this->assertSame(
            ['Mensaje en ticket / pantalla SIRO'],
            SiroCodigoPagoElectronico::faltantesParaOperacion($ento),
        );
    }

    public function test_cuenta_solo_ceros_se_considera_faltante(): void
    {
        $ento = new Ento([
            'siroPrefijoCPE' => '09',
            'siroIdentCuenta' => '0000000000',
            'siroMje' => 'COLEGIO',
        ]);

        $this->assertSame(
            ['Cuenta recaudadora SIRO'],
            SiroCodigoPagoElectronico::faltantesParaCpe($ento),
        );
    }
}
