<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige instalaciones que agregaron reloj.idTurno (confundido con turnos de examen).
 * Los turnos de clase viven en turnos_clase; reloj usa idTurnoClase.
 */
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

        if (! Schema::hasTable('reloj')) {
            return;
        }

        $tieneIdTurno = Schema::hasColumn('reloj', 'idTurno');
        $tieneIdTurnoClase = Schema::hasColumn('reloj', 'idTurnoClase');

        if ($tieneIdTurno && ! $tieneIdTurnoClase) {
            Schema::table('reloj', function (Blueprint $table) {
                $table->unsignedTinyInteger('idTurnoClase')->nullable()->default(1)->after('idNivel');
            });

            DB::table('reloj')->whereNotNull('idTurno')->update([
                'idTurnoClase' => DB::raw('idTurno'),
            ]);

            Schema::table('reloj', function (Blueprint $table) {
                $table->dropColumn('idTurno');
            });
        } elseif (! $tieneIdTurnoClase) {
            Schema::table('reloj', function (Blueprint $table) {
                $table->unsignedTinyInteger('idTurnoClase')->nullable()->default(1)->after('idNivel');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reloj')) {
            return;
        }

        if (Schema::hasColumn('reloj', 'idTurnoClase') && ! Schema::hasColumn('reloj', 'idTurno')) {
            Schema::table('reloj', function (Blueprint $table) {
                $table->unsignedTinyInteger('idTurno')->nullable()->default(1)->after('idNivel');
            });

            DB::table('reloj')->whereNotNull('idTurnoClase')->update([
                'idTurno' => DB::raw('idTurnoClase'),
            ]);

            Schema::table('reloj', function (Blueprint $table) {
                $table->dropColumn('idTurnoClase');
            });
        }
    }
};
