<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reasigna el orden 14: deja de ser acceso global a Configuración y pasa a
 * «Permisos por Usuario» (grupo ADMINISTRACIÓN, junto al orden 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(
            ['id' => 15],
            [
                'id' => 15,
                'orden' => 14,
                'tema' => 'ADMINISTRACIÓN',
                'descripcion' => 'Consultar permisos concedidos por usuario (módulo Permisos por Usuario).',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(
            ['id' => 15],
            [
                'id' => 15,
                'orden' => 14,
                'tema' => 'CONFIGURACIÓN',
                'descripcion' => '(Legado) Acceso completo al menú Configuración. Preferir los permisos granulares del mismo grupo.',
            ]
        );
    }
};
