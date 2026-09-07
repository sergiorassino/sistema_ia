<?php

namespace Tests\Unit;

use App\Support\Seguimiento\NotificarDocentesGabinete;
use PHPUnit\Framework\TestCase;

class NotificarDocentesGabineteTextoTest extends TestCase
{
    public function test_el_mensaje_empieza_con_la_introduccion_fija_y_luego_los_datos(): void
    {
        $texto = NotificarDocentesGabinete::textoMensaje([
            'alumno'     => 'PÉREZ, Ana',
            'curso'      => 'PRIMERO A',
            'ciclo'      => '2026',
            'fecha'      => '07/09/2026',
            'tipo'       => 'Entrevista',
            'solicitud'  => 'Preceptoría',
            'motivo'     => 'Seguimiento pedagógico',
            'asistentes' => 'Madre y DOE',
            'conclusion' => 'Continuar apoyo semanal',
        ]);

        $esperado = NotificarDocentesGabinete::INTRO
            ."\n\n"
            ."Alumno/a: PÉREZ, Ana\n"
            ."Curso: PRIMERO A\n"
            ."Ciclo lectivo: 2026\n"
            ."Fecha: 07/09/2026\n"
            ."Tipo: Entrevista\n"
            ."Solicitud: Preceptoría\n"
            ."Motivo: Seguimiento pedagógico\n"
            ."Asistentes: Madre y DOE\n"
            .'Conclusión: Continuar apoyo semanal';

        $this->assertSame($esperado, $texto);
        $this->assertStringStartsWith('Sres. Profesores:', $texto);
    }

    public function test_campos_vacios_quedan_como_raya_y_omite_ciclo_si_no_hay(): void
    {
        $texto = NotificarDocentesGabinete::textoMensaje([
            'alumno' => 'GÓMEZ, Luis',
            'curso'  => '',
            'fecha'  => '',
        ]);

        $this->assertStringContainsString('Alumno/a: GÓMEZ, Luis', $texto);
        $this->assertStringContainsString('Curso: —', $texto);
        $this->assertStringContainsString('Fecha: —', $texto);
        $this->assertStringContainsString('Tipo: —', $texto);
        $this->assertStringNotContainsString('Ciclo lectivo:', $texto);
    }

    public function test_asunto_incluye_el_alumno(): void
    {
        $this->assertSame(
            'Seguimiento de gabinete — PÉREZ, Ana',
            NotificarDocentesGabinete::asunto('PÉREZ, Ana')
        );
        $this->assertSame('Seguimiento de gabinete', NotificarDocentesGabinete::asunto(''));
    }

    public function test_medios_efectivos_solo_push_y_email(): void
    {
        $this->assertSame(
            ['push', 'email'],
            NotificarDocentesGabinete::mediosEfectivos(['push', 'email', 'whatsapp'])
        );
        $this->assertSame(['push'], NotificarDocentesGabinete::mediosEfectivos(['push', 'whatsapp']));
        $this->assertSame([], NotificarDocentesGabinete::mediosEfectivos(['whatsapp']));
    }

    public function test_destinatarios_incluyen_docentes_directivos_preceptores_y_gabinete(): void
    {
        $this->assertTrue(NotificarDocentesGabinete::esDestinatarioCompartir('Profesor/a'));
        $this->assertTrue(NotificarDocentesGabinete::esDestinatarioCompartir('ATP'));
        $this->assertTrue(NotificarDocentesGabinete::esDestinatarioCompartir('DOE'));
        $this->assertTrue(NotificarDocentesGabinete::esDestinatarioCompartir('Directivo'));
        $this->assertTrue(NotificarDocentesGabinete::esDestinatarioCompartir('Secretaría'));
        $this->assertTrue(NotificarDocentesGabinete::esDestinatarioCompartir('Preceptor/a'));
        $this->assertTrue(NotificarDocentesGabinete::esDestinatarioCompartir('Gabinete de orientación'));
        $this->assertTrue(NotificarDocentesGabinete::esDestinatarioCompartir('Psicopedagoga'));
        $this->assertFalse(NotificarDocentesGabinete::esDestinatarioCompartir('Sin Rol'));
        $this->assertFalse(NotificarDocentesGabinete::esDestinatarioCompartir('Bibliotecario/a'));
        $this->assertFalse(NotificarDocentesGabinete::esDestinatarioCompartir('No docente'));
        $this->assertFalse(NotificarDocentesGabinete::esDestinatarioCompartir(''));
    }
}
