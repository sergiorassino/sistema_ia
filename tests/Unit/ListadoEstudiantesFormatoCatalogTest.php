<?php

namespace Tests\Unit;

use App\Support\Listados\ListadoEstudiantesFormatoCatalog;
use App\Support\Listados\ListadoEstudiantesFormatoTamanoFoto;
use PHPUnit\Framework\TestCase;

class ListadoEstudiantesFormatoCatalogTest extends TestCase
{
    public function test_incluye_modelo_fotos(): void
    {
        $this->assertContains(ListadoEstudiantesFormatoCatalog::MODELO_FOTOS, ListadoEstudiantesFormatoCatalog::keys());
        $this->assertSame('Listado de Fotos', ListadoEstudiantesFormatoCatalog::etiqueta('fotos'));
        $this->assertTrue(ListadoEstudiantesFormatoCatalog::requiereTamanoFoto('fotos'));
        $this->assertFalse(ListadoEstudiantesFormatoCatalog::requiereTamanoFoto('cuadriculado'));
    }

    public function test_tamano_foto_normaliza_y_convierte_a_mm(): void
    {
        $this->assertSame(ListadoEstudiantesFormatoTamanoFoto::MEDIANO, ListadoEstudiantesFormatoTamanoFoto::normalize(null));
        $this->assertSame(ListadoEstudiantesFormatoTamanoFoto::PEQUENO, ListadoEstudiantesFormatoTamanoFoto::normalize('pequeno'));
        $this->assertSame(20.0, ListadoEstudiantesFormatoTamanoFoto::ladoMm('pequeno'));
        $this->assertSame(40.0, ListadoEstudiantesFormatoTamanoFoto::ladoMm('mediano'));
        $this->assertSame(80.0, ListadoEstudiantesFormatoTamanoFoto::ladoMm('grande'));
        $this->assertSame('Pequeño (2×2 cm)', ListadoEstudiantesFormatoTamanoFoto::etiqueta('pequeno'));
    }

    public function test_para_ui_oculta_fotos_si_no_estan_habilitadas(): void
    {
        $keys = array_column(ListadoEstudiantesFormatoCatalog::paraUi(false), 'key');

        $this->assertNotContains(ListadoEstudiantesFormatoCatalog::MODELO_FOTOS, $keys);
        $this->assertSame(
            ListadoEstudiantesFormatoCatalog::keysPermitidos(false),
            $keys,
        );
    }

    public function test_para_ui_incluye_fotos_si_estan_habilitadas(): void
    {
        $keys = array_column(ListadoEstudiantesFormatoCatalog::paraUi(true), 'key');

        $this->assertSame(ListadoEstudiantesFormatoCatalog::keys(), $keys);
        $this->assertContains(ListadoEstudiantesFormatoCatalog::MODELO_FOTOS, $keys);
    }

    public function test_normalize_rechaza_fotos_si_no_estan_habilitadas(): void
    {
        $this->assertSame(
            ListadoEstudiantesFormatoCatalog::MODELO_CUADRICULADO,
            ListadoEstudiantesFormatoCatalog::normalize('fotos', false),
        );
        $this->assertSame(
            ListadoEstudiantesFormatoCatalog::MODELO_FOTOS,
            ListadoEstudiantesFormatoCatalog::normalize('fotos', true),
        );
    }
}
