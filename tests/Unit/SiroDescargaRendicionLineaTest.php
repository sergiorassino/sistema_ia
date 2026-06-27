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
}
