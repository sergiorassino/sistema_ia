<?php

namespace Tests\Feature;

use Tests\TestCase;

class AutogestionSesionNavegacionTest extends TestCase
{
    public function test_se_route_url_usa_el_host_de_la_peticion_no_el_de_app_url(): void
    {
        config(['app.url' => 'http://otro-host.ejemplo:8000']);

        $this->get('http://127.0.0.1/loginEstudiante');

        $url = se_route_url('alumnos.calificaciones');

        $this->assertSame('127.0.0.1', parse_url($url, PHP_URL_HOST));
        $this->assertStringContainsString('/alumnos/calificaciones', (string) parse_url($url, PHP_URL_PATH));
    }

    public function test_invitado_en_ruta_alumnos_va_al_login_estudiante(): void
    {
        $response = $this->get('/alumnos/calificaciones');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('loginEstudiante', $location);
    }
}
