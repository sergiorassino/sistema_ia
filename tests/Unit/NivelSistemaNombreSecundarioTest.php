<?php

namespace Tests\Unit;

use App\Support\NivelSistema;
use PHPUnit\Framework\TestCase;

class NivelSistemaNombreSecundarioTest extends TestCase
{
    public function test_acepta_secundario_y_nivel_medio(): void
    {
        $this->assertTrue(NivelSistema::nombreEsSecundario('Secundario'));
        $this->assertTrue(NivelSistema::nombreEsSecundario('Nivel Medio'));
        $this->assertTrue(NivelSistema::nombreEsSecundario('MEDIO'));
    }

    public function test_rechaza_otros_niveles(): void
    {
        $this->assertFalse(NivelSistema::nombreEsSecundario('Inicial'));
        $this->assertFalse(NivelSistema::nombreEsSecundario('Primario'));
        $this->assertFalse(NivelSistema::nombreEsSecundario('Terciario'));
        $this->assertFalse(NivelSistema::nombreEsSecundario('Administración'));
        $this->assertFalse(NivelSistema::nombreEsSecundario(''));
        $this->assertFalse(NivelSistema::nombreEsSecundario(null));
    }
}
