<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza el permiso legado «PARAMETRIZACIÓN» (orden 1) por «Toma de asistencia a clase».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if ((int) $permiso['orden'] !== PermisosIaCatalog::TOMA_ASISTENCIA_CLASE) {
                continue;
            }

            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);

            break;
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(
            ['id' => 2],
            [
                'id' => 2,
                'orden' => 1,
                'tema' => 'PARAMETRIZACIÓN',
                'descripcion' => 'Términos lectivos, niveles, cursos, planes, materias del año, legajos de docentes y parametrización relacionada.',
            ]
        );
    }
};
