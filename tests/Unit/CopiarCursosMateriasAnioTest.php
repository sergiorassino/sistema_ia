<?php

namespace Tests\Unit;

use App\Support\Configuracion\CopiarCursosMateriasAnio;
use App\Support\NivelSistema;
use PHPUnit\Framework\TestCase;

class CopiarCursosMateriasAnioTest extends TestCase
{
    public function test_clave_division_normaliza_c_y_s(): void
    {
        $this->assertSame('3|1|A', CopiarCursosMateriasAnio::claveDivision(3, '1', 'A'));
        $this->assertSame('3|1|A', CopiarCursosMateriasAnio::claveDivision(3, ' 1 ', ' A '));
        $this->assertSame('2||', CopiarCursosMateriasAnio::claveDivision(2, null, null));
        $this->assertNotSame(
            CopiarCursosMateriasAnio::claveDivision(1, '1', 'A'),
            CopiarCursosMateriasAnio::claveDivision(2, '1', 'A'),
        );
    }

    public function test_niveles_validos_excluye_administracion_y_ceros(): void
    {
        $this->assertSame(
            [1, 2, 3],
            CopiarCursosMateriasAnio::nivelesValidos([1, 2, 2, 3, NivelSistema::ADMINISTRACION, 0, '']),
        );
        $this->assertSame([], CopiarCursosMateriasAnio::nivelesValidos([]));
    }
}
