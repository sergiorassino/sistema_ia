<?php

namespace Tests\Unit;

use App\Support\Examenes\TercerMateriaGestor;
use PHPUnit\Framework\TestCase;

class TercerMateriaValorTmTest extends TestCase
{
    public function test_normaliza_aprob_reprob_y_ausente_sin_importar_mayusculas(): void
    {
        $this->assertSame('Aprob', TercerMateriaGestor::normalizarValorTm('aprob'));
        $this->assertSame('Aprob', TercerMateriaGestor::normalizarValorTm('APROB'));
        $this->assertSame('Aprob', TercerMateriaGestor::normalizarValorTm(' Aprob '));
        $this->assertSame('Reprob', TercerMateriaGestor::normalizarValorTm('reprob'));
        $this->assertSame('Reprob', TercerMateriaGestor::normalizarValorTm('REPROB'));
        $this->assertSame('Reprob', TercerMateriaGestor::normalizarValorTm(' Reprob '));
        $this->assertSame('a', TercerMateriaGestor::normalizarValorTm('a'));
        $this->assertSame('a', TercerMateriaGestor::normalizarValorTm('A'));
        $this->assertSame('a', TercerMateriaGestor::normalizarValorTm(' A '));
    }

    public function test_conserva_notas_numericas_y_vacio(): void
    {
        $this->assertSame('', TercerMateriaGestor::normalizarValorTm(''));
        $this->assertSame('', TercerMateriaGestor::normalizarValorTm('   '));
        $this->assertSame('7', TercerMateriaGestor::normalizarValorTm('7'));
        $this->assertSame('10', TercerMateriaGestor::normalizarValorTm(' 10 '));
    }

    public function test_opciones_selector_incluye_uno_a_diez_ausente_aprob_y_reprob(): void
    {
        $this->assertSame(
            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'a', 'Aprob', 'Reprob'],
            TercerMateriaGestor::opcionesSelector(),
        );
    }

    public function test_filtra_listado_por_alumno_y_cursos(): void
    {
        $filas = [
            [
                'estudiante' => 'Pérez, Ana',
                'curso' => 'TERCERO A',
                'curso_actual' => 'CUARTO B',
            ],
            [
                'estudiante' => 'Gómez, Luis',
                'curso' => 'SEGUNDO C',
                'curso_actual' => 'TERCERO A',
            ],
        ];

        $this->assertCount(1, TercerMateriaGestor::filtrarFilasListado($filas, 'perez', '', ''));
        $this->assertCount(1, TercerMateriaGestor::filtrarFilasListado($filas, 'ana', '', ''));
        $this->assertCount(1, TercerMateriaGestor::filtrarFilasListado($filas, '', 'SEGUNDO C', ''));
        $this->assertCount(1, TercerMateriaGestor::filtrarFilasListado($filas, '', '', 'CUARTO B'));
        $this->assertCount(0, TercerMateriaGestor::filtrarFilasListado($filas, 'perez', 'SEGUNDO C', ''));
        $this->assertSame(
            ['SEGUNDO C', 'TERCERO A'],
            TercerMateriaGestor::opcionesFiltro($filas, 'curso'),
        );
        $this->assertSame(
            ['SEGUNDO C', 'TERCERO A', 'CUARTO A', 'QUINTO B', 'SEXTO A'],
            TercerMateriaGestor::opcionesFiltro([
                ['curso' => 'CUARTO A'],
                ['curso' => 'SEXTO A'],
                ['curso' => 'TERCERO A'],
                ['curso' => 'QUINTO B'],
                ['curso' => 'SEGUNDO C'],
            ], 'curso'),
        );
    }
}
