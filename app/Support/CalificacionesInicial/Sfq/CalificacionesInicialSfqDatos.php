<?php

namespace App\Support\CalificacionesInicial\Sfq;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lectura y persistencia — calificaciones inicial SFQ (ic01–ic06, obs, baObs).
 */
final class CalificacionesInicialSfqDatos
{
    public static function abortSiEsquemaIncompleto(): void
    {
        abort_unless(
            Schema::hasTable('calificaciones')
                && Schema::hasTable('indicadores')
                && Schema::hasTable('edani')
                && Schema::hasColumn('calificaciones', 'ic01')
                && Schema::hasColumn('calificaciones', 'obs01'),
            503,
            'La base de datos no tiene las tablas o columnas necesarias para la carga de calificaciones inicial SFQ.'
        );
    }

    public static function abortSiObservacionesBaInexistentes(): void
    {
        foreach (CalificacionesInicialSfqCatalogo::CAMPOS_OBS_BA as $col) {
            abort_unless(
                Schema::hasColumn('calificaciones', $col),
                503,
                "Falta la columna calificaciones.{$col} necesaria para observaciones de Bellas Artes."
            );
        }
    }

    public static function matriculaEnContexto(int $idMatricula): ?Matricula
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        return Matricula::query()
            ->with('legajo')
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereNull('fechaBaja')
            ->first();
    }

    public static function cursoEnContexto(int $idCurso): ?Curso
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('Id', $idCurso)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->first(['Id', 'cursec', 'c', 's', 'orden', 'idTurnoClase']);
    }

    /**
     * Materia ord = 1 del curso (registro único de calificaciones por alumno en SFQ).
     *
     * @return object{id: int, ord: int}|null
     */
    public static function materiaPrincipalCurso(int $idCurso): ?object
    {
        $ctx = schoolCtx();

        $row = DB::table('materias')
            ->where('idCursos', $idCurso)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->orderBy('ord')
            ->orderBy('id')
            ->first(['id', 'ord']);

        return $row !== null
            ? (object) ['id' => (int) $row->id, 'ord' => (int) $row->ord]
            : null;
    }

    /**
     * @return Collection<int, Matricula>
     */
    public static function matriculasRegularesCurso(int $idCurso): Collection
    {
        $ctx = schoolCtx();
        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );

        return Matricula::query()
            ->with('legajo')
            ->join('legajos as l', 'l.id', '=', 'matricula.idLegajos')
            ->where('matricula.idCursos', $idCurso)
            ->where('matricula.idNivel', (int) $ctx->idNivel)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->whereIn('matricula.idCondiciones', $idsCondiciones)
            ->whereNull('matricula.fechaBaja')
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->select('matricula.*')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function filaCalificaciones(int $idMatricula, int $ord): ?object
    {
        return DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ord)
            ->first();
    }

    /**
     * Indica si el campo ic tiene al menos un dígito válido cargado.
     */
    public static function icTieneDatos(?string $ic): bool
    {
        if ($ic === null || $ic === '') {
            return false;
        }

        return preg_match('/[123]/', $ic) === 1;
    }

    public static function observacionesTienenDatos(?object $fila): bool
    {
        if ($fila === null) {
            return false;
        }

        foreach (array_merge(
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_PEDAG,
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_BA,
        ) as $col) {
            if (! Schema::hasColumn('calificaciones', $col)) {
                continue;
            }
            if (trim((string) ($fila->{$col} ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{
     *     id: int,
     *     idEdani: int,
     *     edani: string,
     *     indicador: string,
     *     nota: string
     * }>
     */
    public static function indicadoresParaCarga(int $sala, int $etapa, string $area): array
    {
        self::abortSiEsquemaIncompleto();

        $query = DB::table('indicadores as i')
            ->join('edani as e', 'e.id', '=', 'i.idEdani')
            ->where('i.sala', $sala)
            ->where('i.etapa', $etapa)
            ->orderBy('i.id');

        self::aplicarFiltroAreaIndicadores($query, $area);

        $filas = $query->get([
            'i.id',
            'i.idEdani',
            'e.edani',
            'i.indicador',
        ]);

        return $filas->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'idEdani' => (int) $r->idEdani,
                'edani' => trim((string) ($r->edani ?? '')),
                'indicador' => trim((string) ($r->indicador ?? '')),
                'nota' => '',
            ];
        })->all();
    }

    /**
     * Indicadores por sala y etapa (sin filtro de área — informe Bellas Artes legacy etapas 11–13).
     *
     * @return list<array{
     *     id: int,
     *     idEdani: int,
     *     edani: string,
     *     indicador: string,
     *     nota: string
     * }>
     */
    public static function indicadoresPorSalaEtapa(int $sala, int $etapa): array
    {
        self::abortSiEsquemaIncompleto();

        $filas = DB::table('indicadores as i')
            ->join('edani as e', 'e.id', '=', 'i.idEdani')
            ->where('i.sala', $sala)
            ->where('i.etapa', $etapa)
            ->orderBy('i.id')
            ->get([
                'i.id',
                'i.idEdani',
                'e.edani',
                'i.indicador',
            ]);

        return $filas->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'idEdani' => (int) $r->idEdani,
                'edani' => trim((string) ($r->edani ?? '')),
                'indicador' => trim((string) ($r->indicador ?? '')),
                'nota' => '',
            ];
        })->all();
    }

    /**
     * @param  list<array{id: int, idEdani: int, edani: string, indicador: string, nota: string}>  $filas
     * @return list<array{id: int, idEdani: int, edani: string, indicador: string, nota: string}>
     */
    public static function fusionarNotasEnIndicadores(array $filas, ?string $ic): array
    {
        $ic = (string) ($ic ?? '');

        foreach ($filas as $i => $fila) {
            $digito = strlen($ic) > $i ? substr($ic, $i, 1) : '';
            if (! in_array($digito, ['1', '2', '3'], true)) {
                $digito = '';
            }
            $filas[$i]['nota'] = $digito;
        }

        return $filas;
    }

    /**
     * @param  list<array{nota: string}>  $filas
     */
    public static function filasAIC(array $filas): string
    {
        $out = '';
        foreach ($filas as $fila) {
            $d = (string) ($fila['nota'] ?? '');
            $out .= in_array($d, ['1', '2', '3'], true) ? $d : '0';
        }

        return $out;
    }

    /**
     * @return array{
     *     alumnoLinea: string,
     *     cursoLabel: string,
     *     sala: int|string,
     *     etapa: int,
     *     area: string,
     *     campoIc: string,
     *     filas: list<array{id: int, idEdani: int, edani: string, indicador: string, nota: string}>
     * }
     */
    public static function cargarFormularioIndicadores(Matricula $matricula, string $campoIc): array
    {
        self::abortSiEsquemaIncompleto();

        $meta = CalificacionesInicialSfqCatalogo::metaCampoIc($campoIc);
        abort_if($meta === null, 404);

        $curso = self::cursoEnContexto((int) $matricula->idCursos);
        abort_if($curso === null, 404);

        $materia = self::materiaPrincipalCurso((int) $curso->Id);
        abort_if($materia === null, 404);

        $fila = self::filaCalificaciones((int) $matricula->id, (int) $materia->ord);
        $ic = $fila !== null ? (string) ($fila->{$campoIc} ?? '') : '';

        $sala = (int) ($curso->c ?? 0);
        $filas = self::indicadoresParaCarga($sala, (int) $meta['etapa'], (string) $meta['area']);
        $filas = self::fusionarNotasEnIndicadores($filas, $ic);

        $legajo = $matricula->legajo;
        $alumnoLinea = trim(((string) ($legajo?->apellido ?? '')).' '.((string) ($legajo?->nombre ?? '')));

        return [
            'alumnoLinea' => $alumnoLinea !== '' ? $alumnoLinea : '—',
            'cursoLabel' => $curso->nombreParaListado(),
            'sala' => $sala,
            'etapa' => (int) $meta['etapa'],
            'area' => (string) $meta['area'],
            'campoIc' => $campoIc,
            'filas' => $filas,
        ];
    }

    /**
     * @param  list<array{nota: string}>  $filas
     */
    public static function guardarIndicadores(Matricula $matricula, string $campoIc, array $filas): void
    {
        self::abortSiEsquemaIncompleto();

        if (! CalificacionesInicialSfqCatalogo::esCampoIc($campoIc)) {
            abort(400);
        }

        foreach ($filas as $fila) {
            $d = (string) ($fila['nota'] ?? '');
            if ($d !== '' && ! CalificacionesInicialSfqCatalogo::digitoValido($d)) {
                abort(422, 'Nota de indicador no válida.');
            }
        }

        $materia = self::materiaPrincipalCurso((int) $matricula->idCursos);
        abort_if($materia === null, 422, 'No hay materia principal configurada para el curso.');

        $ic = self::filasAIC($filas);
        self::actualizarCampoCalificacion($matricula, (int) $materia->id, (int) $materia->ord, [$campoIc => $ic]);
    }

    /**
     * @return array{
     *     alumnoLinea: string,
     *     obs01: string,
     *     obs02: string,
     *     obs03: string,
     *     baObs01: string,
     *     baObs02: string,
     *     baObs03: string
     * }
     */
    public static function cargarFormularioObservaciones(Matricula $matricula): array
    {
        self::abortSiEsquemaIncompleto();
        self::abortSiObservacionesBaInexistentes();

        $materia = self::materiaPrincipalCurso((int) $matricula->idCursos);
        $fila = $materia !== null
            ? self::filaCalificaciones((int) $matricula->id, (int) $materia->ord)
            : null;

        $legajo = $matricula->legajo;
        $alumnoLinea = trim(((string) ($legajo?->apellido ?? '')).' '.((string) ($legajo?->nombre ?? '')));

        $out = [
            'alumnoLinea' => $alumnoLinea !== '' ? $alumnoLinea : '—',
        ];

        foreach (array_merge(
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_PEDAG,
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_BA,
        ) as $col) {
            $out[$col] = $fila !== null ? (string) ($fila->{$col} ?? '') : '';
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $observaciones
     */
    public static function guardarObservaciones(Matricula $matricula, array $observaciones): void
    {
        self::abortSiEsquemaIncompleto();
        self::abortSiObservacionesBaInexistentes();

        $camposPermitidos = array_merge(
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_PEDAG,
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_BA,
        );

        $payload = [];
        foreach ($camposPermitidos as $col) {
            $payload[$col] = trim((string) ($observaciones[$col] ?? ''));
            if (strlen($payload[$col]) > CalificacionesInicialSfqCatalogo::MAX_OBS_CARACTERES) {
                abort(422, 'Observación demasiado larga.');
            }
        }

        $materia = self::materiaPrincipalCurso((int) $matricula->idCursos);
        abort_if($materia === null, 422, 'No hay materia principal configurada para el curso.');

        self::actualizarCampoCalificacion(
            $matricula,
            (int) $materia->id,
            (int) $materia->ord,
            $payload,
        );
    }

    public static function guardarObservacionCampo(Matricula $matricula, string $campo, string $valor): void
    {
        self::abortSiEsquemaIncompleto();
        self::abortSiObservacionesBaInexistentes();

        $camposPermitidos = array_merge(
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_PEDAG,
            CalificacionesInicialSfqCatalogo::CAMPOS_OBS_BA,
        );

        if (! in_array($campo, $camposPermitidos, true)) {
            abort(400);
        }

        $valor = trim($valor);
        if (strlen($valor) > CalificacionesInicialSfqCatalogo::MAX_OBS_CARACTERES) {
            abort(422, 'Observación demasiado larga.');
        }

        $materia = self::materiaPrincipalCurso((int) $matricula->idCursos);
        abort_if($materia === null, 422, 'No hay materia principal configurada para el curso.');

        self::actualizarCampoCalificacion(
            $matricula,
            (int) $materia->id,
            (int) $materia->ord,
            [$campo => $valor],
        );
    }

    /**
     * Actualiza campos en la fila existente de `calificaciones`.
     * No crea filas: deben existir por seed de matrícula (o INSERT de datos).
     *
     * @param  array<string, string>  $campos
     */
    private static function actualizarCampoCalificacion(
        Matricula $matricula,
        int $idMateria,
        int $ord,
        array $campos,
    ): void {
        $idMatricula = (int) $matricula->id;

        $existente = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ord)
            ->first(['id']);

        if ($existente === null) {
            abort(422, 'No existe el registro de calificación para este alumno y materia.');
        }

        DB::table('calificaciones')
            ->where('id', (int) $existente->id)
            ->where('idMatricula', $idMatricula)
            ->update($campos);
    }

    /**
     * Filtro opcional por área (pedagógico / Bellas Artes) según columnas legacy disponibles.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarFiltroAreaIndicadores($query, string $area): void
    {
        if (Schema::hasColumn('indicadores', 'tipo')) {
            $valor = $area === CalificacionesInicialSfqCatalogo::AREA_BELLAS_ARTES ? 2 : 1;
            $query->where('i.tipo', $valor);

            return;
        }

        if (Schema::hasColumn('indicadores', 'idTipo')) {
            $valor = $area === CalificacionesInicialSfqCatalogo::AREA_BELLAS_ARTES ? 2 : 1;
            $query->where('i.idTipo', $valor);

            return;
        }

        if (Schema::hasColumn('indicadores', 'area')) {
            $query->where('i.area', $area === CalificacionesInicialSfqCatalogo::AREA_BELLAS_ARTES ? 'B' : 'P');
        }
    }
}
