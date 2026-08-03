<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso orden 9 — descripción: importar CIDI/GE también en nivel inicial.
 *
 * Solo actualiza el catálogo en `permisos_ia` (no modifica `profesores`).
 * Equivalente a database/sql/permiso_ia_orden_9_descripcion_incluye_inicial.sql
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    private const ORDEN = PermisosIaCatalog::CALIF_SINCRO_CIDI;

    private const DESCRIPCION_ANTERIOR = 'Importar calificaciones desde CSV CIDI/GE (primario y secundario).';

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

        DB::table('permisos_ia')->updateOrInsert(
            ['id' => $permiso['id']],
            $permiso,
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')
            ->where('orden', self::ORDEN)
            ->update(['descripcion' => self::DESCRIPCION_ANTERIOR]);
    }
};
