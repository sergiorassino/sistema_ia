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

    public function test_partes_nombre_institucion_monte_cristo(): void
    {
        $p = CertificadoFinalizacionTextoEs::partesNombreInstitucion('INSTITUTO PARROQUIAL MONTE CRISTO');
        $this->assertSame('Instituto', $p['tipo']);
        $this->assertSame('Parroquial Monte Cristo', $p['nombre']);
    }

    public function test_partes_nombre_colegio_con_particulas(): void
    {
        $p = CertificadoFinalizacionTextoEs::partesNombreInstitucion('COLEGIO SAN JOSÉ DE LA INMACULADA');
        $this->assertSame('Colegio', $p['tipo']);
        $this->assertSame('San José de la Inmaculada', $p['nombre']);
    }

    public function test_partes_nombre_una_palabra(): void
    {
        $p = CertificadoFinalizacionTextoEs::partesNombreInstitucion('ESCUELA');
        $this->assertSame('Escuela', $p['tipo']);
        $this->assertSame('', $p['nombre']);
    }

    public function test_nacido_y_acreditado_segun_sexo(): void
    {
        $this->assertSame('nacida', CertificadoFinalizacionTextoEs::nacidoSegunSexo(1, 'Femenino'));
        $this->assertSame('nacido', CertificadoFinalizacionTextoEs::nacidoSegunSexo(2, 'Masculino'));
        $this->assertSame('nacido', CertificadoFinalizacionTextoEs::nacidoSegunSexo(0, ''));
        $this->assertSame('acreditada', CertificadoFinalizacionTextoEs::acreditadoSegunSexo(1, 'Femenino'));
        $this->assertSame('acreditado', CertificadoFinalizacionTextoEs::acreditadoSegunSexo(2, 'Masculino'));
    }
}
