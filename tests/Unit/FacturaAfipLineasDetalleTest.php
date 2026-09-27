<?php

namespace Tests\Unit;

use App\Support\Cuotas\FacturaAfipLineasDetalle;
use Tests\TestCase;

class FacturaAfipLineasDetalleTest extends TestCase
{
    public function test_sin_discriminacion_ni_intereses_queda_una_linea(): void
    {
        $lineas = FacturaAfipLineasDetalle::armarLineas('Septiembre 2026', [], 78000, 0);

        $this->assertCount(1, $lineas);
        $this->assertSame('SEPTIEMBRE 2026', $lineas[0]['concepto']);
        $this->assertSame(78000.0, $lineas[0]['importe']);
        $this->assertSame('78.000,00', $lineas[0]['importeFmt']);
        $this->assertFalse($lineas[0]['esTitulo']);
    }

    public function test_sin_discriminacion_separa_intereses(): void
    {
        $lineas = FacturaAfipLineasDetalle::armarLineas('Septiembre 2026', [], 78000, 8000);

        $this->assertCount(2, $lineas);
        $this->assertSame('SEPTIEMBRE 2026', $lineas[0]['concepto']);
        $this->assertSame(70000.0, $lineas[0]['importe']);
        $this->assertSame(FacturaAfipLineasDetalle::ETIQUETA_INTERESES, $lineas[1]['concepto']);
        $this->assertSame(8000.0, $lineas[1]['importe']);
        $this->assertTrue($lineas[1]['esDetalle']);
    }

    public function test_nombre_de_la_cuota_encima_de_la_discriminacion_e_intereses(): void
    {
        $lineas = FacturaAfipLineasDetalle::armarLineas('Septiembre 2026', [
            ['nombre' => 'Enseñanza Programática Prim', 'importe' => 50000],
            ['nombre' => 'Enseñanza Extraprogramática Prim', 'importe' => 15000],
            ['nombre' => 'Otros Conceptos Prim', 'importe' => 5000],
        ], 78000, 8000);

        $this->assertSame([
            'SEPTIEMBRE 2026',
            'Enseñanza Programática Prim',
            'Enseñanza Extraprogramática Prim',
            'Otros Conceptos Prim',
            'Intereses',
        ], array_column($lineas, 'concepto'));

        $this->assertTrue($lineas[0]['esTitulo']);
        $this->assertSame('', $lineas[0]['importeFmt']);
        $this->assertSame(50000.0, $lineas[1]['importe']);
        $this->assertSame(15000.0, $lineas[2]['importe']);
        $this->assertSame(5000.0, $lineas[3]['importe']);
        $this->assertSame(8000.0, $lineas[4]['importe']);
        $this->assertSame(78000.0, round(array_sum(array_column(array_slice($lineas, 1), 'importe')), 2));
    }

    public function test_reparte_los_items_cuando_no_suman_el_neto_facturado(): void
    {
        $lineas = FacturaAfipLineasDetalle::armarLineas('Septiembre 2026', [
            ['nombre' => 'Enseñanza', 'importe' => 60],
            ['nombre' => 'Materiales', 'importe' => 40],
        ], 50, 0);

        $this->assertTrue($lineas[0]['esTitulo']);
        $this->assertSame(30.0, $lineas[1]['importe']);
        $this->assertSame(20.0, $lineas[2]['importe']);
    }

    public function test_omite_items_en_cero(): void
    {
        $lineas = FacturaAfipLineasDetalle::armarLineas('Septiembre 2026', [
            ['nombre' => 'Vacío', 'importe' => 0],
        ], 78000, 0);

        $this->assertCount(1, $lineas);
        $this->assertSame('SEPTIEMBRE 2026', $lineas[0]['concepto']);
        $this->assertSame(78000.0, $lineas[0]['importe']);
    }
}
