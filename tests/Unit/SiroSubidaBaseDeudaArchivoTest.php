<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaArchivo;
use PHPUnit\Framework\TestCase;

class SiroSubidaBaseDeudaArchivoTest extends TestCase
{
    public function test_contenido_listo_para_siro_sin_bom_crlf_y_280_bytes_por_linea(): void
    {
        $cabecera = str_repeat('0', SiroSubidaBaseDeudaArchivo::LARGO_LINEA);
        $pie = str_repeat('9', SiroSubidaBaseDeudaArchivo::LARGO_LINEA);

        $contenido = SiroSubidaBaseDeudaArchivo::contenidoListoParaSiro([$cabecera, $pie]);

        $this->assertFalse(str_starts_with($contenido, "\xEF\xBB\xBF"));
        $this->assertSame($cabecera."\r\n".$pie, $contenido);

        foreach (explode("\r\n", $contenido) as $linea) {
            $this->assertSame(SiroSubidaBaseDeudaArchivo::LARGO_LINEA, strlen($linea));
            $this->assertSame(1, preg_match('/^[\x20-\x7E]+$/', $linea));
        }
    }

    public function test_rechaza_linea_con_longitud_incorrecta(): void
    {
        $this->expectException(\RuntimeException::class);
        SiroSubidaBaseDeudaArchivo::contenidoListoParaSiro([str_repeat('0', 279)]);
    }
}
