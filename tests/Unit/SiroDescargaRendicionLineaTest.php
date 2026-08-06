<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionLinea;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionLineaTest extends TestCase
{
    public function test_parsear_linea_integrado_minima(): void
    {
        $base = '202606162026061820260612000055500008600000000448102565086000000260612000499500014000555000051500110529300000000000000000000PF';
        $linea = str_pad($base, SiroDescargaRendicionLinea::LARGO_MINIMO, ' ', STR_PAD_RIGHT);

        $parsed = SiroDescargaRendicionLinea::parsear($linea);
        $this->assertNotNull($parsed);
        $this->assertSame('20260616', $parsed['fechaPago']);
        $this->assertSame('20260618', $parsed['fechaAcreditacion']);
        $this->assertSame('20260612', $parsed['fechVenc1']);
        $this->assertSame(5550000, $parsed['importePagadoCentavos']);
        $this->assertSame('PF', $parsed['canalAbrev']);
        $this->assertSame(55500.0, SiroDescargaRendicionLinea::importeDesdeCentavos(5550000));
    }

    public function test_rechaza_linea_corta(): void
    {
        $this->assertNull(SiroDescargaRendicionLinea::parsear('20260616'));
    }

    public function test_parsea_linea_integrado_corta_api_bpd(): void
    {
        $archivo = 'd:/_enviar/_06-Junio/sfq/CobranzasSiro_33609754309_20260629_12_57_19.txt';
        if (! is_readable($archivo)) {
            $this->markTestSkipped('Archivo de rendición SFQ de ejemplo no disponible.');
        }

        $raw = file_get_contents($archivo);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        $linea = rtrim($raw, "\r\n");

        $parsed = SiroDescargaRendicionLinea::parsear($linea);
        $this->assertNotNull($parsed);
        $this->assertSame('20260623', $parsed['fechaPago']);
        $this->assertSame('20260626', $parsed['fechaAcreditacion']);
        $this->assertSame('20260610', $parsed['fechVenc1']);
        $this->assertSame('BPD', $parsed['canalAbrev']);
        $this->assertSame('', $parsed['idPagoSiro']);
        $this->assertStringStartsWith('0449', $parsed['codigoBarras']);
    }

    public function test_extrae_barcode_0448_con_ceros_iniciales_de_mas(): void
    {
        $linea = rtrim(file('d:/_enviar/_06-Junio/sanfra/nuevo/CobranzasSiro_Cta. 1102_20260625txt.txt')[0], "\r\n");
        $parsed = SiroDescargaRendicionLinea::parsear($linea);
        if ($parsed === null) {
            $this->markTestSkipped('Archivo de rendición de ejemplo no disponible.');
        }

        $this->assertStringStartsWith('0448103284086000000', $parsed['codigoBarras']);
        $this->assertSame(59, strlen($parsed['codigoBarras']));
    }

    public function test_parsea_texto_tras_canal_en_rechazo_bpr(): void
    {
        $archivo = 'd:/_enviar/_08- Agosto/sfq/CobranzasSiro_33609754309_20260806_08_24_27.txt';
        if (! is_readable($archivo)) {
            $this->markTestSkipped('Archivo de rendición SFQ con BPR no disponible.');
        }

        $lineas = file($archivo, FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lineas);
        $lineaBpr = null;
        foreach ($lineas as $linea) {
            if (str_contains($linea, 'BPR')) {
                $lineaBpr = $linea;
                break;
            }
        }
        $this->assertNotNull($lineaBpr);

        $parsed = SiroDescargaRendicionLinea::parsear($lineaBpr);
        $this->assertNotNull($parsed);
        $this->assertSame('BPR', $parsed['canalAbrev']);
        $this->assertStringContainsString('402', $parsed['textoTrasCanal']);
        $this->assertStringContainsString('SERVICIO INVALIDO', $parsed['textoTrasCanal']);
    }
}
