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
            [25, 26, 33, 34, 35, 36, 104, 105, 106, 100],
            PermisosIaCatalog::ordenesReservadosAdministrador(),
        );
        $this->assertSame(25, PermisosConfiguracion::TERLEC);
        $this->assertSame(104, PermisosIaCatalog::COPIAR_CURSOS_MATERIAS_ANIO);
        $this->assertSame(105, PermisosIaCatalog::COPIAR_ASIGNACIONES_PROF_PRECEP);
        $this->assertSame(106, PermisosIaCatalog::PROMOVER_ALUMNOS_ANIO);
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

    public function test_copiar_cursos_materias_esta_en_configuracion_reservada(): void
    {
        $this->assertSame(104, PermisosIaCatalog::COPIAR_CURSOS_MATERIAS_ANIO);
        $this->assertSame(104, PermisosConfiguracion::COPIAR_CURSOS_MATERIAS_ANIO);
        $this->assertTrue(PermisosIaCatalog::esReservadoAdministrador(104));

        $fila = null;
        foreach (PermisosIaCatalog::definicionCatalogo() as $row) {
            if ((int) $row['orden'] === PermisosIaCatalog::COPIAR_CURSOS_MATERIAS_ANIO) {
                $fila = $row;
                break;
            }
        }

        $this->assertNotNull($fila);
        $this->assertSame(104, $fila['id']);
        $this->assertSame('CONFIGURACIÓN', $fila['tema']);
        $this->assertStringContainsString(PermisosIaCatalog::AVISO_NO_OTORGAR_ADMIN, $fila['descripcion']);
    }

    public function test_copiar_asignaciones_prof_precep_esta_en_configuracion_reservada(): void
    {
        $this->assertSame(105, PermisosIaCatalog::COPIAR_ASIGNACIONES_PROF_PRECEP);
        $this->assertSame(105, PermisosConfiguracion::COPIAR_ASIGNACIONES_PROF_PRECEP);
        $this->assertTrue(PermisosIaCatalog::esReservadoAdministrador(105));

        $fila = null;
        foreach (PermisosIaCatalog::definicionCatalogo() as $row) {
            if ((int) $row['orden'] === PermisosIaCatalog::COPIAR_ASIGNACIONES_PROF_PRECEP) {
                $fila = $row;
                break;
            }
        }

        $this->assertNotNull($fila);
        $this->assertSame(105, $fila['id']);
        $this->assertSame('CONFIGURACIÓN', $fila['tema']);
        $this->assertStringContainsString(PermisosIaCatalog::AVISO_NO_OTORGAR_ADMIN, $fila['descripcion']);
    }

    public function test_promover_alumnos_esta_en_configuracion_reservada(): void
    {
        $this->assertSame(106, PermisosIaCatalog::PROMOVER_ALUMNOS_ANIO);
        $this->assertSame(106, PermisosConfiguracion::PROMOVER_ALUMNOS_ANIO);
        $this->assertTrue(PermisosIaCatalog::esReservadoAdministrador(106));

        $fila = null;
        foreach (PermisosIaCatalog::definicionCatalogo() as $row) {
            if ((int) $row['orden'] === PermisosIaCatalog::PROMOVER_ALUMNOS_ANIO) {
                $fila = $row;
                break;
            }
        }

        $this->assertNotNull($fila);
        $this->assertSame(106, $fila['id']);
        $this->assertSame('CONFIGURACIÓN', $fila['tema']);
        $this->assertStringContainsString(PermisosIaCatalog::AVISO_NO_OTORGAR_ADMIN, $fila['descripcion']);
    }

    public function test_listado_familias_esta_en_legajos_estudiantes(): void
    {
        $this->assertSame(102, PermisosIaCatalog::LISTADO_FAMILIAS);

        $fila = null;
        foreach (PermisosIaCatalog::definicionCatalogo() as $row) {
            if ((int) $row['orden'] === PermisosIaCatalog::LISTADO_FAMILIAS) {
                $fila = $row;
                break;
            }
        }

        $this->assertNotNull($fila);
        $this->assertSame(102, $fila['id']);
        $this->assertSame('LEGAJOS ESTUDIANTES', $fila['tema']);
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
