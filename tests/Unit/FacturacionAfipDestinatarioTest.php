<?php

namespace Tests\Unit;

use App\Support\Cuotas\FacturacionAfipComun;
use Tests\TestCase;

class FacturacionAfipDestinatarioTest extends TestCase
{
    public function test_motivo_destinatario_invalido_si_falta_nombre(): void
    {
        $this->assertSame(
            'Falta el destinatario de facturación AFIP en el legajo (nombre).',
            FacturacionAfipComun::motivoDestinatarioInvalido(0, '', '12345678'),
        );
        $this->assertSame(
            'Falta el destinatario de facturación AFIP en el legajo (nombre).',
            FacturacionAfipComun::motivoDestinatarioInvalido(1, '   ', '12345678'),
        );
    }

    public function test_motivo_destinatario_invalido_si_falta_dni(): void
    {
        $this->assertSame(
            'Falta o es inválido el DNI del destinatario de facturación AFIP en el legajo.',
            FacturacionAfipComun::motivoDestinatarioInvalido(0, 'García Juan', ''),
        );
        $this->assertSame(
            'Falta o es inválido el DNI del destinatario de facturación AFIP en el legajo.',
            FacturacionAfipComun::motivoDestinatarioInvalido(0, 'García Juan', '123'),
        );
    }

    public function test_motivo_destinatario_nulo_si_nombre_y_dni_validos(): void
    {
        $this->assertNull(FacturacionAfipComun::motivoDestinatarioInvalido(0, 'García Juan', '12345678'));
    }

    public function test_persistir_destinatario_rechaza_nombre_vacio_sin_tocar_bd(): void
    {
        $vacio = FacturacionAfipComun::persistirDestinatarioEnLegajo(1, '', '12345678');
        $this->assertFalse($vacio['ok']);
        $this->assertStringContainsString('nombre', $vacio['mensaje']);

        $guion = FacturacionAfipComun::persistirDestinatarioEnLegajo(1, '-', '12345678');
        $this->assertFalse($guion['ok']);
        $this->assertStringContainsString('nombre', $guion['mensaje']);
    }

    public function test_destinatario_desde_nombre_y_dni(): void
    {
        $ok = FacturacionAfipComun::destinatarioDesdeNombreYDni('García Juan', '12.345.678');
        $this->assertTrue($ok['valido']);
        $this->assertSame('García Juan', $ok['responsable']);
        $this->assertSame('12345678', $ok['dniResp']);

        $vacio = FacturacionAfipComun::destinatarioDesdeNombreYDni('-', '12345678');
        $this->assertFalse($vacio['valido']);
    }

    public function test_clave_destinatario_por_defecto_sigue_orden_legacy(): void
    {
        $this->assertSame('madre', FacturacionAfipComun::claveDestinatarioPorDefecto([
            'madre' => ['nombre' => 'Ana', 'dni' => '20111222'],
            'padre' => ['nombre' => 'Luis', 'dni' => '30111222'],
            'resp_admin' => ['nombre' => 'Admin', 'dni' => '40111222'],
            'estudiante' => ['nombre' => 'Alumno', 'dni' => '50111222'],
        ]));
        $this->assertSame('padre', FacturacionAfipComun::claveDestinatarioPorDefecto([
            'madre' => ['nombre' => '', 'dni' => ''],
            'padre' => ['nombre' => 'Luis', 'dni' => '30111222'],
            'resp_admin' => ['nombre' => 'Admin', 'dni' => '40111222'],
            'estudiante' => ['nombre' => 'Alumno', 'dni' => '50111222'],
        ]));
        $this->assertSame('', FacturacionAfipComun::claveDestinatarioPorDefecto([
            'madre' => ['nombre' => 'aaa', 'dni' => '111'],
            'padre' => ['nombre' => '', 'dni' => ''],
            'resp_admin' => ['nombre' => '', 'dni' => ''],
            'estudiante' => ['nombre' => 'Alumno', 'dni' => '12'],
        ]));
    }

    public function test_persistir_responsables_imputacion_rechaza_legajo_invalido(): void
    {
        $resultado = FacturacionAfipComun::persistirResponsablesImputacion(0, [
            'nombremad' => 'Ana',
            'dnimad' => '20111222',
        ]);
        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('estudiante', $resultado['mensaje']);
    }
}
