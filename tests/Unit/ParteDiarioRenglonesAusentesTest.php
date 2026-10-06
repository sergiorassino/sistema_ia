<?php

namespace Tests\Unit;

use Tests\TestCase;

class ParteDiarioRenglonesAusentesTest extends TestCase
{
    public function test_sin_valor_en_tenant_usa_doce_renglones_de_4mm(): void
    {
        config(['tenant.parte_diario.renglones_ausentes' => null]);

        $this->assertSame(12, tenantParteDiarioRenglonesAusentes());

        $html = $this->htmlParte();
        $this->assertSame(36, substr_count($html, 'class="celda-manual"'));
        $this->assertSame(36, substr_count($html, 'height:4.00mm'));
    }

    public function test_cantidad_declarada_reparte_el_alto_de_los_doce_renglones(): void
    {
        config(['tenant.parte_diario.renglones_ausentes' => 8]);

        $this->assertSame(8, tenantParteDiarioRenglonesAusentes());

        $html = $this->htmlParte();
        $this->assertSame(24, substr_count($html, 'class="celda-manual"'));
        $this->assertSame(24, substr_count($html, 'height:6.00mm'));
    }

    public function test_valor_fuera_de_rango_vuelve_a_doce(): void
    {
        config(['tenant.parte_diario.renglones_ausentes' => 0]);
        $this->assertSame(12, tenantParteDiarioRenglonesAusentes());

        config(['tenant.parte_diario.renglones_ausentes' => 30]);
        $this->assertSame(12, tenantParteDiarioRenglonesAusentes());

        config(['tenant.parte_diario.renglones_ausentes' => '6']);
        $this->assertSame(6, tenantParteDiarioRenglonesAusentes());
    }

    private function htmlParte(): string
    {
        return view('pdf.parte-diario-preceptor', [
            'pdfHeader' => [
                'logo_file' => '',
                'insti' => 'Colegio',
                'direccion' => '',
                'localidad' => '',
                'cue' => '',
                'ee' => '',
            ],
            'metaCiclo' => 'Nivel Secundario · Ciclo 2026',
            'paginas' => [[
                'subtitulo' => 'PARTE DIARIO DEL PRECEPTOR — PRIMERO A',
                'lineaDia' => 'Día: Martes',
                'fechaTexto' => '06/10/2026',
                'turnoTitulo' => 'Mañana',
                'filasHorario' => [
                    ['etiquetaReloj' => "1º HORA: 7.30 a\n8.15", 'espacio' => "LENGUA\nDocente"],
                ],
            ]],
        ])->render();
    }
}
