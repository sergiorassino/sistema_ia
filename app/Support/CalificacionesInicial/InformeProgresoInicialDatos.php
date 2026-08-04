<?php

namespace App\Support\CalificacionesInicial;

use App\Models\Curso;
use App\Models\Ento;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use App\Support\CalificacionesPrimario\PlanillaCalificacionesPrimarioDatos;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del Informe de Progreso Escolar — nivel inicial (layout provincial).
 *
 * Espacios curriculares: preferir `materias.infoCalif = 1` (flag «Inf.» en Materias del año).
 * Si el curso no tiene ninguna materia marcada, se incluyen todas salvo JUSTIFICADAS / INJUSTIFICADAS
 * (evita un PDF solo con portada cuando el flag no fue configurado).
 */
final class InformeProgresoInicialDatos
{
    public static function abortSiColumnaInfoCalifInexistente(): void
    {
        abort_unless(
            Schema::hasColumn('materias', 'infoCalif'),
            503,
            'Falta aplicar la migración del campo «Síntesis y calificaciones» (infoCalif) en materias.'
        );
    }

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     etapa?: int,
     *     nombreEtapa?: string,
     *     ano?: int,
     *     insti?: string,
     *     direccion?: string,
     *     localidad?: string,
     *     departamento?: string,
     *     escudoProvincia?: ?string,
     *     alumno?: array<string, mixed>,
     *     materias?: list<array<string, mixed>>,
     *     justificadas?: string,
     *     injustificadas?: string
     * }
     */
    public static function buildForMatriculaEnContextoEscolar(int $idMatricula, int $etapa): array
    {
        self::abortSiColumnaInfoCalifInexistente();

        $etapa = $etapa === 2 ? 2 : 1;
        $ctx = schoolCtx();

        $mat = Matricula::query()
            ->with(['legajo', 'curso.turnoClase'])
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereNull('fechaBaja')
            ->first();

        if ($mat === null) {
            return ['ok' => false, 'error' => 'Matrícula no encontrada en el contexto activo.'];
        }

        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );
        if (! in_array((int) $mat->idCondiciones, $idsCondiciones, true)) {
            return ['ok' => false, 'error' => 'La matrícula no está en condición regular.'];
        }

        return self::buildDesdeMatricula($mat, $etapa);
    }

    /**
     * Datos del informe para la matrícula del estudiante en sesión (portal familia).
     *
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     etapa?: int,
     *     nombreEtapa?: string,
     *     ano?: int,
     *     insti?: string,
     *     direccion?: string,
     *     localidad?: string,
     *     departamento?: string,
     *     escudoProvincia?: ?string,
     *     alumno?: array<string, mixed>,
     *     materias?: list<array<string, mixed>>,
     *     justificadas?: string,
     *     injustificadas?: string
     * }
     */
    public static function buildDatosParaAlumno(int $etapa = 1): array
    {
        self::abortSiColumnaInfoCalifInexistente();

        $etapa = $etapa === 2 ? 2 : 1;
        $mat = CalificacionesPrimarioDatos::matriculaAlumnoEnSesion();
        if ($mat === null) {
            return ['ok' => false, 'error' => 'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.'];
        }

        if ($mat->fechaBaja !== null) {
            return ['ok' => false, 'error' => 'La matrícula no está activa en este ciclo lectivo.'];
        }

        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );
        if (! in_array((int) $mat->idCondiciones, $idsCondiciones, true)) {
            return ['ok' => false, 'error' => 'La matrícula no está en condición regular.'];
        }

        return self::buildDesdeMatricula($mat, $etapa);
    }

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     etapa: int,
     *     nombreEtapa: string,
     *     ano: int,
     *     insti: string,
     *     direccion: string,
     *     localidad: string,
     *     departamento: string,
     *     escudoProvincia: ?string,
     *     alumno: array<string, mixed>,
     *     materias: list<array<string, mixed>>,
     *     justificadas: string,
     *     injustificadas: string
     * }
     */
    public static function buildDesdeMatricula(Matricula $matricula, int $etapa): array
    {
        self::abortSiColumnaInfoCalifInexistente();
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();

        $etapa = $etapa === 2 ? 2 : 1;

        $idNivel = (int) $matricula->idNivel;
        $idTerlec = (int) $matricula->idTerlec;
        $idMatricula = (int) $matricula->id;
        $idCurso = (int) $matricula->idCursos;

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['insti', 'direccion', 'localidad', 'departamento']);

        $curso = $matricula->curso;
        if ($curso === null || (int) $curso->Id !== $idCurso) {
            $curso = Curso::query()
                ->with('turnoClase')
                ->where('Id', $idCurso)
                ->where('idNivel', $idNivel)
                ->where('idTerlec', $idTerlec)
                ->first();
        } elseif ((int) ($curso->idTurnoClase ?? 0) > 0 && ! $curso->relationLoaded('turnoClase')) {
            $curso->load('turnoClase');
        }

        if (! $matricula->relationLoaded('terlec')) {
            $matricula->load('terlec');
        }
        $anoTerlec = $matricula->terlec?->ano;
        if ($anoTerlec === null && $idTerlec > 0) {
            $anoTerlec = Terlec::query()->whereKey($idTerlec)->value('ano');
        }
        $ano = (int) ($anoTerlec ?? now()->year);

        $cursec = trim((string) ($curso?->cursec ?? ''));
        $legajo = $matricula->legajo;

        $fechnaci = '';
        if ($legajo?->fechnaci !== null) {
            $fechnaci = $legajo->fechnaci instanceof Carbon
                ? $legajo->fechnaci->format('d/m/Y')
                : Carbon::parse($legajo->fechnaci)->format('d/m/Y');
        }

        $edadSala = $cursec;
        if ($edadSala !== '' && mb_strlen($edadSala) > 1) {
            $edadSala = mb_substr($edadSala, 0, -1).' AÑOS';
        }

        $turno = self::turnoDesdeCurso($curso);
        $apellidoAlumno = trim((string) ($legajo?->apellido ?? ''));
        $nombreAlumno = trim((string) ($legajo?->nombre ?? ''));

        $alumno = [
            'apellido' => $apellidoAlumno,
            'nombre' => $nombreAlumno,
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'cursec' => $cursec,
            'edadSala' => $edadSala,
            'ln_ciudad' => trim((string) ($legajo?->ln_ciudad ?? '')),
            'fechnaci' => $fechnaci,
            'nacion' => trim((string) ($legajo?->nacion ?? '')),
            'ln_provincia' => trim((string) ($legajo?->ln_provincia ?? '')),
            'callenum' => trim((string) ($legajo?->callenum ?? '')),
            'barrio' => trim((string) ($legajo?->barrio ?? '')),
            'localidad' => trim((string) ($legajo?->localidad ?? '')),
            'nroMatricula' => trim((string) ($matricula->nroMatricula ?? '')),
            'turno' => $turno,
            'lineaCursoAlumno' => self::lineaCursoAlumno(
                self::salaParaEncabezadoInforme($curso),
                $turno,
                $apellidoAlumno,
                $nombreAlumno,
            ),
        ];

        $materiasRows = DB::table('materias')
            ->where('idCursos', $idCurso)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'ord', 'materia', 'infoCalif']);

        $candidatas = [];
        foreach ($materiasRows as $row) {
            $nombreMateria = trim((string) $row->materia);
            $materiaUpper = mb_strtoupper($nombreMateria);
            if (in_array($materiaUpper, ['JUSTIFICADAS', 'INJUSTIFICADAS'], true)) {
                continue;
            }

            $candidatas[] = $row;
        }

        $hayMarcadasParaInforme = false;
        foreach ($candidatas as $row) {
            if ((int) ($row->infoCalif ?? 0) === 1) {
                $hayMarcadasParaInforme = true;
                break;
            }
        }

        $materiasPdf = [];
        foreach ($candidatas as $row) {
            if ($hayMarcadasParaInforme && (int) ($row->infoCalif ?? 0) !== 1) {
                continue;
            }

            $nombreMateria = trim((string) $row->materia);
            $idMateria = (int) $row->id;
            $ord = (int) $row->ord;

            $obs = self::observacionesPorMateria($idMatricula, $idMateria, $ord);
            $indicadores = self::indicadoresPorMateria($idMateria);

            $materiasPdf[] = [
                'materia' => $nombreMateria,
                'docente' => self::docentePorMateria($idMateria),
                'indicador1' => $indicadores[1] ?? '',
                'indicador2' => $indicadores[2] ?? '',
                'etapa1' => (string) ($obs['etapa1'] ?? ''),
                'etapa2' => (string) ($obs['etapa2'] ?? ''),
            ];
        }

        $inasistencias = self::inasistenciasDeEtapa($matricula, $etapa);

        return [
            'ok' => true,
            'etapa' => $etapa,
            'nombreEtapa' => $etapa === 1 ? 'PRIMERA ETAPA' : 'SEGUNDA ETAPA',
            'ano' => $ano,
            'insti' => trim((string) ($ento?->insti ?? '')),
            'direccion' => trim((string) ($ento?->direccion ?? '')),
            'localidad' => trim((string) ($ento?->localidad ?? '')),
            'departamento' => trim((string) ($ento?->departamento ?? '')),
            'escudoProvincia' => PlanillaCalificacionesPrimarioDatos::rutaEscudoProvincia(),
            'alumno' => $alumno,
            'materias' => $materiasPdf,
            'justificadas' => $inasistencias['justificadas'],
            'injustificadas' => $inasistencias['injustificadas'],
        ];
    }

    /**
     * Inasistencias de la etapa desde `matricula` (just1/inju1 o just2/inju2).
     *
     * @return array{justificadas: string, injustificadas: string}
     */
    private static function inasistenciasDeEtapa(Matricula $matricula, int $etapa): array
    {
        $campoJust = $etapa === 2 ? 'just2' : 'just1';
        $campoInju = $etapa === 2 ? 'inju2' : 'inju1';

        return [
            'justificadas' => Schema::hasColumn('matricula', $campoJust)
                ? self::formatoInasistencia($matricula->{$campoJust} ?? null)
                : '',
            'injustificadas' => Schema::hasColumn('matricula', $campoInju)
                ? self::formatoInasistencia($matricula->{$campoInju} ?? null)
                : '',
        ];
    }

    private static function formatoInasistencia(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }

        // Evitar "0.0" / "0.00" cuando el legacy guarda cero numérico.
        if (is_numeric($texto) && (float) $texto == 0.0) {
            return '0';
        }

        return $texto;
    }

    /**
     * @return array{etapa1: string, etapa2: string}
     */
    private static function observacionesPorMateria(int $idMatricula, int $idMateria, int $ord): array
    {
        $vacío = ['etapa1' => '', 'etapa2' => ''];

        if (Schema::hasTable('calificaciones')
            && Schema::hasColumn('calificaciones', 'obs01')
            && Schema::hasColumn('calificaciones', 'obs02')) {
            // Preferir idMaterias (mismo criterio que la sincronización GE / carga por estudiante).
            $fila = null;
            if ($idMateria > 0) {
                $fila = DB::table('calificaciones')
                    ->where('idMatricula', $idMatricula)
                    ->where('idMaterias', $idMateria)
                    ->first(['obs01', 'obs02']);
            }

            if ($fila === null && $ord > 0) {
                $fila = DB::table('calificaciones')
                    ->where('idMatricula', $idMatricula)
                    ->where('ord', $ord)
                    ->first(['obs01', 'obs02']);
            }

            if ($fila !== null) {
                return [
                    'etapa1' => (string) ($fila->obs01 ?? ''),
                    'etapa2' => (string) ($fila->obs02 ?? ''),
                ];
            }
        }

        if (Schema::hasTable('infoxobse')) {
            $query = DB::table('infoxobse')->where('idMatricula', $idMatricula);
            if (Schema::hasColumn('infoxobse', 'idMaterias')) {
                $query->where('idMaterias', $idMateria);
            }
            $fila = $query->first(['etapa1', 'etapa2']);
            if ($fila !== null) {
                return [
                    'etapa1' => (string) ($fila->etapa1 ?? ''),
                    'etapa2' => (string) ($fila->etapa2 ?? ''),
                ];
            }
        }

        return $vacío;
    }

    /**
     * @return array<int, string>
     */
    private static function indicadoresPorMateria(int $idMateria): array
    {
        if (! CalificacionesInicialIndicadoresCatalogo::tablaDisponible()) {
            return [1 => '', 2 => ''];
        }

        return CalificacionesInicialIndicadoresDatos::textosPorEtapa($idMateria);
    }

    /**
     * Docente titular del espacio curricular (ppc + situacionrevista.sitRev = TITULAR).
     * Si no hay titular definido, usa el primer docente asignado (orden legacy por id).
     */
    private static function docentePorMateria(int $idMateria): string
    {
        $fila = self::queryDocentesPpcPorMateria($idMateria)
            ->whereRaw('UPPER(TRIM(COALESCE(sr.sitRev, ""))) = ?', ['TITULAR'])
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->orderBy('p.id')
            ->first(['p.apellido', 'p.nombre']);

        if ($fila === null) {
            $fila = self::queryDocentesPpcPorMateria($idMateria)
                ->orderBy('p.id')
                ->first(['p.apellido', 'p.nombre']);
        }

        if ($fila === null) {
            return '';
        }

        $apellido = mb_strtoupper(trim((string) ($fila->apellido ?? '')));
        $nombre = trim((string) ($fila->nombre ?? ''));

        return trim($apellido.($nombre !== '' ? ' '.$nombre : ''));
    }

    private static function queryDocentesPpcPorMateria(int $idMateria): \Illuminate\Database\Query\Builder
    {
        return DB::table('profesores as p')
            ->join('ppc', 'ppc.idProfesor', '=', 'p.id')
            ->leftJoin('situacionrevista as sr', 'sr.id', '=', 'ppc.idSituRevis')
            ->where('ppc.idMateria', $idMateria);
    }

    private static function salaParaEncabezadoInforme(?Curso $curso): string
    {
        if ($curso === null) {
            return '';
        }

        $seccion = trim((string) ($curso->s ?? ''));
        $nombreSala = trim((string) ($curso->c ?? ''));
        $cursec = trim((string) ($curso->cursec ?? ''));

        if ($nombreSala === '' && $cursec !== '') {
            $nombreSala = $cursec;
        }

        if (preg_match('/^(\d+)\s+"([^"]+)"$/u', $nombreSala, $coincidencias)) {
            return 'SALA DE '.$coincidencias[1].' "'.$coincidencias[2].'"';
        }

        if (preg_match('/^SALA DE (\d+)\s+"([^"]+)"$/iu', $nombreSala, $coincidencias)) {
            return 'SALA DE '.$coincidencias[1].' "'.$coincidencias[2].'"';
        }

        if (preg_match('/^SALA DE (\d+)$/iu', $nombreSala, $coincidencias)) {
            $nombreSala = 'SALA DE '.$coincidencias[1];
        } elseif (preg_match('/^(\d+)$/u', $nombreSala)) {
            $nombreSala = 'SALA DE '.$nombreSala;
        } elseif (preg_match('/^SALA DE \d+/iu', $cursec) && ! preg_match('/^SALA DE /iu', $nombreSala)) {
            if (preg_match('/^(SALA DE \d+)/iu', $cursec, $coincidencias)) {
                $nombreSala = $coincidencias[1];
            }
        }

        if ($seccion === '' && preg_match('/^(.+?\d)\s*([A-Za-zÁÉÍÓÚÑ])$/u', $nombreSala, $coincidencias)) {
            $nombreSala = trim($coincidencias[1]);
            $seccion = $coincidencias[2];
        } elseif ($seccion === '' && preg_match('/^(.+?\S)\s+([A-Za-zÁÉÍÓÚÑ])$/u', $nombreSala, $coincidencias)) {
            $nombreSala = trim($coincidencias[1]);
            $seccion = $coincidencias[2];
        }

        if (preg_match('/^(\d+)$/u', $nombreSala)) {
            $nombreSala = 'SALA DE '.$nombreSala;
        }

        $nombreSala = trim($nombreSala);
        if ($nombreSala === '') {
            return '';
        }

        return $seccion !== '' ? $nombreSala.' "'.$seccion.'"' : $nombreSala;
    }

    private static function lineaCursoAlumno(string $sala, string $turno, string $apellido, string $nombre): string
    {
        $sala = trim($sala);
        $turno = trim($turno);
        if ($turno !== '' && $sala !== '' && ! str_contains(mb_strtoupper($sala), mb_strtoupper($turno))) {
            $sala .= ' '.$turno;
        }

        $apellido = trim($apellido);
        $nombre = trim($nombre);
        $alumno = $apellido !== ''
            ? mb_strtoupper($apellido).($nombre !== '' ? ', '.$nombre : '')
            : $nombre;

        if ($sala === '') {
            return $alumno;
        }

        return $alumno !== '' ? $sala.' - '.$alumno : $sala;
    }

    private static function turnoDesdeCurso(?Curso $curso): string
    {
        if ($curso === null) {
            return '';
        }

        if ((int) ($curso->idTurnoClase ?? 0) > 0 && ! $curso->relationLoaded('turnoClase')) {
            $curso->load('turnoClase');
        }

        return trim((string) ($curso->turnoClase?->nombre ?? ''));
    }
}
