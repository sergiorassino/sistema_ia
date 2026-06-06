<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo horarios26: idHora = módulo 1..10; idTurnoClase = jornada (como reloj y cursos).
 * Normaliza filas antiguas con idHora 11–20 / 21–30.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('horarios26')) {
            return;
        }

        if (! Schema::hasColumn('horarios26', 'idTurnoClase')) {
            Schema::table('horarios26', function (Blueprint $table) {
                $table->unsignedTinyInteger('idTurnoClase')->nullable()->after('idHora');
            });
        }

        if (! Schema::hasColumn('horarios26', 'idTurnoClase')) {
            return;
        }

        // Bloque tarde (idHora 11–20) → slot 1–10 + turno 2
        if (Schema::hasTable('turnos_clase')) {
            $idTarde = (int) (DB::table('turnos_clase')->where('codigo', 'tarde')->value('id') ?? 2);
            $idNoche = (int) (DB::table('turnos_clase')->where('codigo', 'noche')->value('id') ?? 3);
            $idManana = (int) (DB::table('turnos_clase')->where('codigo', 'manana')->value('id') ?? 1);
        } else {
            $idManana = 1;
            $idTarde = 2;
            $idNoche = 3;
        }

        DB::table('horarios26')
            ->whereBetween('idHora', [11, 20])
            ->whereNull('idTurnoClase')
            ->update([
                'idTurnoClase' => $idTarde,
                'idHora' => DB::raw('idHora - 10'),
            ]);

        DB::table('horarios26')
            ->whereBetween('idHora', [21, 30])
            ->whereNull('idTurnoClase')
            ->update([
                'idTurnoClase' => $idNoche,
                'idHora' => DB::raw('idHora - 20'),
            ]);

        // idHora 1–10: turno desde curso si hay FK
        if (Schema::hasTable('cursos') && Schema::hasColumn('cursos', 'idTurnoClase')) {
            DB::statement(<<<SQL
                UPDATE horarios26 AS h
                INNER JOIN cursos AS c ON c.Id = h.idCursos
                SET h.idTurnoClase = c.idTurnoClase
                WHERE h.idHora BETWEEN 1 AND 10
                  AND h.idTurnoClase IS NULL
                  AND c.idTurnoClase IS NOT NULL
                  AND c.idTurnoClase > 0
                SQL);
        }

        DB::table('horarios26')
            ->whereBetween('idHora', [1, 10])
            ->whereNull('idTurnoClase')
            ->update(['idTurnoClase' => $idManana]);
    }

    public function down(): void
    {
        if (Schema::hasTable('horarios26') && Schema::hasColumn('horarios26', 'idTurnoClase')) {
            Schema::table('horarios26', function (Blueprint $table) {
                $table->dropColumn('idTurnoClase');
            });
        }
    }
};
