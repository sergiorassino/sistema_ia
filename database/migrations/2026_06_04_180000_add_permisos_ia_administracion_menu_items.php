<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORDENES_NUEVOS = [
        PermisosIaCatalog::ADMIN_ARANCELES_ESTUDIANTE,
        PermisosIaCatalog::ADMIN_CUOTAS_PLANTILLAS,
        PermisosIaCatalog::ADMIN_CUOTAS_IMPORTES_CURSO,
        PermisosIaCatalog::ADMIN_CUOTAS_GENERACION_MASIVA,
        PermisosIaCatalog::ADMIN_CUOTAS_ELIMINACION_MASIVA,
        PermisosIaCatalog::ADMIN_CUOTAS_EDICION_GENERADAS,
        PermisosIaCatalog::ADMIN_CUOTAS_CANCELAR_RESERVAS,
        PermisosIaCatalog::ADMIN_LIBRO_ARANCELES,
        PermisosIaCatalog::ADMIN_LISTADO_PAGOS_FECHA,
        PermisosIaCatalog::ADMIN_LISTADO_ESTUDIANTES_CUOTA,
        PermisosIaCatalog::ADMIN_BECAS_TIPOS,
        PermisosIaCatalog::ADMIN_BECAS_ASIGNACION,
        PermisosIaCatalog::ADMIN_BECAS_RESUMEN_NIVEL,
        PermisosIaCatalog::ADMIN_BECAS_SOLICITUD_AYUDA,
        PermisosIaCatalog::ADMIN_MORA_ESTADO_DEUDA,
        PermisosIaCatalog::ADMIN_MORA_GESTION_MOROSOS,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if (! in_array((int) $permiso['orden'], self::ORDENES_NUEVOS, true)) {
                continue;
            }

            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->whereIn('id', range(49, 64))->delete();
        }
    }
};
