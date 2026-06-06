<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('turnos_clase')) {
            Schema::create('turnos_clase', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->autoIncrement();
                $table->string('codigo', 20);
                $table->string('nombre', 50);
                $table->unsignedTinyInteger('orden')->default(0);
            });

            DB::table('turnos_clase')->insert([
                ['id' => 1, 'codigo' => 'manana', 'nombre' => 'Mañana', 'orden' => 1],
                ['id' => 2, 'codigo' => 'tarde', 'nombre' => 'Tarde', 'orden' => 2],
                ['id' => 3, 'codigo' => 'noche', 'nombre' => 'Noche', 'orden' => 3],
            ]);
        }

        if (! Schema::hasTable('horarios_config')) {
            Schema::create('horarios_config', function (Blueprint $table) {
                $table->unsignedSmallInteger('idNivel')->primary();
                $table->string('turnos_activos', 20)->default('1');
                $table->string('dias_activos', 20)->default('1,2,3,4,5');
            });
        }

        if (Schema::hasTable('reloj') && ! Schema::hasColumn('reloj', 'idTurnoClase')) {
            Schema::table('reloj', function (Blueprint $table) {
                $table->unsignedTinyInteger('idTurnoClase')->nullable()->default(1)->after('idNivel');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reloj') && Schema::hasColumn('reloj', 'idTurnoClase')) {
            Schema::table('reloj', function (Blueprint $table) {
                $table->dropColumn('idTurnoClase');
            });
        }

        Schema::dropIfExists('horarios_config');
        Schema::dropIfExists('turnos_clase');
    }
};
