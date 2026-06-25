<?php

namespace Tests\Unit;

use App\Support\Cuotas\CuponAPagarSnapshot;
use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaArchivo;
use Carbon\Carbon;
use Tests\TestCase;

class CuponAPagarSnapshotTest extends TestCase
{
    public function test_mensaje_ticket_usa_siro_mje_si_esta_configurado(): void
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
            'entoNivel' => [
                'siroMje' => 'COLEGIO PARROQU',
                'insti' => 'COLEGIO PARROQUIAL LARGO',
            ],
            'entoAdmin' => [
                'insti' => 'COLEGIO PARROQUIAL LARGO',
            ],
            'cuotaNombre' => 'JUNIO 2026',
            'importeVenc1' => 4995.0,
            'importeVenc2' => 5550.0,
            'importeVenc3' => 5705.4,
        ];

        $detalle = CuponAPagarSnapshot::armar($registro, $cupon, '0000025305150011052', 3);

        $this->assertSame('COLEGIO PARROQU', $detalle['mensajeTicket1']);
        $this->assertSame('COLEGIO PARROQU', $detalle['mensajePantalla']);
        $this->assertSame(
            SiroSubidaBaseDeudaArchivo::recortarAlfanumerico('JUNIO 2026', 25),
            $detalle['mensajeTicket2'],
        );
    }
}
