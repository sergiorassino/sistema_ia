<?php

namespace App\Support\CalificacionesSecundario\Epq;

use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use App\Support\ConsultaCalificacionesAlumno;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta;
use Illuminate\Support\Facades\DB;

/**
 * Datos para el informe de calificaciones EPQ secundario (layout legacy ScriptCase).
 */
final class BoletinEpqSecundarioDatos
{
    /**
     * @return array{ok: bool, error?: string, data?: array<string, mixed>}
     */
    public static function buildForMatriculaEnContexto(int $idMatricula): array
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return ['ok' => false, 'error' => 'Sesión inválida.'];
        }

        if ($idMatricula <= 0) {
            return ['ok' => false, 'error' => 'Solicitud inválida.'];
        }

        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );

        /** @var Matricula|null $matricula */
        $matricula = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereIn('idCondiciones', $idsCondicionesRegulares)
            ->first();

        if (! $matricula) {
            return ['ok' => false, 'error' => 'Matrícula no encontrada en el contexto activo.'];
        }

        return ['ok' => true, 'data' => self::buildDesdeMatricula($matricula)];
    }

    /**
     * @return array{ok: bool, error?: string, data?: array<string, mixed>}
     */
    public static function buildDatosParaAlumno(): array
    {
        $matricula = CalificacionesPrimarioDatos::matriculaAlumnoEnSesion();
        if ($matricula === null) {
            return ['ok' => false, 'error' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.'];
        }

        return ['ok' => true, 'data' => self::buildDesdeMatricula($matricula, true)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDesdeMatricula(Matricula $matricula, bool $contextoAlumno = false): array
    {
        $matricula->loadMissing(['legajo', 'curso']);
        $idMatricula = (int) $matricula->id;
        $idTerlec = (int) $matricula->idTerlec;
        $idCurso = (int) $matricula->idCursos;
        $idNivel = (int) $matricula->idNivel;

        $campos = CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA;

        $filasCalif = DB::table('calificaciones as c')
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->where('c.idMatricula', $idMatricula)
            ->where('c.idTerlec', $idTerlec)
            ->orderByRaw('COALESCE(m.ord, 9999) asc')
            ->orderBy('m.materia')
            ->get(array_merge(['m.materia'], array_map(fn (string $c) => 'c.'.$c, $campos)));

        $calificaciones = [];
        foreach ($filasCalif as $fila) {
            $item = ['materia' => trim((string) ($fila->materia ?? ''))];
            foreach ($campos as $campo) {
                $item[$campo] = self::valorNota($fila->{$campo} ?? null);
            }
            $calificaciones[] = $item;
        }

        $terlec = Terlec::query()->find($idTerlec, ['ano']);
        $header = $contextoAlumno ? studentPdfHeaderData() : schoolPdfHeaderData();

        $legajo = $matricula->legajo;
        $curso = $matricula->curso;

        return [
            'idMatricula' => $idMatricula,
            'apellido' => trim((string) ($legajo?->apellido ?? '')),
            'nombre' => trim((string) ($legajo?->nombre ?? '')),
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'cursec' => trim((string) ($curso?->cursec ?? $curso?->nombreParaListado() ?? '')),
            'anoLectivo' => (string) ($terlec?->ano ?? ''),
            'insti' => trim((string) ($header['insti'] ?? '')),
            'subtituloInstitucion' => tenantBoletinEpqSecundarioSubtituloInstitucion(),
            'lineaContacto' => self::lineaContactoInstitucional($header),
            'membrete_file' => tenantBoletinEpqSecundarioMembreteAbsoluta() ?? ($header['logo_file'] ?? null),
            'calificaciones' => $calificaciones,
            'proximas_evaluaciones' => self::proximasEvaluacionesTexto($idCurso, $idNivel, $idTerlec),
            'items_boletin' => ConsultaCalificacionesAlumno::itemsBoletinParaMatriculaPublic($idMatricula, $idTerlec),
        ];
    }

    /**
     * @param  array{insti?: string, direccion?: string, localidad?: string}  $header
     */
    private static function lineaContactoInstitucional(array $header): string
    {
        $partes = array_filter([
            trim((string) ($header['direccion'] ?? '')),
            trim((string) ($header['localidad'] ?? '')),
        ], fn (string $v) => $v !== '');

        return implode(' - ', $partes);
    }

    /**
     * @return list<string>
     */
    private static function proximasEvaluacionesTexto(int $idCurso, int $idNivel, int $idTerlec): array
    {
        $filas = SolicitudEvaluacionConsulta::proximasEvaluacionesParaCursoMatricula($idCurso, $idNivel, $idTerlec);
        $lineas = [];
        foreach ($filas as $fila) {
            $linea = trim((string) ($fila->linea ?? ''));
            if ($linea !== '') {
                $lineas[] = $linea;
            }
        }

        return $lineas;
    }

    private static function valorNota(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $s = trim((string) $valor);

        return $s === '0' ? '' : $s;
    }
}
