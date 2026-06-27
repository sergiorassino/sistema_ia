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

    public function test_armar_para_cupones_vencidos_siro_tres_tramos_con_importe_con_intereses_y_fecha_nue_venc(): void
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

        $nueVenc = Carbon::parse('2026-07-15');
        $importeEsperado = CuponAPagarSnapshot::importeConInteresesEnFecha($registro, $nueVenc);

        $cupon = [
            'entoNivel' => ['siroMje' => 'COLEGIO PARROQU', 'insti' => 'COLEGIO'],
            'entoAdmin' => ['insti' => 'COLEGIO'],
            'cuotaNombre' => 'JUNIO 2026',
            'importeVenc1' => 4995.0,
            'importeVenc2' => 5550.0,
            'importeVenc3' => 6200.5,
            'cuponVencido' => true,
        ];

        $detalle = CuponAPagarSnapshot::armarParaCuponesVencidosSiro($registro, $cupon, '0000025305150011052', 3);

        $this->assertSame('2026-07-15', $detalle['venc1']->toDateString());
        $this->assertSame('2026-07-15', $detalle['venc2']->toDateString());
        $this->assertSame('2026-07-15', $detalle['venc3']->toDateString());
        $this->assertSame($importeEsperado, $detalle['importe1']);
        $this->assertSame($importeEsperado, $detalle['importe2']);
        $this->assertSame($importeEsperado, $detalle['importe3']);

        $archivo = SiroSubidaBaseDeudaArchivo::generar([$detalle], Carbon::parse('2026-07-15'));
        $this->assertSame($importeEsperado, $archivo['totalImporte1']);

        $importeArchivo = str_pad((string) (int) round($importeEsperado * 100), 11, '0', STR_PAD_LEFT);
        $lineaDetalle = explode("\r\n", rtrim($archivo['contenido'], "\r\n"))[1];
        $this->assertSame('20260715', substr($lineaDetalle, 41, 8));
        $this->assertSame('20260715', substr($lineaDetalle, 60, 8));
        $this->assertSame('20260715', substr($lineaDetalle, 79, 8));
        $this->assertSame($importeArchivo, substr($lineaDetalle, 49, 11));
        $this->assertSame($importeArchivo, substr($lineaDetalle, 68, 11));
        $this->assertSame($importeArchivo, substr($lineaDetalle, 87, 11));
    }
}
