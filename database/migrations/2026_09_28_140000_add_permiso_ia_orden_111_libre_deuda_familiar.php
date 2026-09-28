<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso orden 111 — Libre deuda familiar (constancia PDF, cuotas internas).
 *
 * Solo inserta/actualiza el catálogo en `permisos_ia` (no modifica `profesores`).
 * Equivalente a database/sql/permiso_ia_orden_111_libre_deuda_familiar.sql (parte INSERT).
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    private const ORDEN = PermisosIaCatalog::ADMIN_MORA_LIBRE_DEUDA_FAMILIAR;

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
            DB::table('permisos_ia')->where('id', 111)->delete();
        }
    }
};
