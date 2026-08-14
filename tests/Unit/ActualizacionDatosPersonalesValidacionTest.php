<?php

namespace Tests\Unit;

use App\Support\Alumnos\ActualizacionDatosPersonalesComun;
use PHPUnit\Framework\TestCase;

class ActualizacionDatosPersonalesValidacionTest extends TestCase
{
    public function test_el_guion_cuenta_como_dato_obligatorio(): void
    {
        $this->assertTrue(ActualizacionDatosPersonalesComun::textoObligatorioAceptado('-'));
        $this->assertTrue(ActualizacionDatosPersonalesComun::textoObligatorioAceptado(' - '));
        $this->assertTrue(ActualizacionDatosPersonalesComun::textoObligatorioAceptado('--'));
        $this->assertTrue(ActualizacionDatosPersonalesComun::textoObligatorioAceptado('---'));
        $this->assertTrue(ActualizacionDatosPersonalesComun::textoObligatorioAceptado('Juan Perez'));
        $this->assertFalse(ActualizacionDatosPersonalesComun::textoObligatorioAceptado(''));
        $this->assertFalse(ActualizacionDatosPersonalesComun::textoObligatorioAceptado('   '));
        $this->assertFalse(ActualizacionDatosPersonalesComun::textoObligatorioAceptado(null));
    }

    public function test_normaliza_rayas_tipograficas_a_guion(): void
    {
        $this->assertSame('-', ActualizacionDatosPersonalesComun::normalizarTextoInput('–'));
        $this->assertSame('-', ActualizacionDatosPersonalesComun::normalizarTextoInput('—'));
        $this->assertSame('-', ActualizacionDatosPersonalesComun::normalizarTextoInput('−'));
        $this->assertSame('-', ActualizacionDatosPersonalesComun::normalizarTextoInput('--'));
        $this->assertSame('-', ActualizacionDatosPersonalesComun::normalizarTextoInput('---'));
        $this->assertSame('-', ActualizacionDatosPersonalesComun::normalizarTextoInput(' - - '));
        $this->assertTrue(ActualizacionDatosPersonalesComun::textoObligatorioAceptado('–'));
        $this->assertTrue(ActualizacionDatosPersonalesComun::emailInputAceptado('–', false));
        $this->assertTrue(ActualizacionDatosPersonalesComun::emailInputAceptado('--', false));
        $this->assertTrue(ActualizacionDatosPersonalesComun::emailInputAceptado('---', false));
    }

    public function test_dni_cero_se_muestra_como_guion(): void
    {
        $this->assertSame('-', ActualizacionDatosPersonalesComun::textoDniDesdeLegajo(0));
        $this->assertSame('-', ActualizacionDatosPersonalesComun::textoDniDesdeLegajo('0'));
        $this->assertSame('', ActualizacionDatosPersonalesComun::textoDniDesdeLegajo(''));
        $this->assertSame('', ActualizacionDatosPersonalesComun::textoDniDesdeLegajo(null));
        $this->assertSame('-', ActualizacionDatosPersonalesComun::textoDniDesdeLegajo('-'));
        $this->assertSame('30111222', ActualizacionDatosPersonalesComun::textoDniDesdeLegajo(30111222));
    }

    public function test_no_convierte_fechas_ni_textos_con_guiones(): void
    {
        $this->assertSame('2024-03-15', ActualizacionDatosPersonalesComun::normalizarTextoInput('2024-03-15'));
        $this->assertSame('Ana-Maria', ActualizacionDatosPersonalesComun::normalizarTextoInput('Ana-Maria'));
    }
}
