<?php

namespace Tests\Unit;

use App\Support\Configuracion\CopiarAsignacionesProfPrecepAnio;
use App\Support\NivelSistema;
use PHPUnit\Framework\TestCase;

class CopiarAsignacionesProfPrecepAnioTest extends TestCase
{
    public function test_clave_materia_destino_normaliza_c_y_s(): void
    {
        $this->assertSame('3|12|1|A', CopiarAsignacionesProfPrecepAnio::claveMateriaDestino(3, 12, '1', 'A'));
        $this->assertSame('3|12|1|A', CopiarAsignacionesProfPrecepAnio::claveMateriaDestino(3, 12, ' 1 ', ' A '));
        $this->assertSame('2|0||', CopiarAsignacionesProfPrecepAnio::claveMateriaDestino(2, null, null, null));
        $this->assertNotSame(
            CopiarAsignacionesProfPrecepAnio::claveMateriaDestino(1, 10, '1', 'A'),
            CopiarAsignacionesProfPrecepAnio::claveMateriaDestino(2, 10, '1', 'A'),
        );
    }

    public function test_clave_ppc_y_preceptor(): void
    {
        $this->assertSame('44|9', CopiarAsignacionesProfPrecepAnio::clavePpc(44, 9));
        $this->assertSame('7|3', CopiarAsignacionesProfPrecepAnio::clavePreceptor(7, 3));
        $this->assertSame('1|2', CopiarAsignacionesProfPrecepAnio::clavePpc(1, 2));
        $this->assertSame('1|2', CopiarAsignacionesProfPrecepAnio::clavePreceptor(1, 2));
    }

    public function test_niveles_validos_excluye_administracion_y_ceros(): void
    {
        $this->assertSame(
            [1, 2, 3],
            CopiarAsignacionesProfPrecepAnio::nivelesValidos([1, 2, 2, 3, NivelSistema::ADMINISTRACION, 0, '']),
        );
        $this->assertSame([], CopiarAsignacionesProfPrecepAnio::nivelesValidos([]));
    }
}
