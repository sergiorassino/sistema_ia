<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaInstalableTest extends TestCase
{
    public function test_manifiesto_personal_es_distinto_del_de_familias(): void
    {
        $personal = $this->get(route('pwa.manifest', ['portal' => 'personal']));
        $familias = $this->get(route('pwa.manifest', ['portal' => 'familias']));

        $personal->assertOk();
        $familias->assertOk();

        $p = $personal->json();
        $f = $familias->json();

        $this->assertSame('standalone', $p['display'] ?? null);
        $this->assertStringContainsString('pwa-personal', (string) ($p['start_url'] ?? ''));
        $this->assertStringContainsString('/entrar', (string) ($p['start_url'] ?? ''));
        $this->assertStringContainsString('pwa-personal', (string) ($p['id'] ?? ''));
        $this->assertStringContainsString('pwa-personal', (string) ($p['scope'] ?? ''));
        $this->assertSame('Personal', $p['short_name']);

        $this->assertStringContainsString('pwa-familias', (string) ($f['start_url'] ?? ''));
        $this->assertStringContainsString('/entrar', (string) ($f['start_url'] ?? ''));
        $this->assertStringContainsString('pwa-familias', (string) ($f['id'] ?? ''));
        $this->assertStringContainsString('pwa-familias', (string) ($f['scope'] ?? ''));
        $this->assertSame('Familias', $f['short_name']);
        $this->assertNotSame($p['id'], $f['id']);
        $this->assertNotSame($p['scope'], $f['scope']);
        $this->assertFalse(str_starts_with(rtrim((string) $f['scope'], '/').'/', rtrim((string) $p['scope'], '/').'/'));
        $this->assertFalse(str_starts_with(rtrim((string) $p['scope'], '/').'/', rtrim((string) $f['scope'], '/').'/'));
        $this->assertStringContainsString('icon-se-192.png', (string) ($p['icons'][0]['src'] ?? ''));
        $this->assertStringContainsString('?v=', (string) ($p['icons'][0]['src'] ?? ''));
        $this->assertStringContainsString('http', (string) ($p['icons'][0]['src'] ?? ''));
    }

    public function test_login_personal_enlaza_manifiesto_personal(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('manifest-personal.webmanifest', false);
        $response->assertDontSee('manifest-familias.webmanifest', false);
        $response->assertSee('Instalar en este dispositivo', false);
    }

    public function test_login_familias_enlaza_manifiesto_familias(): void
    {
        $response = $this->get(route('alumnos.login'));

        $response->assertOk();
        $response->assertSee('manifest-familias.webmanifest', false);
        $response->assertDontSee('manifest-personal.webmanifest', false);
        $response->assertSee('Instalar en este dispositivo', false);
    }

    public function test_entrar_redirige_al_login_de_personal(): void
    {
        $this->get(route('pwa.inicio'))->assertRedirect(route('login'));
    }

    public function test_lanzar_personal_y_familias_no_dan_404(): void
    {
        $this->get(route('pwa.lanzar', ['portal' => 'personal']))->assertRedirect(route('login'));
        $this->get(route('pwa.lanzar', ['portal' => 'familias']))->assertRedirect(route('alumnos.login'));
    }

    public function test_login_prefijado_personal_enlaza_manifiesto_personal(): void
    {
        $response = $this->get('/pwa-personal/loginUsuario');

        $response->assertOk();
        $response->assertSee('manifest-personal.webmanifest', false);
        $response->assertSee('pwa-personal', false);
    }

    public function test_login_prefijado_familias_enlaza_manifiesto_familias(): void
    {
        $response = $this->get('/pwa-familias/loginEstudiante');

        $response->assertOk();
        $response->assertSee('manifest-familias.webmanifest', false);
        $response->assertSee('pwa-familias', false);
    }

    public function test_entrar_prefijado_familias_va_al_login_de_estudiantes(): void
    {
        $response = $this->get('/pwa-familias/entrar');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('loginEstudiante', $location);
        $this->assertStringContainsString('pwa-familias', $location);
    }

    public function test_icono_pwa_png(): void
    {
        $response = $this->get(route('pwa.icon', ['size' => 192]));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_service_worker_ruta_javascript(): void
    {
        $response = $this->get(route('pwa.sw'));

        $response->assertOk();
        $this->assertStringContainsString(
            'javascript',
            strtolower((string) $response->headers->get('content-type'))
        );
        $response->assertSee("addEventListener('push'", false);
    }

    public function test_service_worker_incluye_push_y_no_cachea_html(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('push'", $sw);
        $this->assertStringContainsString("addEventListener('fetch'", $sw);
        $this->assertStringContainsString("req.mode !== 'navigate'", $sw);
    }
}
