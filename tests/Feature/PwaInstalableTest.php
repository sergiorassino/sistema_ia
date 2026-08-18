<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaInstalableTest extends TestCase
{
    public function test_manifest_publico_es_instalable(): void
    {
        $response = $this->get(route('pwa.manifest'));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/manifest+json',
            (string) $response->headers->get('content-type')
        );

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertSame('standalone', $data['display'] ?? null);
        $this->assertNotEmpty($data['name'] ?? null);
        $this->assertNotEmpty($data['start_url'] ?? null);
        $this->assertNotEmpty($data['scope'] ?? null);
        $this->assertNotEmpty($data['icons'] ?? null);
        $this->assertSame('./entrar', $data['start_url']);
        $this->assertSame('./', $data['scope']);
        $this->assertSame('./', $data['id']);
        $this->assertStringContainsString('pwa-icon/192.png', (string) ($data['icons'][0]['src'] ?? ''));
    }

    public function test_icono_pwa_png(): void
    {
        $response = $this->get(route('pwa.icon', ['size' => 192]));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_inicio_pwa_invitado_muestra_portales(): void
    {
        $response = $this->get(route('pwa.inicio'));

        $response->assertOk();
        $response->assertSee('Personal de la institución', false);
        $response->assertSee('Familias y estudiantes', false);
        $response->assertSee('Instalar en este dispositivo', false);
        $response->assertSee(route('login'), false);
        $response->assertSee(route('alumnos.login'), false);
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
