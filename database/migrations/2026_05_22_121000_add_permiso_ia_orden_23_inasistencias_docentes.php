<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso de uso: orden 23 (posición en profesores.permisos_ia).
 * Catálogo: id 25 (el id 23 ya está ocupado por Pase parcial, orden 21).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        // Restaurar catálogo de Pase parcial si una versión anterior de esta migración lo pisó (mismo id 23).
        DB::table('permisos_ia')->updateOrInsert(
            ['id' => 23],
            [
                'id' => 23,
                'orden' => 21,
                'tema' => 'CERTIFICADOS',
                'descripcion' => 'Pase parcial: listado de legajos de nivel medio, solicitud y emisión de PDF.',
            ]
        );

        DB::table('permisos_ia')->updateOrInsert(
            ['id' => 25],
            [
                'id' => 25,
                'orden' => 23,
                'tema' => 'INASISTENCIAS DOCENTES',
                'descripcion' => 'Gestión de inasistencias docentes: cargos, registros, informes por bimestre y PDF.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 25)->delete();
        }
    }
};
