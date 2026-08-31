<?php

namespace Tests\Unit;

use App\Support\CalificacionesSecundario\CierreAnualJournal;
use PHPUnit\Framework\TestCase;

class CierreAnualJournalSnapshotTest extends TestCase
{
    public function test_snapshot_normaliza_textos_y_enteros(): void
    {
        $snap = CierreAnualJournal::snapshotDesdeFila((object) [
            'apro' => '2',
            'calif' => ' 7 ',
            'mes' => '12',
            'ano' => '',
            'cond' => ' Regular ',
            'escuapro' => 'Colegio',
            'condAdeuda' => null,
            'inscri' => null,
        ]);

        $this->assertSame(2, $snap['apro']);
        $this->assertSame('7', $snap['calif']);
        $this->assertSame(12, $snap['mes']);
        $this->assertNull($snap['ano']);
        $this->assertSame('Regular', $snap['cond']);
        $this->assertSame('', $snap['condAdeuda']);
        $this->assertSame(0, $snap['inscri']);
    }

    public function test_snapshot_tras_update_solo_pisa_claves_enviadas(): void
    {
        $antes = CierreAnualJournal::snapshotDesdeFila((object) [
            'apro' => 0,
            'calif' => '6',
            'mes' => null,
            'ano' => null,
            'cond' => '',
            'escuapro' => '',
            'condAdeuda' => '',
            'inscri' => 1,
        ]);
        $despues = CierreAnualJournal::snapshotTrasUpdate($antes, [
            'apro' => 1,
            'condAdeuda' => 'PR',
            'inscri' => 0,
        ]);

        $this->assertSame(1, $despues['apro']);
        $this->assertSame('6', $despues['calif']);
        $this->assertSame('PR', $despues['condAdeuda']);
        $this->assertSame(0, $despues['inscri']);
    }

    public function test_igual_snapshot_trata_mes_nulo_como_cero(): void
    {
        $a = CierreAnualJournal::snapshotDesdeFila((object) [
            'apro' => 2,
            'calif' => '7',
            'mes' => null,
            'ano' => 2026,
            'cond' => 'Regular',
            'escuapro' => 'X',
            'condAdeuda' => '',
            'inscri' => 0,
        ]);
        $b = $a;
        $b['mes'] = 0;

        $this->assertTrue(CierreAnualJournal::igualSnapshot($a, $b));
    }

    public function test_no_igual_si_calif_cambio(): void
    {
        $a = CierreAnualJournal::snapshotDesdeFila((object) [
            'apro' => 2,
            'calif' => '7',
            'mes' => 12,
            'ano' => 2026,
            'cond' => 'Regular',
            'escuapro' => 'X',
            'condAdeuda' => '',
            'inscri' => 0,
        ]);
        $b = $a;
        $b['calif'] = '8';

        $this->assertFalse(CierreAnualJournal::igualSnapshot($a, $b));
    }
}
