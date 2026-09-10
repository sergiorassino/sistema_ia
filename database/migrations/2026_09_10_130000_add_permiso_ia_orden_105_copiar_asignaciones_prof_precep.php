<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso orden 105 — Copiar asignación de profesores y preceptores entre años lectivos.
 *
 * Solo inserta/actualiza el catálogo en `permisos_ia` (no modifica `profesores`).
 * Equivalente a database/sql/permiso_ia_orden_105_copiar_asignaciones_prof_precep.sql (parte INSERT).
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    private const ORDEN = PermisosIaCatalog::COPIAR_ASIGNACIONES_PROF_PRECEP;

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
            DB::table('permisos_ia')->where('id', 105)->delete();
        }
    }
};
