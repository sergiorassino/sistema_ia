<?php

namespace Tests\Feature;

use Tests\TestCase;

class FaviconIcoTest extends TestCase
{
    public function test_favicon_ico_sirve_png_sin_redirigir_al_login(): void
    {
        $response = $this->get('/favicon.ico');

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
    }

    public function test_icono_escuela_sirve_png_sin_redirigir_al_login(): void
    {
        $response = $this->get('/icono-escuela.png');

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
    }
}
