<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeComprobante448;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionIdentUsuario448Nuevo;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionIdClienteExtendido;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionLinea;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionResolucion448;
use App\Support\Cuotas\Siro\SiroIdFactura;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionIdentUsuario448NuevoTest extends TestCase
{
    public function test_armar_y_parsear_identificador_nuevo(): void
    {
        $ident = SiroDescargaRendicionIdentUsuario448Nuevo::armar(86, 3284, 3);
        $this->assertSame('008600328403086', $ident);

        $parsed = SiroDescargaRendicionIdentUsuario448Nuevo::parse($ident);
        $this->assertNotNull($parsed);
        $this->assertSame(86, $parsed['idCuotas']);
        $this->assertSame(3284, $parsed['idLegajos']);
        $this->assertSame(3, $parsed['ultUpload']);
        $this->assertSame(
            SiroIdFactura::generar(3284, 86, 3),
            SiroDescargaRendicionIdentUsuario448Nuevo::idFacturaDesdeParse($parsed),
        );
    }

    public function test_rechaza_formato_anterior_como_nuevo(): void
    {
        $this->assertNull(SiroDescargaRendicionIdentUsuario448Nuevo::parse('103284086000000'));
    }

    public function test_resolucion_prioriza_id_cliente_extendido_nuevo(): void
    {
        $identNuevo = SiroDescargaRendicionIdentUsuario448Nuevo::armar(86, 3284, 2);
        $barcodeLegacy = '04481032840860000002606120010980000140012200000515001102925';
        $cadena = str_repeat(' ', 272)
            .substr($identNuevo, 0, 1)
            .str_repeat(' ', 100)
            .substr($identNuevo, 1);

        $linea = [
            'codigoBarras' => $barcodeLegacy,
            'cadenaPago' => $cadena,
            'idComprobante' => '00000000000000000000',
            'idUsuario' => '86000000',
            'concepto' => '0',
        ];

        $res = SiroDescargaRendicionResolucion448::resolver($linea);
        $this->assertSame(SiroDescargaRendicionResolucion448::MODALIDAD_NUEVA, $res['modalidad']);
        $this->assertSame(
            SiroIdFactura::generar(3284, 86, 2),
            $res['idFactura'],
        );
    }

    public function test_resolucion_legacy_cuando_cola_no_es_formato_nuevo(): void
    {
        $identLegacy = SiroDescargaRendicionBarcodeComprobante448::armarIdentUsuarioLegacy('1', 3134, 86, 1);
        $barcode = '0448'.$identLegacy.'26061200109800001400122000000515001102925';
        $cadena = str_repeat(' ', 272)
            .'1'
            .str_repeat(' ', 100)
            .substr($identLegacy, 1);

        $linea = [
            'codigoBarras' => $barcode,
            'cadenaPago' => $cadena,
            'idComprobante' => '00000000000000000000',
            'idUsuario' => '86000000',
            'concepto' => '0',
        ];

        $res = SiroDescargaRendicionResolucion448::resolver($linea);
        $this->assertSame(SiroDescargaRendicionResolucion448::MODALIDAD_LEGACY, $res['modalidad']);
        $this->assertSame(
            SiroIdFactura::generar(3134, 86, 1),
            $res['idFactura'],
        );
    }

    public function test_cola_recompone_ident_nuevo_sin_primer_digito(): void
    {
        $ident = SiroDescargaRendicionIdentUsuario448Nuevo::armar(86, 3284, 1);
        $cadena = str_repeat(' ', 272)
            .substr($ident, 0, 1)
            .str_repeat(' ', 100)
            .substr($ident, 1);

        $this->assertSame($ident, SiroDescargaRendicionIdClienteExtendido::desdeColaLinea($cadena));
    }

    public function test_barcode_emision_usa_formato_nuevo(): void
    {
        $ident = SiroDescargaRendicionIdentUsuario448Nuevo::armar(86, 3284, 1);
        $barcode = '0448'.$ident.'26061200109800001400122000000515001102925';
        $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($barcode);
        $this->assertNull($parsed);
    }
}
