<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campos_legajo')) {
            DB::table('campos_legajo')->whereIn('columna', ['apellido', 'nombre', 'dni'])->delete();
        }
    }

    public function down(): void
    {
        // No se restauran filas: apellido/nombre/dni no deben parametrizarse.
    }
};
