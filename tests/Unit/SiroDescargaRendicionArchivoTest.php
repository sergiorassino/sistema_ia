<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionArchivo;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionArchivoTest extends TestCase
{
    public function test_normalizar_nombre_archivo_recorta_y_quita_espacios(): void
    {
        $nombre = SiroDescargaRendicionArchivo::normalizarNombreArchivo('  CobranzasSiro_Cta. 1105_20260624txt.txt  ');

        $this->assertSame('CobranzasSiro_Cta. 1105_20260624txt.txt', $nombre);
    }

    public function test_normalizar_nombre_archivo_respeta_maximo_cincuenta(): void
    {
        $nombre = SiroDescargaRendicionArchivo::normalizarNombreArchivo(str_repeat('A', 60));

        $this->assertSame(50, mb_strlen($nombre));
    }
}
