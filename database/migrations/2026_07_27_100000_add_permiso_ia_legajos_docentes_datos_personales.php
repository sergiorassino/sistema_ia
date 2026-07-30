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

        $permiso = [
            'id' => 88,
            'orden' => 88,
            'tema' => 'LEGAJOS DOCENTES',
            'descripcion' => 'Ver, imprimir y exportar datos personales completos de docentes (sin este permiso solo apellido, nombre y DNI).',
        ];

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
