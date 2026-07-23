<?php

namespace Tests\Unit;

use App\Support\CertificacionServicios\AntiguedadServiciosCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AntiguedadServiciosCalculatorTest extends TestCase
{
    public function test_diff_ejemplos_capturas(): void
    {
        $this->assertSame(
            ['anios' => 0, 'meses' => 0, 'dias' => 20],
            AntiguedadServiciosCalculator::diffYmd('2005-08-10', '2005-08-29')
        );

        $this->assertSame(
            ['anios' => 4, 'meses' => 11, 'dias' => 12],
            AntiguedadServiciosCalculator::diffYmd('2007-03-15', '2012-02-26')
        );

        $this->assertSame(
            ['anios' => 0, 'meses' => 1, 'dias' => 16],
            AntiguedadServiciosCalculator::diffYmd('2011-10-31', '2011-12-16')
        );
    }

    public function test_union_elimina_solape_total_y_parcial(): void
    {
        $ref = Carbon::parse('2026-07-23');
        $union = AntiguedadServiciosCalculator::unirIntervalos([
            ['inicio' => '2007-03-15', 'fin' => '2012-02-26'],
            ['inicio' => '2008-01-01', 'fin' => '2010-06-01'], // contenido
            ['inicio' => '2012-02-01', 'fin' => '2012-06-30'], // solape parcial
        ], $ref);

        $this->assertCount(1, $union);
        $this->assertSame('2007-03-15', $union[0]['inicio']->toDateString());
        $this->assertSame('2012-06-30', $union[0]['fin']->toDateString());
    }

    public function test_licencia_parcial_no_descuenta(): void
    {
        $ref = '2026-07-23';
        $r = AntiguedadServiciosCalculator::calcular(
            [
                ['fechaAlta' => '2010-01-01', 'fechaBaja' => '2012-01-01'],
            ],
            [
                ['fechaInicio' => '2011-01-01', 'fechaFin' => '2011-06-30', 'parcial' => 1],
                ['fechaInicio' => '2011-10-31', 'fechaFin' => '2011-12-16', 'parcial' => 0],
            ],
            $ref
        );

        $soloLicNoParcial = AntiguedadServiciosCalculator::diffYmd('2011-10-31', '2011-12-16');
        $this->assertSame($soloLicNoParcial, $r['descuentoLicencias']);
        $this->assertTrue($r['antiguedad']['ok']);
    }

    public function test_licencias_solapadas_se_unen_al_descontar(): void
    {
        $ref = '2026-07-23';
        $r = AntiguedadServiciosCalculator::calcular(
            [
                ['fechaAlta' => '2010-01-01', 'fechaBaja' => '2015-01-01'],
            ],
            [
                ['fechaInicio' => '2011-01-01', 'fechaFin' => '2011-03-31', 'parcial' => 0],
                ['fechaInicio' => '2011-03-01', 'fechaFin' => '2011-04-30', 'parcial' => 0],
            ],
            $ref
        );

        $esperado = AntiguedadServiciosCalculator::normalizar(0, 3, 30);
        // Unión 01/01–30/04 = 0y 3m 30d → normaliza a 0y 4m 0d
        $this->assertSame($esperado, $r['descuentoLicencias']);
        $this->assertSame(['anios' => 0, 'meses' => 4, 'dias' => 0], $r['descuentoLicencias']);
    }
}
