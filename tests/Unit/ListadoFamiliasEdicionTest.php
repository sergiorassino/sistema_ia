<?php

namespace Tests\Unit;

use App\Models\Familia;
use App\Support\Listados\ListadoFamiliasEdicion;
use PHPUnit\Framework\TestCase;

class ListadoFamiliasEdicionTest extends TestCase
{
    public function test_normalizar_recorta_textos_y_deja_solo_digitos_en_dni(): void
    {
        $fila = ListadoFamiliasEdicion::normalizar([
            'apellido' => '  Pérez  ',
            'responsable' => ' Ana ',
            'dniResp' => '12.345.678',
            'email' => '  ana@colegio.edu  ',
        ], true);

        $this->assertSame('Pérez', $fila['apellido']);
        $this->assertSame('Ana', $fila['responsable']);
        $this->assertSame('12345678', $fila['dniResp']);
        $this->assertSame('ana@colegio.edu', $fila['email']);
    }

    public function test_normalizar_omite_dni_si_la_columna_no_existe(): void
    {
        $fila = ListadoFamiliasEdicion::normalizar([
            'apellido' => 'García',
            'responsable' => 'Luis',
            'dniResp' => '12345678',
            'email' => '',
        ], false);

        $this->assertSame('', $fila['dniResp']);
    }

    public function test_payload_incluye_dni_solo_si_hay_columna(): void
    {
        $conDni = ListadoFamiliasEdicion::payload([
            'apellido' => 'García',
            'responsable' => 'Luis',
            'dniResp' => '12345678',
            'email' => 'a@b.com',
        ], true);

        $this->assertSame('12345678', $conDni['dniResp']);
        $this->assertArrayNotHasKey(
            'dniResp',
            ListadoFamiliasEdicion::payload([
                'apellido' => 'García',
                'responsable' => 'Luis',
                'dniResp' => '12345678',
                'email' => 'a@b.com',
            ], false),
        );
    }

    public function test_payload_dni_vacio_va_como_nulo(): void
    {
        $payload = ListadoFamiliasEdicion::payload([
            'apellido' => 'García',
            'responsable' => '',
            'dniResp' => '',
            'email' => '',
        ], true);

        $this->assertNull($payload['dniResp']);
        $this->assertSame('', $payload['responsable']);
        $this->assertSame('', $payload['email']);
    }

    public function test_reglas_exigen_apellido_y_aceptan_email_vacio(): void
    {
        $vacios = [
            'apellido' => '',
            'responsable' => '',
            'dniResp' => '',
            'email' => '',
        ];
        $reglas = ListadoFamiliasEdicion::reglas('12', $vacios, true);

        $this->assertContains('required', $reglas['filas.12.apellido']);
        $this->assertContains('nullable', $reglas['filas.12.email']);
        $this->assertContains('nullable', $reglas['filas.12.dniResp']);
    }

    public function test_reglas_validan_email_y_dni_cuando_hay_valor(): void
    {
        $conDatos = [
            'apellido' => 'García',
            'responsable' => 'Luis',
            'dniResp' => '12345678',
            'email' => 'a@b.com',
        ];
        $reglas = ListadoFamiliasEdicion::reglas('12', $conDatos, true);

        $this->assertContains('email', $reglas['filas.12.email']);
        $this->assertContains('digits_between:7,11', $reglas['filas.12.dniResp']);
    }

    public function test_mezclar_pisa_solo_los_campos_presentes_en_la_grilla(): void
    {
        $mezcla = ListadoFamiliasEdicion::mezclar(
            [
                'apellido' => 'García',
                'responsable' => 'Luis',
                'dniResp' => '12345678',
                'email' => 'a@b.com',
            ],
            [
                'email' => 'nuevo@colegio.edu',
            ],
        );

        $this->assertSame('García', $mezcla['apellido']);
        $this->assertSame('Luis', $mezcla['responsable']);
        $this->assertSame('12345678', $mezcla['dniResp']);
        $this->assertSame('nuevo@colegio.edu', $mezcla['email']);
    }

    public function test_fila_desde_modelo(): void
    {
        $familia = new Familia([
            'apellido' => 'López',
            'responsable' => 'Marta',
            'dniResp' => '30111222',
            'email' => 'marta@colegio.edu',
        ]);

        $fila = ListadoFamiliasEdicion::filaDesdeModelo($familia, true);

        $this->assertSame('López', $fila['apellido']);
        $this->assertSame('Marta', $fila['responsable']);
        $this->assertSame('30111222', $fila['dniResp']);
        $this->assertSame('marta@colegio.edu', $fila['email']);
    }

    public function test_dni_para_grilla_usa_separador_de_miles_y_normalizar_lo_quita(): void
    {
        $this->assertSame('30.111.222', ListadoFamiliasEdicion::dniParaGrilla('30111222'));
        $this->assertSame('30.111.222', ListadoFamiliasEdicion::dniParaGrilla('30.111.222'));
        $this->assertSame('', ListadoFamiliasEdicion::dniParaGrilla(''));

        $enGrilla = ListadoFamiliasEdicion::filaParaGrilla([
            'apellido' => 'López',
            'responsable' => 'Marta',
            'dniResp' => '30111222',
            'email' => 'marta@colegio.edu',
        ], true);

        $this->assertSame('30.111.222', $enGrilla['dniResp']);

        $guardado = ListadoFamiliasEdicion::normalizar($enGrilla, true);
        $this->assertSame('30111222', $guardado['dniResp']);
        $this->assertSame('30111222', ListadoFamiliasEdicion::payload($guardado, true)['dniResp']);
    }
}
