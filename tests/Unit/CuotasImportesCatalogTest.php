<?php

namespace Tests\Unit;

use App\Support\Cuotas\CuotasImportesCatalog;
use Tests\TestCase;

class CuotasImportesCatalogTest extends TestCase
{
    public function test_formatea_valores_con_coma_decimal(): void
    {
        $this->assertSame('10,50', CuotasImportesCatalog::formatearCampoParaInput('valor1v', 10.5));
        $this->assertSame('-', CuotasImportesCatalog::formatearCampoParaInput('signo1v', '-'));
        $this->assertSame('%', CuotasImportesCatalog::formatearCampoParaInput('porcan2v', '%'));
    }

    public function test_persiste_importe_formato_argentino(): void
    {
        $this->assertSame(15000.5, CuotasImportesCatalog::valorPersistidoParaCampo('importe', '15.000,50'));
        $this->assertSame(10.0, CuotasImportesCatalog::valorPersistidoParaCampo('valor2v', '10'));
        $this->assertSame('+', CuotasImportesCatalog::valorPersistidoParaCampo('signo3v', '+'));
        $this->assertSame('p', CuotasImportesCatalog::valorPersistidoParaCampo('porcan4v', 'p'));
    }

    public function test_campos_editables_cubre_importe_y_cuatro_tramos(): void
    {
        $campos = CuotasImportesCatalog::camposEditables();

        $this->assertContains('importe', $campos);
        foreach ([1, 2, 3, 4] as $n) {
            $this->assertContains("signo{$n}v", $campos);
            $this->assertContains("valor{$n}v", $campos);
            $this->assertContains("porcan{$n}v", $campos);
        }
        $this->assertCount(13, $campos);
    }
}
