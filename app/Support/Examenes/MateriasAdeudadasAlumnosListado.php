<?php

namespace App\Support\Examenes;

use App\Support\SchoolContext;
use Illuminate\Support\Facades\DB;

final class MateriasAdeudadasAlumnosListado
{
    public static function esNivelSecundario(SchoolContext $ctx): bool
    {
        return str_contains(mb_strtolower($ctx->nivelNombre()), 'secundari');
    }

    /**
     * Alumnos con matrícula activa en el nivel y ciclo lectivo del contexto (secundario en gestión).
     *
     * @return list<array{
     *     idLegajos: int,
     *     idMatricula: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string
     * }>
     */
    public static function alumnos(int $idNivel, int $idTerlec, ?string $buscar = null): array
    {
        $q = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereNull('m.fechaBaja')
            ->select([
                'm.id as idMatricula',
                'l.id as idLegajos',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ])
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->orderBy('l.id');

        $termino = self::normalizarBusqueda($buscar);
        if ($termino !== '') {
            $like = '%'.$termino.'%';
            $q->where(function ($w) use ($like) {
                $w->where('l.apellido', 'like', $like)
                    ->orWhere('l.nombre', 'like', $like)
                    ->orWhere('l.dni', 'like', $like);
            });
        }

        $out = [];
        foreach ($q->get() as $r) {
            $out[] = [
                'idLegajos' => (int) $r->idLegajos,
                'idMatricula' => (int) $r->idMatricula,
                'apellido' => trim((string) ($r->apellido ?? '')),
                'nombre' => trim((string) ($r->nombre ?? '')),
                'dni' => trim((string) ($r->dni ?? '')),
                'curso' => self::cursoLabelDesdeFila($r),
            ];
        }

        return $out;
    }

    private static function normalizarBusqueda(?string $buscar): string
    {
        $t = trim((string) $buscar);

        return mb_strlen($t) >= 2 ? $t : '';
    }

    private static function cursoLabelDesdeFila(object $r): string
    {
        $sec = trim((string) ($r->cursec ?? ''));
        if ($sec !== '') {
            return $sec;
        }

        $nombrePlan = trim((string) ($r->curPlanCurso ?? ''));
        $extras = collect([$r->turnoClaseNombre ?? '', $r->c ?? '', $r->s ?? ''])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($nombrePlan !== '') {
            return $extras->isNotEmpty()
                ? $nombrePlan.' · '.$extras->implode(' · ')
                : $nombrePlan;
        }

        if ($extras->isNotEmpty()) {
            return $extras->implode(' · ');
        }

        return '';
    }
}
