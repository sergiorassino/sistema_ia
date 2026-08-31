<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso «NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR» en órdenes 25, 26, 33–36 y 100.
 *
 * Solo actualiza el catálogo en `permisos_ia` (no modifica `profesores`).
 * Equivalente: database/sql/permisos_ia_aviso_no_otorgar_admin.sql
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $ordenes = PermisosIaCatalog::ordenesReservadosAdministrador();
        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if (! in_array((int) $permiso['orden'], $ordenes, true)) {
                continue;
            }
            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $aviso = PermisosIaCatalog::AVISO_NO_OTORGAR_ADMIN;
        foreach (PermisosIaCatalog::ordenesReservadosAdministrador() as $orden) {
            $fila = DB::table('permisos_ia')->where('orden', $orden)->first(['descripcion']);
            if ($fila === null) {
                continue;
            }
            $desc = trim(str_replace($aviso, '', (string) $fila->descripcion), " \t.");
            if ($desc !== '') {
                $desc .= '.';
            }
            DB::table('permisos_ia')->where('orden', $orden)->update(['descripcion' => $desc]);
        }
    }
};
