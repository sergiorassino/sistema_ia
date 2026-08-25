<?php

namespace Tests\Unit;

use App\Support\Alumnos\ActualizacionDatosPersonalesComun;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

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

    public function test_destinatario_facturacion_afip_nombre_obligatorio_sin_guion(): void
    {
        $rules = ['respAdmiNom' => ActualizacionDatosPersonalesComun::reglaNombreDestinatarioFacturacionAfip()];

        $this->assertTrue(Validator::make(['respAdmiNom' => 'García, Juan'], $rules)->passes());
        $this->assertTrue(Validator::make(['respAdmiNom' => ''], $rules)->fails());
        $this->assertTrue(Validator::make(['respAdmiNom' => '-'], $rules)->fails());
        $this->assertTrue(Validator::make(['respAdmiNom' => '---'], $rules)->fails());
    }

    public function test_destinatario_facturacion_afip_dni_obligatorio_7_a_11_digitos(): void
    {
        $rules = ['respAdmiDni' => ActualizacionDatosPersonalesComun::reglaDniDestinatarioFacturacionAfip()];

        $this->assertTrue(Validator::make(['respAdmiDni' => '30111222'], $rules)->passes());
        $this->assertTrue(Validator::make(['respAdmiDni' => '30.111.222'], $rules)->passes());
        $this->assertTrue(Validator::make(['respAdmiDni' => ''], $rules)->fails());
        $this->assertTrue(Validator::make(['respAdmiDni' => '-'], $rules)->fails());
        $this->assertTrue(Validator::make(['respAdmiDni' => '123'], $rules)->fails());
    }

    public function test_datos_destinatario_facturacion_afip_para_guardar_normaliza_dni(): void
    {
        $datos = ActualizacionDatosPersonalesComun::datosDestinatarioFacturacionAfipParaGuardar([
            'respAdmiNom' => '  García Juan  ',
            'respAdmiDni' => '30.111.222',
        ]);

        $this->assertSame('García Juan', $datos['respAdmiNom']);
        $this->assertSame('30111222', $datos['respAdmiDni']);
    }
}
