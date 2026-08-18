<?php

namespace Tests\Unit;

use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionResumen;
use PHPUnit\Framework\TestCase;

class SiroDescargaRendicionResumenTest extends TestCase
{
    public function test_debe_mostrar_modal_con_advertencias(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 3, omitidos: 1);
        $resumen->agregarAdvertencia('Cupón no encontrado.', 2);

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

    public function test_para_modal_incluye_problemas_con_numero_de_registro(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 1, omitidos: 2);
        $resumen->agregarError('La planilla no tiene pagos descargados para impactar.');
        $resumen->agregarAdvertencia('Formato inválido.', 4);
        $resumen->agregarAdvertencia('Cupón no encontrado.', 9);
        $resumen->agregarAdvertencia('Importes NO coinciden.', 9);

        $modal = $resumen->paraModal('Resultado', 'descarga');

        $this->assertSame('Resultado', $modal['titulo']);
        $this->assertSame('descarga', $modal['contexto']);
        $this->assertSame([
            ['linea' => null, 'mensaje' => 'La planilla no tiene pagos descargados para impactar.'],
            ['linea' => 4, 'mensaje' => 'Formato inválido.'],
            ['linea' => 9, 'mensaje' => 'Cupón no encontrado.'],
            ['linea' => 9, 'mensaje' => 'Importes NO coinciden.'],
        ], $modal['problemas']);
    }

    public function test_no_debe_mostrar_modal_sin_problemas(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 5, impactados: 5);

        $this->assertFalse($resumen->debeMostrarModal());
        $this->assertSame('Registros procesados: 5. Cuotas impactadas: 5.', $resumen->mensajeExitoBreve());
    }

    public function test_encabezado_incluye_rechazos_siro(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 9, rechazos: 2);

        $this->assertSame([
            'Registros procesados: 9.',
            'Rechazos SIRO: 2.',
        ], $resumen->lineasEncabezado());
        $this->assertTrue($resumen->debeMostrarModal());
    }

    public function test_encabezado_incluye_pagos_duplicados(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 3);
        $resumen->agregarRegistroArchivo([
            'linea' => 1,
            'canal' => 'BPD',
            'idFacturaBuscado' => 'A',
            'modalidadIdentificacion' => '—',
            'estado' => 'encontrado',
            'detalle' => null,
        ]);
        $resumen->agregarRegistroArchivo([
            'linea' => 101,
            'canal' => 'BPD',
            'idFacturaBuscado' => 'B',
            'modalidadIdentificacion' => '—',
            'estado' => 'encontrado_duplicado',
            'detalle' => 'PAGO DUPLICADO: Pago repetido: pagado por primera vez en planilla 1151 (SIRO 0110709841).',
        ]);

        $this->assertSame([
            'Registros procesados: 3.',
            'Pagos duplicados (se registran igual): 1.',
        ], $resumen->lineasEncabezado());
    }

    public function test_mensaje_swal_incluye_numero_de_registro(): void
    {
        $resumen = new SiroDescargaRendicionResumen(procesados: 1, omitidos: 1);
        $resumen->agregarAdvertencia('Cupón no encontrado.', 57);

        $this->assertStringContainsString('• Registro 57: Cupón no encontrado.', $resumen->mensajeSwal());
    }
}
