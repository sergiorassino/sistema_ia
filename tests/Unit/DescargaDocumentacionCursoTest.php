<?php

namespace Tests\Unit;

use App\Support\Alumnos\DescargaDocumentacionCurso;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class DescargaDocumentacionCursoTest extends TestCase
{
    public function test_carpeta_de_alumno_une_apellido_nombre_y_dni(): void
    {
        $this->assertSame(
            'Pérez Juan 12345678',
            DescargaDocumentacionCurso::nombreCarpetaAlumno('Pérez', 'Juan', '12.345.678'),
        );
    }

    public function test_carpeta_de_alumno_conserva_palabras_internas(): void
    {
        $this->assertSame(
            'DE LA CRUZ ANA MARIA 30111222',
            DescargaDocumentacionCurso::nombreCarpetaAlumno('DE  LA   CRUZ', 'ANA MARIA', '30.111.222'),
        );
    }

    public function test_carpeta_de_curso_quita_separadores_de_ruta(): void
    {
        $this->assertSame('1 A', DescargaDocumentacionCurso::nombreCarpetaCurso('1/A'));
        $this->assertSame('Curso', DescargaDocumentacionCurso::nombreCarpetaCurso('  '));
        $this->assertSame('Curso', DescargaDocumentacionCurso::nombreCarpetaCurso('..'));
    }

    public function test_nombres_de_archivo_repiten_la_etiqueta_con_sufijo(): void
    {
        $nombres = DescargaDocumentacionCurso::nombresDeArchivos([
            ['etiqueta' => 'DNI frente', 'extension' => 'jpeg'],
            ['etiqueta' => 'DNI frente', 'extension' => 'jpg'],
            ['etiqueta' => 'Partida', 'extension' => 'pdf'],
        ]);

        $this->assertSame(['DNI frente.jpg', 'DNI frente 2.jpg', 'Partida.pdf'], $nombres);
    }

    public function test_ruta_del_zip_arma_curso_alumno_y_archivo(): void
    {
        $this->assertSame(
            '1A/Pérez Juan 123/DNI frente.jpg',
            DescargaDocumentacionCurso::rutaDentroDelZip('1A', 'Pérez Juan 123', 'DNI frente.jpg'),
        );
        $this->assertSame('', DescargaDocumentacionCurso::rutaDentroDelZip('1A', 'Pérez Juan 123', 'notas.exe'));
    }

    public function test_empaqueta_la_carpeta_del_curso_y_la_del_alumno(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive no está disponible.');
        }

        $origen = tempnam(sys_get_temp_dir(), 'doc_src_');
        $this->assertNotFalse($origen);
        file_put_contents($origen, 'jpg-falso');

        $ruta = DescargaDocumentacionCurso::rutaDentroDelZip('1A', 'PEREZ JUAN 12345678', 'DNI frente.jpg');
        $zip = DescargaDocumentacionCurso::empaquetar([
            ['zip' => $ruta, 'absoluto' => $origen],
        ]);

        $this->assertNotNull($zip);
        $abierto = new ZipArchive;
        $this->assertTrue($abierto->open($zip) === true);
        $this->assertSame(1, $abierto->numFiles);
        $this->assertSame('1A/PEREZ JUAN 12345678/DNI frente.jpg', $abierto->getNameIndex(0));
        $this->assertSame('jpg-falso', $abierto->getFromIndex(0));
        $abierto->close();

        @unlink($origen);
        @unlink($zip);
    }
}
