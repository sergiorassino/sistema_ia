<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la solapa slug «otros» si no existe: en el PDF por curso, ese bloque lista solo
 * los campos de `campos_legajo` asignados a esa solapa (mismo criterio que el resto de solapas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solapas_legajo')) {
            return;
        }

        if (DB::table('solapas_legajo')->where('slug', 'otros')->exists()) {
            return;
        }

        $maxOrden = (int) DB::table('solapas_legajo')->max('orden');

        DB::table('solapas_legajo')->insert([
            'nombre' => 'Otros',
            'slug' => 'otros',
            'orden' => $maxOrden + 1,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('solapas_legajo')) {
            return;
        }

        DB::table('solapas_legajo')->where('slug', 'otros')->delete();
    }
};
