<?php

namespace Tests\Unit;

use App\Support\Seguimiento\GabineteSemaforo;
use PHPUnit\Framework\TestCase;

class GabineteSemaforoTest extends TestCase
{
    public function test_normaliza_colores_validos_e_invalidos(): void
    {
        $this->assertSame(GabineteSemaforo::VERDE, GabineteSemaforo::normalizar(1));
        $this->assertSame(GabineteSemaforo::AMARILLO, GabineteSemaforo::normalizar('2'));
        $this->assertSame(GabineteSemaforo::ROJO, GabineteSemaforo::normalizar(3));
        $this->assertSame(GabineteSemaforo::NINGUNO, GabineteSemaforo::normalizar(0));
        $this->assertSame(GabineteSemaforo::NINGUNO, GabineteSemaforo::normalizar(9));
        $this->assertSame(GabineteSemaforo::NINGUNO, GabineteSemaforo::normalizar(null));
    }

    public function test_filtro_de_listado(): void
    {
        $this->assertSame('verde', GabineteSemaforo::normalizarFiltro('VERDE'));
        $this->assertSame('sin', GabineteSemaforo::normalizarFiltro('sin'));
        $this->assertSame('', GabineteSemaforo::normalizarFiltro('azul'));
        $this->assertSame(GabineteSemaforo::ROJO, GabineteSemaforo::colorDesdeFiltro('rojo'));
        $this->assertSame(GabineteSemaforo::NINGUNO, GabineteSemaforo::colorDesdeFiltro(''));
    }

    public function test_clase_fondo_del_nombre(): void
    {
        $this->assertSame('se-gabinete-nombre--verde', GabineteSemaforo::claseFondoNombre(1));
        $this->assertSame('se-gabinete-nombre--amarillo', GabineteSemaforo::claseFondoNombre(2));
        $this->assertSame('se-gabinete-nombre--rojo', GabineteSemaforo::claseFondoNombre(3));
        $this->assertSame('', GabineteSemaforo::claseFondoNombre(0));
    }

    public function test_rgb_fondo_pdf_es_mas_saturado_en_amarillo(): void
    {
        $this->assertSame([102, 187, 106], GabineteSemaforo::rgbFondoPdf(GabineteSemaforo::VERDE));
        $this->assertSame([255, 202, 40], GabineteSemaforo::rgbFondoPdf(GabineteSemaforo::AMARILLO));
        $this->assertSame([239, 154, 154], GabineteSemaforo::rgbFondoPdf(GabineteSemaforo::ROJO));
        $this->assertSame([255, 255, 255], GabineteSemaforo::rgbFondoPdf(GabineteSemaforo::NINGUNO));
    }
}
