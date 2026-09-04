<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aclara en permisos_ia que el orden 71 (CALIF_CARGA) cubre solo
 * la carga manual; informes y planillas de visualización no lo requieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $permiso = collect(PermisosIaCatalog::definicionCatalogo())
            ->firstWhere('orden', PermisosIaCatalog::CALIF_CARGA);

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
            ->where('id', 71)
            ->where('orden', PermisosIaCatalog::CALIF_CARGA)
            ->update([
                'descripcion' => 'Carga manual de calificaciones e indicadores (inicial, primario y secundario).',
            ]);
    }
};
