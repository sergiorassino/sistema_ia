<?php

namespace Tests\Unit;

use App\Support\Certificados\CertificadoFinalizacionTextoEs;
use PHPUnit\Framework\TestCase;

class CertificadoFinalizacionTextoEsTest extends TestCase
{
    public function test_dni_ocho_digitos_con_puntos(): void
    {
        $this->assertSame('12.345.678', CertificadoFinalizacionTextoEs::dniConPuntos('12345678'));
    }

    public function test_dni_siete_digitos_con_puntos(): void
    {
        $this->assertSame('1.234.567', CertificadoFinalizacionTextoEs::dniConPuntos('1234567'));
    }

    public function test_mes_nombre(): void
    {
        $this->assertSame('diciembre', CertificadoFinalizacionTextoEs::mesNombre(12));
        $this->assertSame('', CertificadoFinalizacionTextoEs::mesNombre(0));
    }

    public function test_en_letras_desde_texto_numerico(): void
    {
        $this->assertSame('dos mil veinticinco', CertificadoFinalizacionTextoEs::enLetrasDesdeTexto('2025'));
        $this->assertSame('diecinueve', CertificadoFinalizacionTextoEs::enLetrasDesdeTexto('19'));
        $this->assertSame('diciembre', CertificadoFinalizacionTextoEs::enLetrasDesdeTexto('diciembre'));
    }
}
