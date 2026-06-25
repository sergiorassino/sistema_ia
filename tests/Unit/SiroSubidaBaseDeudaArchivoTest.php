<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaArchivo;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SiroSubidaBaseDeudaArchivoTest extends TestCase
{
    public function test_contenido_listo_para_siro_sin_bom_crlf_y_280_bytes_por_linea(): void
    {
        $cabecera = str_repeat('0', SiroSubidaBaseDeudaArchivo::LARGO_LINEA);
        $pie = str_repeat('9', SiroSubidaBaseDeudaArchivo::LARGO_LINEA);

        $contenido = SiroSubidaBaseDeudaArchivo::contenidoListoParaSiro([$cabecera, $pie]);

        $this->assertFalse(str_starts_with($contenido, "\xEF\xBB\xBF"));
        $this->assertSame($cabecera."\r\n".$pie."\r\n", $contenido);

        $lineas = explode("\r\n", $contenido);
        array_pop($lineas);

        foreach ($lineas as $linea) {
            $this->assertSame(SiroSubidaBaseDeudaArchivo::LARGO_LINEA, strlen($linea));
            $this->assertSame(1, preg_match('/^[\x20-\x7E]+$/', $linea));
        }
    }

    public function test_cabecera_y_pie_coinciden_con_legacy_scriptcase(): void
    {
        $cabecera = '0'.'400'.'0000'.'20260625'.str_repeat('0', 264);
        $pie = '9'.'400'.'0000'.'20260625'.'0000001'.'0000000'.'00000499500'.str_repeat('0', 239);

        $this->assertSame(SiroSubidaBaseDeudaArchivo::LARGO_LINEA, strlen($cabecera));
        $this->assertSame(SiroSubidaBaseDeudaArchivo::LARGO_LINEA, strlen($pie));
        $this->assertSame('0', $cabecera[16], 'Pos. 17 debe ser cero (legacy), no flag Full');

        $contenido = SiroSubidaBaseDeudaArchivo::contenidoListoParaSiro([$cabecera, $pie]);

        $this->assertSame($cabecera."\r\n".$pie."\r\n", $contenido);
    }

    public function test_fecha_siro_formatea_aaaammdd(): void
    {
        $fecha = Carbon::parse('2026-06-12');

        $this->assertSame('20260612', SiroSubidaBaseDeudaArchivo::fechaSiro($fecha, true));
        $this->assertSame('00000000', SiroSubidaBaseDeudaArchivo::fechaSiro(null));
    }

    public function test_detalle_coloca_fecha_primer_vencimiento_en_posicion_legacy(): void
    {
        $detalle = [
            'cpe' => '0000025305150011052',
            'idFactura' => '00002530000008605086',
            'venc1' => Carbon::parse('2026-06-12'),
            'venc2' => Carbon::parse('2026-06-26'),
            'venc3' => Carbon::parse('2026-06-26'),
            'importe1' => 4995.0,
            'importe2' => 5550.0,
            'importe3' => 5705.4,
            'mensajeTicket1' => 'COLEGIO PARROQU',
            'mensajeTicket2' => str_pad('JUNIO 2026', 25, ' ', STR_PAD_RIGHT),
            'mensajePantalla' => 'COLEGIO PARROQU',
        ];

        $ref = new \ReflectionMethod(SiroSubidaBaseDeudaArchivo::class, 'lineaDetalle');
        $ref->setAccessible(true);
        $detalleLinea = $ref->invoke(null, $detalle, '20260625');

        $this->assertSame('5', $detalleLinea[0]);
        $this->assertSame('20260612', substr($detalleLinea, 41, 8));
        $this->assertSame('20260626', substr($detalleLinea, 60, 8));
        $this->assertSame('00000499500', substr($detalleLinea, 49, 11));
    }

    public function test_generar_valida_cabecera_legacy_sin_bom(): void
    {
        $refCabecera = new \ReflectionMethod(SiroSubidaBaseDeudaArchivo::class, 'lineaCabecera');
        $refCabecera->setAccessible(true);
        $refDetalle = new \ReflectionMethod(SiroSubidaBaseDeudaArchivo::class, 'lineaDetalle');
        $refDetalle->setAccessible(true);
        $refPie = new \ReflectionMethod(SiroSubidaBaseDeudaArchivo::class, 'lineaPie');
        $refPie->setAccessible(true);

        $detalle = [
            'cpe' => '0000025305150011052',
            'idFactura' => '00002530000008605086',
            'venc1' => Carbon::parse('2026-06-12'),
            'venc2' => Carbon::parse('2026-06-26'),
            'venc3' => Carbon::parse('2026-06-26'),
            'importe1' => 4995.0,
            'importe2' => 5550.0,
            'importe3' => 5705.4,
            'mensajeTicket1' => 'COLEGIO PARROQU',
            'mensajeTicket2' => str_pad('JUNIO 2026', 25, ' ', STR_PAD_RIGHT),
            'mensajePantalla' => 'COLEGIO PARROQU',
        ];

        $fechaTxt = '20260625';
        $lineas = [
            $refCabecera->invoke(null, $fechaTxt),
            $refDetalle->invoke(null, $detalle, $fechaTxt),
            $refPie->invoke(null, $fechaTxt, 1, 4995.0),
        ];
        $contenido = SiroSubidaBaseDeudaArchivo::contenidoListoParaSiro($lineas);

        $this->assertFalse(str_starts_with($contenido, "\xEF\xBB\xBF"));
        SiroSubidaBaseDeudaArchivo::validarContenidoParaSiro($contenido);

        $lineasArchivo = explode("\r\n", rtrim($contenido, "\r\n"));
        $this->assertSame('04000000202606250', substr($lineasArchivo[0], 0, 17));
        $this->assertSame('0', $lineasArchivo[0][16]);
        $this->assertSame('20260612', substr($lineasArchivo[1], 41, 8));
    }

    public function test_cabecera_archivo_legacy_san_fra_octubre_2025(): void
    {
        $f = 'd:/_enviar/09-setiembre/sanfra/30677609148.20251003';
        if (! is_readable($f)) {
            $this->markTestSkipped('Archivo de referencia San Fra no disponible en este entorno.');
        }

        $raw = file_get_contents($f);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $lineas = preg_split('/\r\n/', rtrim($raw, "\r\n"));
        $this->assertNotEmpty($lineas);

        SiroSubidaBaseDeudaArchivo::validarContenidoParaSiro(implode("\r\n", $lineas)."\r\n");

        $ref = new \ReflectionMethod(SiroSubidaBaseDeudaArchivo::class, 'lineaCabecera');
        $ref->setAccessible(true);
        $generada = $ref->invoke(null, substr($lineas[0], 8, 8));

        $this->assertSame($lineas[0], $generada);
    }

    public function test_bytes_para_descarga_rechaza_bom(): void
    {
        $cabecera = '0'.'400'.'0000'.'20260625'.str_repeat('0', 264);
        $pie = '9'.'400'.'0000'.'20260625'.'0000001'.'0000000'.'00000499500'.str_repeat('0', 239);
        $contenido = SiroSubidaBaseDeudaArchivo::contenidoListoParaSiro([$cabecera, $pie]);
        $conBom = "\xEF\xBB\xBF".$contenido;

        $bytes = SiroSubidaBaseDeudaArchivo::bytesParaDescarga($conBom);

        $this->assertSame($contenido, $bytes);
        $this->assertFalse(str_starts_with($bytes, "\xEF\xBB\xBF"));
    }

    public function test_rechaza_linea_con_longitud_incorrecta(): void
    {
        $this->expectException(\RuntimeException::class);
        SiroSubidaBaseDeudaArchivo::contenidoListoParaSiro([str_repeat('0', 279)]);
    }
}
