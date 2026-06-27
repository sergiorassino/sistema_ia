<?php

namespace Tests\Unit;

use App\Support\Cuotas\CuponAPagarSnapshot;
use App\Support\Cuotas\Siro\SiroSubidaGrillaColumnas;
use Carbon\Carbon;
use Tests\TestCase;

class SiroSubidaGrillaColumnasTest extends TestCase
{
    public function test_desde_detalle_refleja_fechas_e_importes_del_archivo_siro(): void
    {
        $registro = new \App\Models\CuotaGenerada([
            'id' => 1,
            'idLegajos' => 2530,
            'idCuotas' => 86,
            'idCursos' => 1,
            'faltapa' => 4995,
            'venc1' => '2026-06-12',
            'venc2' => '2026-06-26',
            'venc3' => '2026-06-26',
            'ultUpload' => 5,
        ]);

        $cupon = [
            'entoNivel' => ['siroMje' => 'COLEGIO', 'insti' => 'COLEGIO'],
            'entoAdmin' => ['insti' => 'COLEGIO'],
            'cuotaNombre' => 'JUNIO 2026',
            'importeVenc1' => 4995.0,
            'importeVenc2' => 5550.0,
            'importeVenc3' => 5705.4,
        ];

        $detalle = CuponAPagarSnapshot::armar($registro, $cupon, '0000025305150011052', 3);
        $columnas = SiroSubidaGrillaColumnas::desdeDetalle($detalle);

        $this->assertSame('12/06/2026', $columnas['siroVenc1']);
        $this->assertSame('4.995,00', $columnas['siroImporte1']);
        $this->assertSame('26/06/2026', $columnas['siroVenc2']);
        $this->assertSame('5.550,00', $columnas['siroImporte2']);
        $this->assertSame('26/06/2026', $columnas['siroVenc3']);
        $this->assertSame('5.705,40', $columnas['siroImporte3']);
    }

    public function test_cupones_vencidos_muestra_tres_tramos_iguales_en_grilla(): void
    {
        $registro = new \App\Models\CuotaGenerada([
            'id' => 1,
            'idLegajos' => 2530,
            'idCuotas' => 86,
            'idCursos' => 1,
            'faltapa' => 4995,
            'venc1' => '2026-01-12',
            'venc2' => '2026-02-12',
            'venc3' => '2026-03-12',
            'nueVenc' => '2026-07-15',
            'ultUpload' => 5,
        ]);

        $importeEsperado = CuponAPagarSnapshot::importeConInteresesEnFecha(
            $registro,
            Carbon::parse('2026-07-15'),
        );

        $cupon = [
            'entoNivel' => ['siroMje' => 'COLEGIO', 'insti' => 'COLEGIO'],
            'cuotaNombre' => 'JUNIO 2026',
        ];

        $detalle = CuponAPagarSnapshot::armarParaCuponesVencidosSiro($registro, $cupon, '0000025305150011052', 3);
        $columnas = SiroSubidaGrillaColumnas::desdeDetalle($detalle);

        $importeFmt = \App\Support\Alumnos\ArancelesEscolares::formatearImporte($importeEsperado);

        $this->assertSame('15/07/2026', $columnas['siroVenc1']);
        $this->assertSame('15/07/2026', $columnas['siroVenc2']);
        $this->assertSame('15/07/2026', $columnas['siroVenc3']);
        $this->assertSame($importeFmt, $columnas['siroImporte1']);
        $this->assertSame($importeFmt, $columnas['siroImporte2']);
        $this->assertSame($importeFmt, $columnas['siroImporte3']);
    }

    public function test_sin_detalle_devuelve_guiones(): void
    {
        $columnas = SiroSubidaGrillaColumnas::desdeDetalle(null);

        $this->assertSame('—', $columnas['siroVenc1']);
        $this->assertSame('—', $columnas['siroImporte3']);
    }
}
