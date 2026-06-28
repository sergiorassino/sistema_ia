<?php

namespace App\Support\CalificacionesPrimario\Epq;

use App\Models\Ento;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioCatalogo;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use Illuminate\Support\Facades\DB;

/**
 * Datos para el Boletín (Prim) EPQ — anverso y reverso.
 */
final class BoletinPrimEpqDatos
{
    /**
     * @return array{ok: bool, error?: string, data?: array<string, mixed>}
     */
    public static function buildForMatriculaEnContexto(int $idMatricula): array
    {
        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($idMatricula);
        if ($mat === null) {
            return ['ok' => false, 'error' => 'Matrícula no encontrada en el contexto activo.'];
        }

        return ['ok' => true, 'data' => self::buildDesdeMatricula($mat)];
    }

    /**
     * @return array{ok: bool, error?: string, data?: array<string, mixed>}
     */
    public static function buildDatosParaAlumno(): array
    {
        $mat = CalificacionesPrimarioDatos::matriculaAlumnoEnSesion();
        if ($mat === null) {
            return ['ok' => false, 'error' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.'];
        }

        return ['ok' => true, 'data' => self::buildDesdeMatricula($mat)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDesdeMatricula(Matricula $matricula): array
    {
        $matricula->loadMissing(['legajo', 'curso']);
        $idMatricula = (int) $matricula->id;
        $idNivel = (int) $matricula->idNivel;

        $materias = CalificacionesPrimarioCatalogo::materiasParaSelectorAnio(
            (int) $matricula->idCursos,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
        )->sortBy('ord')->values();

        $filasCalif = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->get(array_merge(['idMaterias', 'ord'], CalificacionesEpqCatalogo::CAMPOS_NOTA));

        $porMateria = [];
        foreach ($filasCalif as $r) {
            $idMat = (int) ($r->idMaterias ?? 0);
            if ($idMat > 0) {
                $porMateria[$idMat] = $r;
            }
        }

        $calificaciones = [];
        foreach ($materias as $m) {
            $idMaterias = (int) $m->id;
            $fila = $porMateria[$idMaterias] ?? null;
            $item = ['materia' => (string) $m->materia];
            foreach (CalificacionesEpqCatalogo::CAMPOS_NOTA as $campo) {
                $item[$campo] = (string) ($fila?->$campo ?? '');
            }
            $calificaciones[] = $item;
        }

        $camposInfo = CalificacionesEpqCatalogo::camposInfoAdicional();
        $infoRow = DB::table('matricula')->where('id', $idMatricula)->first($camposInfo);
        $info = [];
        foreach ($camposInfo as $campo) {
            $info[$campo] = (string) ($infoRow?->$campo ?? '');
        }

        $terlec = Terlec::query()->find((int) $matricula->idTerlec, [
            'habiles1t', 'habiles2t', 'habiles3t', 'habilesTot', 'ano',
        ]);

        $legajo = $matricula->legajo;
        $curso = $matricula->curso;
        $cursec = trim((string) ($curso?->cursec ?? ''));
        $nombreCurso = $cursec !== '' ? mb_substr($cursec, 0, -1) : trim((string) ($curso?->c ?? ''));
        $seccion = $cursec !== '' ? mb_substr($cursec, -1) : trim((string) ($curso?->s ?? ''));

        $fechnaci = $legajo?->fechnaci;
        $fechnaciFmt = $fechnaci instanceof \DateTimeInterface
            ? $fechnaci->format('d/m/Y')
            : (is_string($fechnaci) && trim($fechnaci) !== '' ? date('d/m/Y', strtotime($fechnaci)) : '');

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['insti', 'direccion', 'localidad', 'provincia', 'logo_path']);

        return [
            'idMatricula' => $idMatricula,
            'apellido' => trim((string) ($legajo?->apellido ?? '')),
            'nombre' => trim((string) ($legajo?->nombre ?? '')),
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'legajo' => trim((string) ($legajo?->legajo ?? '')),
            'nombretut' => trim((string) ($legajo?->nombretut ?? '')),
            'fechnaci' => $fechnaciFmt,
            'nombreCurso' => $nombreCurso,
            'seccion' => $seccion,
            'turno' => trim((string) ($curso?->turno ?? '')),
            'anoLectivo' => (string) ($terlec?->ano ?? ''),
            'habiles1t' => (string) ($terlec?->habiles1t ?? ''),
            'habiles2t' => (string) ($terlec?->habiles2t ?? ''),
            'habiles3t' => (string) ($terlec?->habiles3t ?? ''),
            'habilesTot' => (string) ($terlec?->habilesTot ?? ''),
            'calificaciones' => $calificaciones,
            'info' => $info,
            'insti' => trim((string) ($ento?->insti ?? '')),
            'direccion' => trim((string) ($ento?->direccion ?? '')),
            'localidad' => trim((string) ($ento?->localidad ?? '')),
            'provincia' => trim((string) ($ento?->provincia ?? '')),
            'membrete_portada_file' => tenantBoletinPrimEpqMembretePortadaAbsoluta(),
        ];
    }
}
