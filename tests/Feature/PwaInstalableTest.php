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
        $this->assertSame('./', $p['start_url']);
        $this->assertSame('./app-personal', $p['id']);
        $this->assertSame('Personal', $p['short_name']);

        $this->assertSame('./alumnos', $f['start_url']);
        $this->assertSame('./app-familias', $f['id']);
        $this->assertSame('Familias', $f['short_name']);
        $this->assertNotSame($p['id'], $f['id']);
        $this->assertStringContainsString('pwa-icon/192.png', (string) ($p['icons'][0]['src'] ?? ''));
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
