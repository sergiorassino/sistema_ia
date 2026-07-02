<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla legacy `familias` (grupos familiares de estudiantes).
 * Equivalente a database/sql/familias_tabla_idempotente.sql — se aplica con se:migrate-legacy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('familias')) {
            Schema::create('familias', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('apellido', 50)->default('');
                $table->string('responsable', 100)->default('');
                $table->string('email', 150)->default('');
            });
        }

        if (! Schema::hasColumn('familias', 'email')) {
            Schema::table('familias', function (Blueprint $table) {
                $table->string('email', 150)->default('')->after('responsable');
            });
        }

        if (! DB::table('familias')->where('id', 1)->exists()) {
            DB::table('familias')->insert([
                'id' => 1,
                'apellido' => ' Sin Registro de Familia',
                'responsable' => '',
                'email' => '',
            ]);
        }
    }

    public function down(): void
    {
        // No eliminar tabla ni fila placeholder (datos legacy / legajos.idFamilias).
    }
};
