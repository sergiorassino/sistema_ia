<?php

namespace Tests\Feature;

use App\Models\Ento;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Support\StudentContext;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Reproduce login alumno → navegación de menú sin perder sesión (BD real local).
 */
class AutogestionNavegacionRealTest extends TestCase
{
    public function test_acting_as_alumno_puede_abrir_aranceles_sin_volver_al_login(): void
    {
        $legajo = $this->legajoConMatriculaAutogestion();
        if ($legajo === null) {
            $this->markTestSkipped('No hay legajo con matrícula usable en idTerlecVerNotas.');
        }

        $this->actingAs($legajo, 'alumno');
        $ok = StudentContext::establecerDesdeLegajo($legajo);
        $this->assertTrue($ok, 'No se pudo armar StudentContext');

        $this->assertTrue(Auth::guard('alumno')->check());
        $this->assertTrue(studentCtx()->isValid());

        $home = $this->get(route('alumnos.home'));
        $home->assertSuccessful();
        $home->assertDontSee('Acceso estudiantes', false);

        $aranceles = $this->get(route('alumnos.aranceles-escolares'));
        if ($aranceles->isRedirect()) {
            $this->fail(
                'Aranceles redirigió a: '.$aranceles->headers->get('Location').
                ' session='.json_encode(session()->all())
            );
        }
        $aranceles->assertSuccessful();
        $aranceles->assertDontSee('Acceso estudiantes', false);

        $datos = $this->get(route('alumnos.actualizacion-datos'));
        if ($datos->isRedirect()) {
            $loc = (string) $datos->headers->get('Location');
            if (str_contains($loc, 'loginEstudiante')) {
                $this->fail('Actualización de datos volvió al login: '.$loc);
            }
        }
        $this->assertTrue(Auth::guard('alumno')->check(), 'Auth alumno se perdió tras navegar');
        $this->assertTrue(studentCtx()->isValid(), 'StudentContext inválido tras navegar');
    }

    public function test_login_autenticado_no_limpia_sesion_alumno(): void
    {
        $legajo = $this->legajoConMatriculaAutogestion();
        if ($legajo === null) {
            $this->markTestSkipped('No hay legajo con matrícula usable en idTerlecVerNotas.');
        }

        $this->actingAs($legajo, 'alumno');
        $this->assertTrue(StudentContext::establecerDesdeLegajo($legajo));

        $response = $this->get(route('alumnos.login'));
        $response->assertRedirect();
        $this->assertStringContainsString('/alumnos', (string) $response->headers->get('Location'));
        $this->assertTrue(Auth::guard('alumno')->check(), 'Abrir login no debe cerrar sesión alumno activa');
    }

    private function legajoConMatriculaAutogestion(): ?Legajo
    {
        $entos = Ento::query()
            ->whereNotNull('idTerlecVerNotas')
            ->where('idTerlecVerNotas', '>', 0)
            ->get(['idNivel', 'idTerlecVerNotas']);

        foreach ($entos as $ento) {
            $idNivel = (int) $ento->idNivel;
            $idTerlec = (int) $ento->idTerlecVerNotas;
            if ($idNivel <= 0 || $idTerlec <= 0) {
                continue;
            }

            $idLegajo = Matricula::query()
                ->where('idNivel', $idNivel)
                ->where('idTerlec', $idTerlec)
                ->where('idCursos', '>', 0)
                ->orderByDesc('id')
                ->value('idLegajos');

            if (! $idLegajo) {
                continue;
            }

            $legajo = Legajo::query()->whereKey((int) $idLegajo)->first();
            if ($legajo !== null && trim((string) ($legajo->dni ?? '')) !== '') {
                return $legajo;
            }
        }

        return null;
    }
}
