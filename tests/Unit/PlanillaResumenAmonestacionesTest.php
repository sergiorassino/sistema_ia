<?php

namespace Tests\Unit;

use App\Support\PlanillaResumenCalificacionesSecundario;
use PHPUnit\Framework\TestCase;

class PlanillaResumenAmonestacionesTest extends TestCase
{
    public function test_toma_el_item_con_etiqueta_amonestaciones_y_no_el_primero_de_sanciones(): void
    {
        $items = [
            (object) ['etiqueta' => 'Apercibimientos Orales', 'fuente' => 'sanciones', 'total' => 0],
            (object) ['etiqueta' => 'Apercibimientos Escritos', 'fuente' => 'sanciones', 'total' => 11],
            (object) ['etiqueta' => 'Amonestaciones', 'fuente' => 'sanciones', 'total' => 11],
        ];

        $this->assertSame('11', PlanillaResumenCalificacionesSecundario::textoAmonestaciones($items));
    }

    public function test_respeta_un_total_cero_del_item_amonestaciones(): void
    {
        $items = [
            (object) ['etiqueta' => 'Amonestaciones', 'fuente' => 'sanciones', 'total' => 0],
        ];

        $this->assertSame('0', PlanillaResumenCalificacionesSecundario::textoAmonestaciones($items));
    }

    public function test_sin_etiqueta_amonestaciones_no_imprime_valor(): void
    {
        $items = [
            (object) ['etiqueta' => 'Apercibimientos Orales', 'fuente' => 'sanciones', 'total' => 4],
        ];

        $this->assertSame('', PlanillaResumenCalificacionesSecundario::textoAmonestaciones($items));
    }
}
