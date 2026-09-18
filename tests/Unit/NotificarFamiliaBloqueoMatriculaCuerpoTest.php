<?php

namespace Tests\Unit;

use App\Support\MatriculaWeb\NotificarFamiliaBloqueoMatricula;
use Tests\TestCase;

class NotificarFamiliaBloqueoMatriculaCuerpoTest extends TestCase
{
    public function test_cuerpo_bloqueo_default_incluye_motivos_y_contacto(): void
    {
        $cuerpo = NotificarFamiliaBloqueoMatricula::armarCuerpo(true, true, 'Secundario');

        $this->assertStringContainsString('PEDAGÓGICOS y/o ADMINISTRATIVOS', $cuerpo);
        $this->assertStringContainsString('Secretaría de Nivel Secundario y Administración', $cuerpo);
        $this->assertStringContainsString('Estimada Familia:', $cuerpo);
        $this->assertStringNotContainsString('{motivos}', $cuerpo);
        $this->assertStringNotContainsString('{contacto}', $cuerpo);
    }

    public function test_plantilla_parametrizada_reemplaza_marcadores(): void
    {
        $cuerpo = NotificarFamiliaBloqueoMatricula::armarCuerpo(
            false,
            true,
            'Primario',
            'Aviso: bloqueo por {motivos}. Contacto: {contacto}.',
        );

        $this->assertSame(
            'Aviso: bloqueo por ADMINISTRATIVOS. Contacto: Administración.',
            $cuerpo,
        );
    }

    public function test_cuerpo_desbloqueo_usa_plantilla_y_requisitos(): void
    {
        $cuerpo = NotificarFamiliaBloqueoMatricula::armarCuerpoDesbloqueo(
            false,
            false,
            'Ya cumplieron {requisitos}.',
        );

        $this->assertSame('Ya cumplieron administrativos y/o pedagógicos.', $cuerpo);
    }

    public function test_convierte_br_de_la_plantilla_en_saltos_de_linea(): void
    {
        $cuerpo = NotificarFamiliaBloqueoMatricula::armarCuerpo(
            true,
            false,
            'Inicial',
            'Línea 1<br>Motivo {motivos}',
        );

        $this->assertSame("Línea 1\nMotivo PEDAGÓGICOS", $cuerpo);
    }
}
