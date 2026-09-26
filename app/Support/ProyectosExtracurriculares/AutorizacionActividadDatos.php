<?php

namespace App\Support\ProyectosExtracurriculares;

use App\Models\ExtActividad;
use App\Models\Matricula;
use App\Models\Ento;
use App\Support\Listados\EstudiantesDatosConsulta;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Alumnos y datos de viaje para la autorización de una actividad extracurricular.
 */
final class AutorizacionActividadDatos
{
    /**
     * Membrete del PDF: logo y datos institucionales de `ento` del nivel activo.
     *
     * @return array{
     *   insti: string,
     *   direccion: string,
     *   localidad: string,
     *   provincia: string,
     *   departamento: string,
     *   categoria: string,
     *   subtitulo: string,
     *   adscripcion: string,
     *   telefono: string,
     *   mail: string,
     *   cue: string,
     *   ee: string,
     *   logo_file: ?string
     * }
     */
    public static function encabezadoInstitucional(): array
    {
        $base = schoolPdfHeaderData();
        $extra = [
            'departamento' => '',
            'categoria' => '',
            'telefono' => '',
            'mail' => '',
        ];

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel > 0 && Schema::hasTable('ento')) {
            $columnas = [];
            foreach (array_keys($extra) as $columna) {
                if (Schema::hasColumn('ento', $columna)) {
                    $columnas[] = $columna;
                }
            }
            if ($columnas !== []) {
                $ento = Ento::query()->where('idNivel', $idNivel)->first($columnas);
                foreach ($columnas as $columna) {
                    $extra[$columna] = trim((string) ($ento?->{$columna} ?? ''));
                }
            }
        }

        return [
            'insti' => trim((string) ($base['insti'] ?? '')),
            'direccion' => trim((string) ($base['direccion'] ?? '')),
            'localidad' => trim((string) ($base['localidad'] ?? '')),
            'provincia' => trim((string) ($base['provincia'] ?? '')),
            'departamento' => $extra['departamento'],
            'categoria' => $extra['categoria'],
            'subtitulo' => trim((string) config('tenant.institucional.membrete_subtitulo', '')),
            'adscripcion' => trim((string) config('tenant.institucional.membrete_adscripcion', '')),
            'telefono' => $extra['telefono'],
            'mail' => $extra['mail'],
            'cue' => trim((string) ($base['cue'] ?? '')),
            'ee' => trim((string) ($base['ee'] ?? '')),
            'logo_file' => $base['logo_file'] ?? null,
        ];
    }

    /**
     * @return Collection<int, Matricula>
     */
    public static function matriculasInvolucradas(ExtActividad $actividad): Collection
    {
        $ctx = schoolCtx();
        if ((int) $actividad->id_nivel !== (int) $ctx->idNivel || (int) $actividad->id_terlec !== (int) $ctx->idTerlec) {
            return collect();
        }

        $actividad->loadMissing(['cursos', 'alumnos']);

        $q = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', (int) $actividad->id_nivel)
            ->where('idTerlec', (int) $actividad->id_terlec)
            ->whereNull('fechaBaja');

        if ($actividad->tipo_grupo === ExtActividad::TIPO_GRUPO_CURSOS) {
            $ids = $actividad->cursos
                ->pluck('id_curso')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->values()
                ->all();
            if ($ids === []) {
                return collect();
            }
            $q->whereIn('idCursos', $ids)
                ->whereIn('idCondiciones', ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::REGULARES));
        } else {
            $ids = $actividad->alumnos
                ->pluck('id_legajo')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->values()
                ->all();
            if ($ids === []) {
                return collect();
            }
            $q->whereIn('idLegajos', $ids);
        }

        return OrdenAlfabeticoEstudiante::ordenarMatriculas($q->get())
            ->unique(static fn (Matricula $matricula) => (int) $matricula->idLegajos)
            ->values();
    }

    /**
     * @param  list<int>  $idsMatricula
     * @return list<array{apellido: string, nombre: string, dni: string, grupo_sanguineo: string, curso: string, division: string, calle: string, numero: string, localidad: string}>
     */
    public static function alumnosParaPdf(ExtActividad $actividad, array $idsMatricula): array
    {
        $permitidos = array_fill_keys(
            array_values(array_filter(array_map('intval', $idsMatricula), static fn (int $id) => $id > 0)),
            true
        );
        if ($permitidos === []) {
            return [];
        }

        $filas = [];
        foreach (self::matriculasInvolucradas($actividad) as $matricula) {
            if (! isset($permitidos[(int) $matricula->id])) {
                continue;
            }
            $legajo = $matricula->legajo;
            if ($legajo === null) {
                continue;
            }
            $curso = $matricula->curso;
            $domicilio = AutorizacionActividadRedaccion::calleYNumero(trim((string) ($legajo->callenum ?? '')));
            $cursoNumero = '';
            $division = '';
            if ($curso !== null) {
                $cursoNumero = trim((string) ($curso->c ?? ''));
                $division = trim((string) ($curso->s ?? ''));
                if ($cursoNumero === '') {
                    $cursoNumero = trim($curso->nombreParaListado());
                }
            }
            $filas[] = [
                'apellido' => trim((string) ($legajo->apellido ?? '')),
                'nombre' => trim((string) ($legajo->nombre ?? '')),
                'dni' => trim((string) ($legajo->dni ?? '')),
                'grupo_sanguineo' => EstudiantesDatosConsulta::valorGrupoSanguineo($legajo),
                'curso' => $cursoNumero,
                'division' => $division,
                'calle' => $domicilio['calle'],
                'numero' => $domicilio['numero'],
                'localidad' => trim((string) ($legajo->localidad ?? '')),
            ];
        }

        return $filas;
    }

    /**
     * @return array{
     *   nombre: string,
     *   lugar: string,
     *   localidad_salida: string,
     *   institucion: string,
     *   acompanantes: string,
     *   horario: string,
     *   participacion: string,
     *   jornadas: list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>
     * }
     */
    public static function viajeParaPdf(ExtActividad $actividad): array
    {
        $actividad->loadMissing(['fechas', 'docentes.profesor']);
        $header = schoolPdfHeaderData();
        $institucion = trim((string) ($header['insti'] ?? ''));
        if ($institucion === '') {
            $institucion = schoolNombre();
        }

        $jornadas = [];
        foreach ($actividad->fechas as $fecha) {
            $dia = $fecha->fecha;
            if ($dia === null) {
                continue;
            }
            $jornadas[] = [
                'fecha' => $dia->format('d/m/Y'),
                'fecha_larga' => AutorizacionActividadRedaccion::fechaLarga($dia),
                'inicio' => ExtActividadesService::formatearHora((string) ($fecha->hora_inicio ?? '')),
                'fin' => ExtActividadesService::formatearHora((string) ($fecha->hora_fin ?? '')),
            ];
        }

        $vistos = [];
        $nombres = [];
        $docentes = $actividad->docentes->sortBy(
            static fn ($docente) => $docente->rol === ExtActividad::ROL_A_CARGO ? 0 : 1
        );
        foreach ($docentes as $docente) {
            $id = (int) $docente->id_profesor;
            if ($id > 0 && isset($vistos[$id])) {
                continue;
            }
            if ($id > 0) {
                $vistos[$id] = true;
            }
            $profesor = $docente->profesor;
            if ($profesor === null) {
                continue;
            }
            $nombre = AutorizacionActividadRedaccion::nombreProsa(
                (string) ($profesor->nombre ?? ''),
                (string) ($profesor->apellido ?? '')
            );
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }

        return [
            'nombre' => trim((string) $actividad->nombre),
            'lugar' => trim((string) ($actividad->lugar ?? '')),
            'localidad_salida' => trim((string) ($header['localidad'] ?? '')),
            'institucion' => $institucion,
            'acompanantes' => AutorizacionActividadRedaccion::enumerar($nombres),
            'horario' => AutorizacionActividadRedaccion::horarioComplementario(
                trim((string) ($actividad->horario ?? '')),
                $jornadas
            ),
            'participacion' => AutorizacionActividadRedaccion::textoParticipacion(
                AutorizacionActividadRedaccion::seccionesCronograma((string) ($actividad->descripcion ?? ''))
            ),
            'jornadas' => $jornadas,
        ];
    }
}
