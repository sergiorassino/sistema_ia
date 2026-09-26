<?php

namespace Tests\Unit;

use App\Support\ProyectosExtracurriculares\AutorizacionActividadRedaccion;
use App\Support\ProyectosExtracurriculares\AutorizacionActividadTcpdf;
use DateTimeImmutable;
use Tests\TestCase;

class AutorizacionActividadRedaccionTest extends TestCase
{
    public function test_fecha_larga_en_espanol(): void
    {
        $this->assertSame(
            '16 de octubre de 2026',
            AutorizacionActividadRedaccion::fechaLarga(new DateTimeImmutable('2026-10-16'))
        );
    }

    public function test_enumerar_nombres(): void
    {
        $this->assertSame(
            'Raquel Monetti, Melisa Giraudo y Ana Paula Sosa',
            AutorizacionActividadRedaccion::enumerar(['Raquel Monetti', 'Melisa Giraudo', 'Ana Paula Sosa'])
        );
    }

    public function test_cronograma_omite_secciones_vacias_de_la_plantilla(): void
    {
        $texto = "Actividades Previas:\n\nActividades Durante:\nVisita al Jardín Botánico y almuerzo.\n\nActividades Posteriores:\n";

        $secciones = AutorizacionActividadRedaccion::seccionesCronograma($texto);

        $this->assertCount(1, $secciones);
        $this->assertSame('Actividades Durante', $secciones[0]['titulo']);
        $this->assertSame('Visita al Jardín Botánico y almuerzo.', $secciones[0]['texto']);
    }

    public function test_plantilla_vacia_no_genera_cronograma(): void
    {
        $this->assertSame(
            [],
            AutorizacionActividadRedaccion::seccionesCronograma("Actividades Previas:\n\nActividades Durante:\n\nActividades Posteriores:\n")
        );
    }

    public function test_parrafo_toma_datos_del_alumno_y_del_viaje(): void
    {
        $parrafo = AutorizacionActividadRedaccion::parrafoAutorizacion(
            $this->alumno(),
            $this->viaje()
        );

        $this->assertStringContainsString('AUTORIZO a mi hijo/a García, Lucía', $parrafo);
        $this->assertStringContainsString('D.N.I Nº 45123456', $parrafo);
        $this->assertStringContainsString('Grupo y Factor sanguíneo 0+', $parrafo);
        $this->assertStringContainsString('Curso 3ro división A', $parrafo);
        $this->assertStringContainsString('Calle San Martín Número 120', $parrafo);
        $this->assertStringContainsString('Localidad de General Deheza', $parrafo);
        $this->assertStringContainsString('salida desde General Deheza hasta Córdoba Capital', $parrafo);
        $this->assertStringContainsString('para participar de visitas guiadas al Jardín Botánico', $parrafo);
        $this->assertStringContainsString('a realizarse el día 16 de octubre de 2026', $parrafo);
        $this->assertStringContainsString('acompañado/a de Raquel Monetti', $parrafo);
        $this->assertStringContainsString('según Agenda, desde el inicio al final del viaje:', $parrafo);
        $this->assertStringNotContainsString('05:00', $parrafo);
    }

    public function test_detalle_sigue_el_orden_del_formulario(): void
    {
        $viaje = $this->viaje();

        $this->assertSame(
            'El traslado de los estudiantes hasta el punto de salida el día 16/10/2026, al igual que el regreso el día 16/10/2026 estará a cargo de ______________________',
            AutorizacionActividadRedaccion::fraseTraslado($viaje)
        );
        $this->assertSame([
            'SALIDA: Fecha y hora. 16 de octubre de 2026 a las 5:00 hs',
            'REGRESO: Fecha y hora. 16 de octubre de 2026 a las 22:00 hs.',
            'TRANSPORTE: ______________________________',
        ], AutorizacionActividadRedaccion::lineasDetalle($viaje));
    }

    public function test_genera_pdf_de_autorizacion(): void
    {
        $viaje = $this->viaje();

        $pdf = AutorizacionActividadTcpdf::generarLote([
            'insti' => 'Instituto "25 de Mayo"',
            'categoria' => 'PRIMERA',
            'subtitulo' => 'DE ENSEÑANZA PRIVADA',
            'adscripcion' => 'Adscripto a la Provincia Ley Nacional Nº 24049/91 y Provincial Nº 8253/92',
            'direccion' => 'Bv. San Martín 136',
            'telefono' => '(0358) 4050102',
            'mail' => 'inst25demayo@gmail.com',
            'localidad' => 'General Deheza',
            'provincia' => 'Córdoba',
            'departamento' => '',
            'cue' => '1401234',
            'ee' => '',
            'logo_file' => null,
        ], $viaje, [$this->alumno()]);

        $this->assertSame(1, $pdf->getNumPages());
        $binario = $pdf->Output('autorizacion.pdf', 'S');

        $this->assertStringStartsWith('%PDF', $binario);
    }

    /** @return array{apellido: string, nombre: string, dni: string, grupo_sanguineo: string, curso: string, division: string, calle: string, numero: string, localidad: string} */
    private function alumno(): array
    {
        return [
            'apellido' => 'García',
            'nombre' => 'Lucía',
            'dni' => '45123456',
            'grupo_sanguineo' => '0+',
            'curso' => '3ro',
            'division' => 'A',
            'calle' => 'San Martín',
            'numero' => '120',
            'localidad' => 'General Deheza',
        ];
    }

    /**
     * @return array{
     *   nombre: string,
     *   lugar: string,
     *   localidad_salida: string,
     *   institucion: string,
     *   acompanantes: string,
     *   horario: string,
     *   participacion: string,
     *   jornadas: list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>
     * }
     */
    private function viaje(): array
    {
        return [
            'nombre' => 'Viaje educativo a Córdoba Capital',
            'lugar' => 'Córdoba Capital',
            'localidad_salida' => 'General Deheza',
            'institucion' => 'Instituto 25 de Mayo',
            'acompanantes' => 'Raquel Monetti, Melisa Giraudo y Ana Paula Sosa',
            'horario' => 'Agencia de Viajes Trip Magic',
            'participacion' => 'visitas guiadas al Jardín Botánico',
            'jornadas' => [[
                'fecha' => '16/10/2026',
                'fecha_larga' => '16 de octubre de 2026',
                'inicio' => '05:00',
                'fin' => '22:00',
            ]],
        ];
    }
}
