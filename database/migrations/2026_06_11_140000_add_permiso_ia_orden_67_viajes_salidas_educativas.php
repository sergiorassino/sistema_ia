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

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if ((int) $permiso['orden'] !== PermisosIaCatalog::VIAJES_SALIDAS_EDUCATIVAS) {
                continue;
            }

            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 67)->delete();
        }
    }
};
