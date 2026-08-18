<?php

namespace Tests\Unit;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionMatchCuotaSinCupon448;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionProvisorios;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionMatchCuotaSinCupon448Test extends TestCase
{
    public function test_ids_desde_linea_legacy_448(): void
    {
        $ids = SiroDescargaRendicionMatchCuotaSinCupon448::idsDesdeLinea([
            'codigoBarras' => '04481013760880000002608130010980000140012200000515001102940',
            'cadenaPago' => str_repeat('0', 126),
            'idComprobante' => '',
        ]);

        $this->assertNotNull($ids);
        $this->assertSame(1376, $ids['idLegajos']);
        $this->assertSame(88, $ids['idCuotas']);
    }

    public function test_ids_desde_linea_formato_nuevo_en_cola(): void
    {
        $identNuevo = '008800137601088';
        $ids = SiroDescargaRendicionMatchCuotaSinCupon448::idsDesdeLinea([
            'codigoBarras' => '0448'.str_repeat('0', 55),
            'cadenaPago' => str_repeat('0', 373).$identNuevo,
            'idComprobante' => '',
        ]);

        $this->assertNotNull($ids);
        $this->assertSame(1376, $ids['idLegajos']);
        $this->assertSame(88, $ids['idCuotas']);
    }

    public function test_aplica_a_linea_solo_448(): void
    {
        $this->assertTrue(SiroDescargaRendicionMatchCuotaSinCupon448::aplicaALinea([
            'codigoBarras' => '0448101376088000000',
        ]));
        $this->assertFalse(SiroDescargaRendicionMatchCuotaSinCupon448::aplicaALinea([
            'codigoBarras' => '0449090001376260813',
        ]));
    }

    public function test_capital_prioriza_saldo_cupon_luego_faltapa(): void
    {
        $cuota = new CuotaGenerada(['faltapa' => 500.0, 'importe' => 800.0]);
        $cupon = new CuponAPagar(['saldo_pagar' => 109800.0]);

        $this->assertSame(
            109800.0,
            SiroDescargaRendicionMatchCuotaSinCupon448::capitalParaDesglose($cupon, $cuota),
        );
        $this->assertSame(
            500.0,
            SiroDescargaRendicionMatchCuotaSinCupon448::capitalParaDesglose(null, $cuota),
        );

        $cuotaSaldada = new CuotaGenerada(['faltapa' => 0.0, 'importe' => 800.0]);
        $this->assertSame(
            800.0,
            SiroDescargaRendicionMatchCuotaSinCupon448::capitalParaDesglose(null, $cuotaSaldada),
        );
    }

    public function test_aviso_formulario_menciona_448_y_autogestion(): void
    {
        $mensaje = SiroDescargaRendicionMatchCuotaSinCupon448::mensajeAvisoFormulario();

        $this->assertStringContainsString('provisorio 2', mb_strtolower($mensaje));
        $this->assertStringContainsString('448', $mensaje);
        $this->assertStringContainsString('cupones_a_pagar', mb_strtolower($mensaje));
    }

    public function test_provisorios_agregan_ambos_avisos(): void
    {
        $this->assertTrue(SiroDescargaRendicionProvisorios::hayAlgunoHabilitado());
        $mensajes = SiroDescargaRendicionProvisorios::mensajesAvisoFormulario();
        $this->assertGreaterThanOrEqual(2, count($mensajes));
        $texto = mb_strtolower(implode(' ', $mensajes));
        $this->assertStringContainsString('upload', $texto);
        $this->assertStringContainsString('448', $texto);
    }

    public function test_detalle_columna_sin_cupon_incluye_importe_archivo_y_cuota(): void
    {
        $cuota = new CuotaGenerada([
            'id' => 90,
            'idLegajos' => 1259,
            'idCuotas' => 84,
            'faltapa' => 120000.0,
        ]);

        $detalle = SiroDescargaRendicionProvisorios::detalleColumna([
            'matchTipo' => SiroDescargaRendicionMatchCuotaSinCupon448::MATCH_TIPO,
            'provisorioImporteArchivo' => true,
            'idFacturaBuscado' => '00001259000008412084',
            'cupon' => null,
            'cuotaGenerada' => $cuota,
            'pagadoArchivo' => 133056.0,
            'desglose' => [
                'importe' => 120000.0,
                'interes' => 13056.0,
                'bonificacion' => 0.0,
                'pagado' => 133056.0,
            ],
        ]);

        $this->assertNotNull($detalle);
        $this->assertStringStartsWith('PROVISORIO:', $detalle);
        $this->assertStringContainsString('id_factura archivo: 00001259000008412084', $detalle);
        $this->assertStringContainsString('Importe archivo: $133.056,00', $detalle);
        $this->assertStringContainsString('id_factura cupones_a_pagar: —', $detalle);
        $this->assertStringContainsString('importes cupones_a_pagar: 1v —  2v —  3v —', $detalle);
        $this->assertStringContainsString('RESOLVIENDO POR: provisorio 2 — 448 sin cupón en cupones_a_pagar', $detalle);
    }

    public function test_detalle_columna_importe_distinto_incluye_vencimientos_del_cupon(): void
    {
        $cupon = new CuponAPagar([
            'id_factura' => '00001735000008703087',
            'origen' => CuponAPagar::ORIGEN_SUBIDA_SIRO,
            'saldo_pagar' => 111000.0,
            'importe1venc' => 99900.0,
            'importe2venc' => 111000.0,
            'importe3venc' => 111000.0,
        ]);
        $cuota = new CuotaGenerada([
            'id' => 91,
            'idLegajos' => 1735,
            'idCuotas' => 87,
            'faltapa' => 111000.0,
        ]);

        $detalle = SiroDescargaRendicionProvisorios::detalleColumna([
            'matchTipo' => 'exacto',
            'provisorioImporteArchivo' => true,
            'idFacturaBuscado' => '00001735000008703087',
            'cupon' => $cupon,
            'cuotaGenerada' => $cuota,
            'pagadoArchivo' => 117216.0,
            'desglose' => [
                'importe' => 111000.0,
                'interes' => 6216.0,
                'bonificacion' => 0.0,
                'pagado' => 117216.0,
            ],
        ]);

        $this->assertNotNull($detalle);
        $this->assertStringStartsWith('PROVISORIO:', $detalle);
        $this->assertStringContainsString('id_factura archivo: 00001735000008703087', $detalle);
        $this->assertStringContainsString('Importe archivo: $117.216,00', $detalle);
        $this->assertStringContainsString('id_factura cupones_a_pagar: 00001735000008703087', $detalle);
        $this->assertStringContainsString('1v $99.900,00', $detalle);
        $this->assertStringContainsString('2v $111.000,00', $detalle);
        $this->assertStringContainsString('3v $111.000,00', $detalle);
        $this->assertStringContainsString('RESOLVIENDO POR: importe archivo distinto a vencimientos del cupón', $detalle);
    }

    public function test_detalle_columna_upload_cercano_incluye_metodo(): void
    {
        $cupon = new CuponAPagar([
            'id_factura' => '00001735000008702087',
            'importe1venc' => 99900.0,
            'importe2venc' => 111000.0,
            'importe3venc' => 111000.0,
        ]);

        $detalle = SiroDescargaRendicionProvisorios::detalleColumna([
            'matchTipo' => 'upload_cercano',
            'provisorioImporteArchivo' => false,
            'idFacturaBuscado' => '00001735000008703087',
            'cupon' => $cupon,
            'pagadoArchivo' => 99900.0,
        ]);

        $this->assertNotNull($detalle);
        $this->assertStringContainsString('id_factura archivo: 00001735000008703087', $detalle);
        $this->assertStringContainsString('id_factura cupones_a_pagar: 00001735000008702087', $detalle);
        $this->assertStringContainsString('RESOLVIENDO POR: provisorio 1 — upload cercano (449)', $detalle);
    }

    public function test_detalle_columna_null_si_no_es_provisorio(): void
    {
        $this->assertNull(SiroDescargaRendicionProvisorios::detalleColumna([
            'matchTipo' => 'exacto',
            'provisorioImporteArchivo' => false,
            'pagadoArchivo' => 1000.0,
        ]));
    }
}
