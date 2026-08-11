<?php

namespace Tests\Unit;

use App\Support\MatrizAnaliticos\CalificacionEnLetras;
use PHPUnit\Framework\TestCase;

class CalificacionEnLetrasTest extends TestCase
{
    protected function tearDown(): void
    {
        CalificacionEnLetras::olvidarCache();
        parent::tearDown();
    }

    public function test_codigos_especiales(): void
    {
        $this->assertSame('ADEUDA', CalificacionEnLetras::resolver('Adeud'));
        $this->assertSame('APROBADO', CalificacionEnLetras::resolver('aprob'));
    }

    public function test_notas_enteras(): void
    {
        $this->assertSame('seis c/00', CalificacionEnLetras::numericaALetras('6'));
        $this->assertSame('seis c/00', CalificacionEnLetras::numericaALetras('6.00'));
        $this->assertSame('siete c/00', CalificacionEnLetras::numericaALetras('7.00'));
        $this->assertSame('diez', CalificacionEnLetras::numericaALetras('10'));
        $this->assertSame('diez', CalificacionEnLetras::numericaALetras('10.00'));
    }

    public function test_notas_con_decimales(): void
    {
        $this->assertSame('seis c/50', CalificacionEnLetras::numericaALetras('6.50'));
        $this->assertSame('siete c/50', CalificacionEnLetras::numericaALetras('7.50'));
        $this->assertSame('nueve c/50', CalificacionEnLetras::numericaALetras('9.50'));
        $this->assertSame('nueve c/75', CalificacionEnLetras::numericaALetras('9.75'));
        $this->assertSame('ocho c/25', CalificacionEnLetras::numericaALetras('8,25'));
    }

    public function test_resolver_usa_fallback_numerico(): void
    {
        $this->assertSame('seis c/00', CalificacionEnLetras::resolver('6.00'));
        $this->assertSame('ocho c/00', CalificacionEnLetras::resolver('8'));
        $this->assertSame('siete c/50', CalificacionEnLetras::resolver('7.50'));
        $this->assertSame('diez', CalificacionEnLetras::resolver('10'));
    }

    public function test_fuera_de_rango_o_no_numerica(): void
    {
        $this->assertNull(CalificacionEnLetras::numericaALetras('11'));
        $this->assertNull(CalificacionEnLetras::numericaALetras('abc'));
        $this->assertSame('ABC', CalificacionEnLetras::resolver('abc'));
    }
}
