<?php

namespace Tests\Unit;

use App\Support\Listados\ListadoFamiliasConsulta;
use App\Support\Listados\ListadoFamiliasExport;
use PHPUnit\Framework\TestCase;

class ListadoFamiliasConsultaTest extends TestCase
{
    public function test_curso_y_seccion_usa_campos_c_y_s(): void
    {
        $curso = (object) ['c' => '3', 's' => 'A', 'cursec' => '3° A'];

        $this->assertSame(
            ['curso' => '3', 'seccion' => 'A'],
            ListadoFamiliasConsulta::cursoYSeccion($curso),
        );
    }

    public function test_curso_y_seccion_cae_a_cursec_si_faltan_c_y_s(): void
    {
        $curso = (object) ['c' => '', 's' => '  ', 'cursec' => 'Sala 5 Azul'];

        $this->assertSame(
            ['curso' => 'Sala 5 Azul', 'seccion' => ''],
            ListadoFamiliasConsulta::cursoYSeccion($curso),
        );
    }

    public function test_curso_y_seccion_vacio_si_no_hay_curso(): void
    {
        $this->assertSame(
            ['curso' => '', 'seccion' => ''],
            ListadoFamiliasConsulta::cursoYSeccion(null),
        );
    }

    public function test_etiqueta_curso_nivel_concatena_curso_seccion_y_abrev(): void
    {
        $curso = (object) ['c' => '4', 's' => 'A', 'cursec' => '4° A', 'idNivel' => 2];
        $nivel = (object) ['id' => 2, 'nivel' => 'Primario', 'abrev' => 'P'];

        $this->assertSame('4A (P)', ListadoFamiliasConsulta::etiquetaCursoNivel($curso, $nivel, 2));
    }

    public function test_etiqueta_curso_nivel_usa_letra_de_secundario(): void
    {
        $curso = (object) ['c' => '3', 's' => 'B', 'idNivel' => 3];
        $nivel = (object) ['id' => 3, 'nivel' => 'Secundario', 'abrev' => ''];

        $this->assertSame('3B (S)', ListadoFamiliasConsulta::etiquetaCursoNivel($curso, $nivel, 3));
    }

    public function test_etiqueta_curso_nivel_prioriza_nombre_del_curso_sobre_id_y_abrev(): void
    {
        $curso = (object) ['c' => '1', 's' => 'B', 'idNivel' => 2];
        $nivel = (object) ['id' => 3, 'nivel' => 'Primario', 'abrev' => 'S'];

        $this->assertSame('1B (P)', ListadoFamiliasConsulta::etiquetaCursoNivel($curso, $nivel, 3));
    }

    public function test_etiqueta_curso_nivel_inicial_por_nombre(): void
    {
        $curso = (object) ['c' => 'Sala 5', 's' => '', 'idNivel' => 3];
        $nivel = (object) ['id' => 3, 'nivel' => 'Educación Inicial', 'abrev' => 'S'];

        $this->assertSame('Sala 5 (I)', ListadoFamiliasConsulta::etiquetaCursoNivel($curso, $nivel, 3));
    }

    public function test_etiqueta_curso_nivel_inicial_por_id_si_no_hay_nombre(): void
    {
        $curso = (object) ['c' => 'Sala 5', 's' => '', 'idNivel' => 1];

        $this->assertSame('Sala 5 (I)', ListadoFamiliasConsulta::etiquetaCursoNivel($curso, null, 1));
    }

    public function test_encabezados_export_tienen_nueve_columnas(): void
    {
        $encabezados = ListadoFamiliasExport::encabezados();

        $this->assertCount(9, $encabezados);
        $this->assertSame('Familia', $encabezados[1]);
        $this->assertSame('Apellido', $encabezados[5]);
        $this->assertSame('Curso', $encabezados[8]);
    }
}
