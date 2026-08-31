<?php

namespace Tests\Unit;

use App\Support\PermisosConfiguracion;
use App\Support\PermisosIaCatalog;
use PHPUnit\Framework\TestCase;

class PermisosIaReservadosAdminTest extends TestCase
{
    public function test_ordenes_reservados_son_los_pactados(): void
    {
        $this->assertSame(
            [25, 26, 33, 34, 35, 36, 100],
            PermisosIaCatalog::ordenesReservadosAdministrador(),
        );
        $this->assertSame(25, PermisosConfiguracion::TERLEC);
        $this->assertSame(100, PermisosIaCatalog::CALIF_CIERRE_ANUAL_LOTES);
    }

    public function test_catalogo_incluye_aviso_en_reservados(): void
    {
        $aviso = PermisosIaCatalog::AVISO_NO_OTORGAR_ADMIN;
        $porOrden = [];
        foreach (PermisosIaCatalog::definicionCatalogo() as $row) {
            $porOrden[(int) $row['orden']] = (string) $row['descripcion'];
        }

        foreach (PermisosIaCatalog::ordenesReservadosAdministrador() as $orden) {
            $this->assertArrayHasKey($orden, $porOrden);
            $this->assertStringContainsString($aviso, $porOrden[$orden]);
        }

        $this->assertStringNotContainsString($aviso, $porOrden[15] ?? '');
    }

    public function test_descripcion_con_aviso_no_duplica(): void
    {
        $una = PermisosIaCatalog::descripcionConAvisoAdmin('Términos lectivos.');
        $this->assertSame(
            'Términos lectivos. '.PermisosIaCatalog::AVISO_NO_OTORGAR_ADMIN,
            $una,
        );
        $this->assertSame($una, PermisosIaCatalog::descripcionConAvisoAdmin($una));
    }
}
