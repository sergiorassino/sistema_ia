<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionResumen;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionResumenTest extends TestCase
{
    public function test_debe_mostrar_modal_con_advertencias(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 3, omitidos: 1);
        $resumen->agregarAdvertencia('Línea 2: cupón no encontrado.');

        $this->assertTrue($resumen->debeMostrarModal());
    }

    public function test_debe_mostrar_modal_en_descarga_con_registros(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 2);
        $resumen->agregarRegistroArchivo([
            'linea' => 1,
            'canal' => 'PF',
            'idFacturaBuscado' => '00003284000008601086',
            'estado' => 'encontrado',
            'detalle' => null,
        ]);

        $this->assertTrue($resumen->debeMostrarModal('descarga'));
        $this->assertFalse($resumen->debeMostrarModal('impacto'));
    }

    public function test_para_modal_incluye_todas_las_lineas_en_orden(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 1, omitidos: 2);
        $resumen->agregarError('La planilla no tiene pagos descargados para impactar.');
        $resumen->agregarAdvertencia('Línea 4: formato inválido.');
        $resumen->agregarAdvertencia('Línea 9: cupón no encontrado.');

        $modal = $resumen->paraModal('Resultado', 'descarga');

        $this->assertSame('Resultado', $modal['titulo']);
        $this->assertSame('descarga', $modal['contexto']);
        $this->assertSame([
            'La planilla no tiene pagos descargados para impactar.',
            'Línea 4: formato inválido.',
            'Línea 9: cupón no encontrado.',
        ], $modal['problemas']);
    }

    public function test_no_debe_mostrar_modal_sin_problemas(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 5, impactados: 5);

        $this->assertFalse($resumen->debeMostrarModal());
        $this->assertSame('Registros procesados: 5. Cuotas impactadas: 5.', $resumen->mensajeExitoBreve());
    }
}
