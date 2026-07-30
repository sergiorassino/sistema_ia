<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el permiso IA orden 88 (datos personales docentes).
 * Ese alcance queda unificado en el orden 11 (LEGAJOS_DOCENTES).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->where('id', 88)->delete();
        DB::table('permisos_ia')->where('orden', 88)->delete();

        $legajosDocentes = collect(PermisosIaCatalog::definicionCatalogo())
            ->firstWhere('orden', PermisosIaCatalog::LEGAJOS_DOCENTES);

        if ($legajosDocentes !== null) {
            DB::table('permisos_ia')->updateOrInsert(['id' => $legajosDocentes['id']], $legajosDocentes);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(
            ['id' => 88],
            [
                'id' => 88,
                'orden' => 88,
                'tema' => 'LEGAJOS DOCENTES',
                'descripcion' => 'Ver, imprimir y exportar datos personales completos de docentes (sin este permiso solo apellido, nombre y DNI).',
            ]
        );
    }
};
