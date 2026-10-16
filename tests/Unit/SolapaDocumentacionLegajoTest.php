<?php

namespace Tests\Unit;

use App\Support\Alumnos\SolapaDocumentacionLegajo;
use PHPUnit\Framework\TestCase;

class SolapaDocumentacionLegajoTest extends TestCase
{
    public function test_queda_a_la_derecha_de_foto_carnet(): void
    {
        $tabs = SolapaDocumentacionLegajo::insertar([
            'alumno' => 'Alumno',
            'domicilio' => 'Domicilio',
            'foto_carnet' => 'Foto Carnet',
            'escolar' => 'Escolaridad',
        ], true);

        $this->assertSame(
            ['alumno', 'domicilio', 'foto_carnet', 'documentacion', 'escolar'],
            array_keys($tabs),
        );
        $this->assertSame('DOCUMENTACIÓN', $tabs['documentacion']);
    }

    public function test_no_aparece_si_no_hay_tipos(): void
    {
        $tabs = [
            'alumno' => 'Alumno',
            'foto_carnet' => 'Foto Carnet',
        ];

        $this->assertSame($tabs, SolapaDocumentacionLegajo::insertar($tabs, false));
    }

    public function test_si_no_hay_solapa_foto_carnet_queda_al_final(): void
    {
        $tabs = SolapaDocumentacionLegajo::insertar([
            'alumno' => 'Alumno',
            'madre' => 'Madre',
        ], true);

        $this->assertSame(['alumno', 'madre', 'documentacion'], array_keys($tabs));
    }

    public function test_reconoce_la_etiqueta_foto_carnet_si_el_slug_es_otro(): void
    {
        $tabs = SolapaDocumentacionLegajo::insertar([
            'alumno' => 'Alumno',
            'foto' => 'Foto Carnet',
            'otros' => 'Otros',
        ], true);

        $this->assertSame(['alumno', 'foto', 'documentacion', 'otros'], array_keys($tabs));
    }
}
