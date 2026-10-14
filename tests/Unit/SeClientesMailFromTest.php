<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SeClientesMailFromTest extends TestCase
{
    public function test_lee_remitente_desde_la_config_del_mailer(): void
    {
        Config::set('mail.mailers.sistemas_escolares', [
            'host' => 'smtp.gmail.com',
            'username' => 'clientes@example.com',
            'password' => 'app-password',
            'from' => [
                'address' => 'clientes@example.com',
                'name' => 'Sistemas Escolares',
            ],
        ]);

        $from = seClientesMailFrom();

        $this->assertSame([
            'address' => 'clientes@example.com',
            'name' => 'Sistemas Escolares',
        ], $from);
    }

    public function test_usa_el_usuario_smtp_si_no_hay_from_address(): void
    {
        Config::set('mail.mailers.sistemas_escolares', [
            'host' => 'smtp.gmail.com',
            'username' => 'clientes@example.com',
            'password' => 'app-password',
        ]);

        $from = seClientesMailFrom();

        $this->assertSame('clientes@example.com', $from['address'] ?? null);
        $this->assertNotSame('', $from['name'] ?? '');
    }

    public function test_sin_contrasena_no_esta_configurado(): void
    {
        Config::set('mail.mailers.sistemas_escolares', [
            'host' => 'smtp.gmail.com',
            'username' => 'clientes@example.com',
            'password' => '',
            'from' => [
                'address' => 'clientes@example.com',
                'name' => 'Sistemas Escolares',
            ],
        ]);

        $this->assertNull(seClientesMailFrom());
    }
}
