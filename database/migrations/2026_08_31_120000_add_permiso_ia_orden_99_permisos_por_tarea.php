<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso orden 99 — Permisos por Tarea.
 *
 * Solo inserta/actualiza el catálogo en `permisos_ia` (no modifica `profesores`).
 * Equivalente a database/sql/permiso_ia_orden_99_permisos_por_tarea.sql (parte INSERT).
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    private const ORDEN = PermisosIaCatalog::PERMISOS_POR_TAREA;

    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $permiso = collect(PermisosIaCatalog::definicionCatalogo())
            ->firstWhere('orden', self::ORDEN);

        if ($permiso === null) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 99)->delete();
        }
    }
};
