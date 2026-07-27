<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $permiso = collect(PermisosIaCatalog::definicionCatalogo())
            ->firstWhere('orden', PermisosIaCatalog::LEGAJOS_DOCENTES_DATOS_PERSONALES);

        if ($permiso === null) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);

        $legajosDocentes = collect(PermisosIaCatalog::definicionCatalogo())
            ->firstWhere('orden', PermisosIaCatalog::LEGAJOS_DOCENTES);

        if ($legajosDocentes !== null) {
            DB::table('permisos_ia')->updateOrInsert(['id' => $legajosDocentes['id']], $legajosDocentes);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 88)->delete();
        }
    }
};
