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
            DB::table('permisos_ia')->updateOrInsert(
                ['id' => $permiso['id']],
                $permiso,
            );
        }
    }

    public function down(): void
    {
        // Sin reversión: el catálogo es datos de referencia compartidos entre tenants.
    }
};
