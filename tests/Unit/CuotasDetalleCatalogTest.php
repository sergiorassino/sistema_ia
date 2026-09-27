<?php

namespace Tests\Unit;

use App\Support\Cuotas\CuotasDetalleCatalog;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CuotasDetalleCatalogTest extends TestCase
{
    public function test_importe_vacio_es_cero(): void
    {
        $this->assertSame(0.0, CuotasDetalleCatalog::importeDesdeTexto(''));
        $this->assertSame(0.0, CuotasDetalleCatalog::importeDesdeTexto('   '));
    }

    public function test_importe_formato_argentino(): void
    {
        $this->assertSame(105586.0, CuotasDetalleCatalog::importeDesdeTexto('105.586,00'));
        $this->assertSame(1234.56, CuotasDetalleCatalog::importeDesdeTexto('1.234,56'));
        $this->assertSame(811.41, CuotasDetalleCatalog::importeDesdeTexto('811,41'));
        $this->assertSame(10.5, CuotasDetalleCatalog::importeDesdeTexto('10.50'));
        $this->assertSame(1234.0, CuotasDetalleCatalog::importeDesdeTexto('1.234'));
    }

    public function test_rechaza_importe_invalido_o_negativo(): void
    {
        $this->expectException(ValidationException::class);
        CuotasDetalleCatalog::importeDesdeTexto('-1');
    }

    public function test_rechaza_texto_que_no_es_importe(): void
    {
        $this->expectException(ValidationException::class);
        CuotasDetalleCatalog::importeDesdeTexto('abc');
    }

    public function test_subtotal_suma_los_textos(): void
    {
        $this->assertSame(300.5, CuotasDetalleCatalog::subtotalTextos([
            '100,00',
            '200,50',
        ]));
    }

    public function test_subtotal_null_si_hay_un_importe_invalido(): void
    {
        $this->assertNull(CuotasDetalleCatalog::subtotalTextos(['10,00', 'no']));
    }

    public function test_nombre_recorta_espacios(): void
    {
        $this->assertSame(
            'Cuota de materiales',
            CuotasDetalleCatalog::normalizarNombre("  Cuota   de   materiales  "),
        );
    }

    public function test_nombre_vacio_falla(): void
    {
        $this->expectException(ValidationException::class);
        CuotasDetalleCatalog::normalizarNombre('   ');
    }
}
