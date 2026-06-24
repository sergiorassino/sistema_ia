<?php

namespace App\Support\Migrations;

use App\Support\HorariosProfesores;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asegura cursos.turno (texto legacy) e idTurnoClase (FK lógica a turnos_clase).
 * turno: se crea si falta; nunca se elimina si ya existe.
 */
final class EnsureCursosTurnoColumns
{
    public static function aplicar(): void
    {
        if (! Schema::hasTable('cursos')) {
            return;
        }

        self::asegurarColumnaTurno();
        self::asegurarColumnaIdTurnoClase();
        self::poblarIdTurnoClaseDesdeTurno();
        self::poblarTurnoDesdeIdTurnoClase();
    }

    private static function asegurarColumnaTurno(): void
    {
        if (Schema::hasColumn('cursos', 'turno')) {
            return;
        }

        Schema::table('cursos', function (Blueprint $table) {
            $table->string('turno', 20)->nullable()->after('s');
        });
    }

    private static function asegurarColumnaIdTurnoClase(): void
    {
        if (Schema::hasColumn('cursos', 'idTurnoClase')) {
            return;
        }

        Schema::table('cursos', function (Blueprint $table) {
            $table->unsignedTinyInteger('idTurnoClase')->nullable()->after('turno');
        });
    }

    private static function poblarIdTurnoClaseDesdeTurno(): void
    {
        if (! Schema::hasColumn('cursos', 'turno') || ! Schema::hasColumn('cursos', 'idTurnoClase')) {
            return;
        }

        foreach (DB::table('cursos')->whereNull('idTurnoClase')->cursor() as $row) {
            $text = trim((string) ($row->turno ?? ''));
            if ($text === '') {
                continue;
            }
            $id = HorariosProfesores::inferirTurnoClaseDesdeCurso($text);
            if ($id > 0) {
                DB::table('cursos')->where('Id', $row->Id)->update(['idTurnoClase' => $id]);
            }
        }
    }

    private static function poblarTurnoDesdeIdTurnoClase(): void
    {
        if (! Schema::hasTable('turnos_clase')
            || ! Schema::hasColumn('cursos', 'turno')
            || ! Schema::hasColumn('cursos', 'idTurnoClase')) {
            return;
        }

        $nombresPorId = DB::table('turnos_clase')->pluck('nombre', 'id');

        $query = DB::table('cursos')
            ->whereNotNull('idTurnoClase')
            ->where('idTurnoClase', '>', 0)
            ->where(function ($q) {
                $q->whereNull('turno')->orWhere('turno', '');
            });

        foreach ($query->cursor() as $row) {
            $nombre = trim((string) ($nombresPorId[$row->idTurnoClase] ?? ''));
            if ($nombre !== '') {
                DB::table('cursos')->where('Id', $row->Id)->update(['turno' => $nombre]);
            }
        }
    }
}
