<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aclara en permisos_ia que el orden 13 (HORARIOS) cubre solo
 * Configuración y Carga; Impresión de horarios no requiere ese permiso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $permiso = collect(PermisosIaCatalog::definicionCatalogo())
            ->firstWhere('orden', PermisosIaCatalog::HORARIOS);

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
            ->where('id', 14)
            ->where('orden', PermisosIaCatalog::HORARIOS)
            ->update([
                'descripcion' => 'Configuración de horarios (turnos, días, reloj) y carga de horas cátedra por docente.',
            ]);
    }
};
