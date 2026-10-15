<?php

namespace App\Support\ProyectosExtracurriculares;

use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\Curso;
use App\Models\ExtActividad;
use App\Models\ExtActividadAlumno;
use App\Models\ExtActividadCurso;
use App\Models\ExtActividadDocente;
use App\Models\ExtFecha;
use App\Models\ExtTipoRegistro;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Profesor;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Mail\MailDesarrollo;
use App\Support\PreceptoresPorCurso;
use App\Support\ProfesorMenuPortal;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistencia y reglas de proyectos extracurriculares (tablas ext_*).
 */
final class ExtActividadesService
{
    public const TIPO_REGISTRO_ACTIVIDAD = 1;

    public const TIPO_REGISTRO_NIVEL = 2;

    public const TIPO_REGISTRO_INSTITUCIONAL = 3;

    public const DESCRIPCION_PLANTILLA = "Actividades Previas:\n\nActividades Durante:\n\nActividades Posteriores:\n";

    /**
     * @return list<string>
     */
    public static function tablasRequeridas(): array
    {
        return [
            'ext_tipo_registro',
            'ext_actividades',
            'ext_fechas',
            'ext_actividad_cursos',
            'ext_actividad_alumnos',
            'ext_actividad_docentes',
        ];
    }

    public static function tablasDisponibles(): bool
    {
        foreach (self::tablasRequeridas() as $tabla) {
            if (! Schema::hasTable($tabla)) {
                return false;
            }
        }

        return true;
    }

    public static function mensajeTablasFaltantes(): string
    {
        return 'No están disponibles las tablas de actividad institucional. Ejecute el SQL de actualización de esquema del módulo.';
    }

    public static function idNivelContexto(): int
    {
        return (int) (schoolCtx()->idNivel ?? 0);
    }

    public static function idTerlecContexto(): int
    {
        return (int) (schoolCtx()->idTerlec ?? 0);
    }

    /**
     * @return list<int>
     */
    public static function idsTiposRegistro(): array
    {
        return [
            self::TIPO_REGISTRO_ACTIVIDAD,
            self::TIPO_REGISTRO_NIVEL,
            self::TIPO_REGISTRO_INSTITUCIONAL,
        ];
    }

    /**
     * Viaje educativo (tipo 1): lugar, descripción, evaluación, presupuesto y otros docentes.
     * Actividad de nivel e institucional: nombre, fechas, horario, grupo y docentes a cargo.
     */
    public static function tipoEsViajeEducativo(int $idTipo): bool
    {
        return $idTipo === self::TIPO_REGISTRO_ACTIVIDAD;
    }

    public static function tipoUsaCamposCompletos(int $idTipo): bool
    {
        return self::tipoEsViajeEducativo($idTipo);
    }

    public static function tipoRegistroDefault(): ?ExtTipoRegistro
    {
        if (! Schema::hasTable('ext_tipo_registro')) {
            return null;
        }

        return ExtTipoRegistro::query()->find(self::TIPO_REGISTRO_ACTIVIDAD);
    }

    /**
     * @return Collection<int, ExtTipoRegistro>
     */
    public static function tiposParaSelector(): Collection
    {
        if (! Schema::hasTable('ext_tipo_registro')) {
            return collect();
        }

        return ExtTipoRegistro::query()
            ->whereIn('id', self::idsTiposRegistro())
            ->orderBy('id')
            ->get();
    }

    public static function scopedQuery(?int $idProponente = null)
    {
        $q = ExtActividad::query()
            ->where('id_nivel', self::idNivelContexto())
            ->where('id_terlec', self::idTerlecContexto());

        if ($idProponente !== null && $idProponente > 0) {
            $q->where('id_profesor_proponente', $idProponente);
        }

        return $q;
    }

    public static function scopedOrFail(int $id, ?int $idProponente = null): ExtActividad
    {
        $act = self::scopedQuery($idProponente)->whereKey($id)->first();
        abort_if($act === null, 404, 'La actividad no pertenece al contexto activo.');

        return $act;
    }

    public static function cargarCompleta(int $id, ?int $idProponente = null): ExtActividad
    {
        $act = self::scopedQuery($idProponente)
            ->with([
                'tipoRegistro',
                'nivel',
                'proponente',
                'fechas',
                'cursos.curso.curplan',
                'cursos.curso.turnoClase',
                'alumnos.legajo',
                'docentes.profesor',
            ])
            ->whereKey($id)
            ->first();
        abort_if($act === null, 404, 'La actividad no pertenece al contexto activo.');

        return $act;
    }

    /**
     * Actividad del ciclo lectivo activo, de cualquier nivel, para el detalle del calendario.
     */
    public static function cargarParaCalendario(int $id): ?ExtActividad
    {
        if ($id < 1 || ! self::tablasDisponibles()) {
            return null;
        }

        return ExtActividad::query()
            ->with([
                'tipoRegistro',
                'nivel',
                'proponente',
                'fechas',
                'cursos.curso.curplan',
                'cursos.curso.turnoClase',
                'alumnos.legajo',
                'docentes.profesor',
            ])
            ->where('id_terlec', self::idTerlecContexto())
            ->whereKey($id)
            ->first();
    }

    /**
     * @param  array{
     *   id_tipo_registro: int,
     *   nombre: string,
     *   lugar: string,
     *   horario: string,
     *   descripcion: string,
     *   evaluacion: string,
     *   presupuesto: string,
     *   tipo_grupo: string,
     *   fechas: list<array{fecha: string, hora_inicio: string, hora_fin: string}>,
     *   ids_cursos: list<int>,
     *   ids_alumnos: list<int>,
     *   ids_docentes_a_cargo: list<int>,
     *   ids_otros_docentes: list<int>
     * }  $datos
     */
    public static function guardar(?int $id, array $datos, int $idProponente): ExtActividad
    {
        $idNivel = self::idNivelContexto();
        $idTerlec = self::idTerlecContexto();
        $idTipo = (int) ($datos['id_tipo_registro'] ?? self::TIPO_REGISTRO_ACTIVIDAD);
        if (! in_array($idTipo, self::idsTiposRegistro(), true) || ! ExtTipoRegistro::query()->whereKey($idTipo)->exists()) {
            throw new \RuntimeException('El tipo de registro no está disponible.');
        }
        $camposCompletos = self::tipoUsaCamposCompletos($idTipo);

        return DB::transaction(function () use ($id, $datos, $idProponente, $idNivel, $idTerlec, $idTipo, $camposCompletos) {
            if ($id !== null && $id > 0) {
                $act = self::scopedOrFail($id, $idProponente);
                abort_unless($act->estaPendiente(), 403, 'Solo se puede editar una actividad pendiente de aprobación.');
            } else {
                $act = new ExtActividad;
                $act->id_tipo_registro = $idTipo;
                $act->id_nivel = $idNivel;
                $act->id_terlec = $idTerlec;
                $act->id_profesor_proponente = $idProponente;
                $act->estado = ExtActividad::ESTADO_PENDIENTE;
            }

            $horario = trim($datos['horario']);
            if ($horario === '') {
                $horario = self::horarioDesdeFechas($datos['fechas']);
            }

            $evaluacion = $camposCompletos ? trim((string) ($datos['evaluacion'] ?? '')) : '';
            $presupuesto = $camposCompletos ? trim((string) ($datos['presupuesto'] ?? '')) : '';
            $lugar = $camposCompletos ? trim((string) ($datos['lugar'] ?? '')) : '';
            $descripcion = $camposCompletos ? (string) ($datos['descripcion'] ?? '') : '';
            $payload = [
                'id_tipo_registro' => $idTipo,
                'nombre' => $datos['nombre'],
                'lugar' => $lugar !== '' ? $lugar : null,
                'horario' => $horario !== '' ? $horario : null,
                'descripcion' => $descripcion,
                'evaluacion' => $evaluacion !== '' ? $evaluacion : null,
                'presupuesto' => $presupuesto !== '' ? $presupuesto : null,
                'tipo_grupo' => $datos['tipo_grupo'],
            ];
            if ($id === null || $id <= 0) {
                $payload['id_nivel'] = $idNivel;
                $payload['id_terlec'] = $idTerlec;
                $payload['id_profesor_proponente'] = $idProponente;
                $payload['estado'] = ExtActividad::ESTADO_PENDIENTE;
            }

            $preparado = PersistenciaColumnas::prepararPayload('ext_actividades', $payload);
            if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                throw new \RuntimeException(PersistenciaColumnas::mensajeColumnasInexistentes(
                    'ext_actividades',
                    $preparado['columnas_con_valor_sin_columna']
                ));
            }
            foreach ($preparado['payload'] as $columna => $valor) {
                $act->{$columna} = $valor;
            }
            $act->save();

            $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
                'ext_actividades',
                ['id' => $act->id],
                ['nombre' => $datos['nombre']]
            );
            if ($noPersistidas !== []) {
                throw new \RuntimeException(PersistenciaColumnas::mensajeColumnasNoPersistidas(
                    'ext_actividades',
                    $noPersistidas
                ));
            }

            ExtFecha::query()->where('id_actividad', $act->id)->delete();
            foreach ($datos['fechas'] as $fila) {
                ExtFecha::query()->create([
                    'id_actividad' => $act->id,
                    'fecha' => $fila['fecha'],
                    'hora_inicio' => $fila['hora_inicio'] !== '' ? $fila['hora_inicio'] : null,
                    'hora_fin' => $fila['hora_fin'] !== '' ? $fila['hora_fin'] : null,
                ]);
            }

            $act->cursos()->delete();
            $act->alumnos()->delete();
            if ($datos['tipo_grupo'] === ExtActividad::TIPO_GRUPO_CURSOS) {
                foreach ($datos['ids_cursos'] as $idCurso) {
                    ExtActividadCurso::query()->create([
                        'id_actividad' => $act->id,
                        'id_curso' => $idCurso,
                    ]);
                }
            } else {
                foreach ($datos['ids_alumnos'] as $idLegajo) {
                    ExtActividadAlumno::query()->create([
                        'id_actividad' => $act->id,
                        'id_legajo' => $idLegajo,
                    ]);
                }
            }

            $act->docentes()->delete();
            foreach ($datos['ids_docentes_a_cargo'] as $idProf) {
                ExtActividadDocente::query()->create([
                    'id_actividad' => $act->id,
                    'id_profesor' => $idProf,
                    'rol' => ExtActividad::ROL_A_CARGO,
                ]);
            }
            $otros = $camposCompletos ? $datos['ids_otros_docentes'] : [];
            foreach ($otros as $idProf) {
                if (in_array($idProf, $datos['ids_docentes_a_cargo'], true)) {
                    continue;
                }
                ExtActividadDocente::query()->create([
                    'id_actividad' => $act->id,
                    'id_profesor' => $idProf,
                    'rol' => ExtActividad::ROL_OTRO,
                ]);
            }

            return $act->fresh();
        });
    }

    public static function eliminar(int $id, int $idProponente): void
    {
        $act = self::scopedOrFail($id, $idProponente);
        abort_unless($act->estaPendiente(), 403, 'Solo se puede eliminar una actividad pendiente de aprobación.');

        DB::transaction(function () use ($act) {
            ExtFecha::query()->where('id_actividad', $act->id)->delete();
            $act->cursos()->delete();
            $act->alumnos()->delete();
            $act->docentes()->delete();
            $act->delete();
        });
    }

    public static function aprobar(int $id, int $idAprobador): ExtActividad
    {
        $act = self::scopedOrFail($id);
        abort_unless($act->estaPendiente(), 403, 'La actividad ya está aprobada.');

        $act->estado = ExtActividad::ESTADO_APROBADO;
        $act->aprobado_por = $idAprobador;
        $act->aprobado_at = now();
        $act->save();

        return $act;
    }

    public static function volverAPendiente(int $id): ExtActividad
    {
        $act = self::scopedOrFail($id);
        abort_unless($act->estaAprobada(), 403, 'La actividad no está aprobada.');

        $act->estado = ExtActividad::ESTADO_PENDIENTE;
        $act->aprobado_por = null;
        $act->aprobado_at = null;
        $act->save();

        return $act;
    }

    /** Correo de refuerzo: activo salvo APP_ENV=local (MailDesarrollo). Escape: MAIL_FORCE_REAL=true. */
    public static function refuerzoMailActivo(): bool
    {
        return ! MailDesarrollo::bloquearSmtp();
    }

    public static function textoConfirmarComunicar(): string
    {
        if (self::refuerzoMailActivo()) {
            return 'Se enviará un comunicado institucional y un correo de refuerzo a:';
        }

        return 'Se enviará un comunicado institucional a:';
    }

    /**
     * Destinatarios del comunicado (sin el remitente) y su rol en el proyecto.
     *
     * @return list<array{id: int, nombre: string, participaciones: list<string>}>
     */
    public static function destinatariosComunicacionDetalle(ExtActividad $act, ?int $idExcluir = null): array
    {
        $idNivel = (int) $act->id_nivel;
        $idTerlec = (int) $act->id_terlec;
        $idsCursos = self::idsCursosInvolucrados($act);
        $etiquetasCursos = self::etiquetasCursos($idsCursos, $idNivel, $idTerlec);
        $ppcPorProfesor = self::cursosPorDocentePpc($idsCursos, $idNivel, $idTerlec);

        /** @var array<int, array{a_cargo: bool, otro: bool, cursos_ppc: list<int>, cursos_pre: list<int>}> $buckets */
        $buckets = [];
        $asegurar = static function (int $id) use (&$buckets): void {
            if ($id < 1) {
                return;
            }
            $buckets[$id] ??= [
                'a_cargo' => false,
                'otro' => false,
                'cursos_ppc' => [],
                'cursos_pre' => [],
            ];
        };

        foreach ($act->docentes as $d) {
            $id = (int) $d->id_profesor;
            $asegurar($id);
            if ($id < 1) {
                continue;
            }
            if ((string) $d->rol === ExtActividad::ROL_A_CARGO) {
                $buckets[$id]['a_cargo'] = true;
            } else {
                $buckets[$id]['otro'] = true;
            }
        }

        foreach ($ppcPorProfesor as $idProf => $idsCursoProf) {
            $idProf = (int) $idProf;
            $asegurar($idProf);
            if ($idProf < 1) {
                continue;
            }
            foreach ($idsCursoProf as $idCurso) {
                $idCurso = (int) $idCurso;
                if ($idCurso > 0 && ! in_array($idCurso, $buckets[$idProf]['cursos_ppc'], true)) {
                    $buckets[$idProf]['cursos_ppc'][] = $idCurso;
                }
            }
        }

        foreach ($idsCursos as $idCurso) {
            foreach (PreceptoresPorCurso::idsPreceptores((int) $idCurso, $idNivel, $idTerlec) as $idProf) {
                $idProf = (int) $idProf;
                $asegurar($idProf);
                if ($idProf < 1) {
                    continue;
                }
                if (! in_array((int) $idCurso, $buckets[$idProf]['cursos_pre'], true)) {
                    $buckets[$idProf]['cursos_pre'][] = (int) $idCurso;
                }
            }
        }

        if ($idExcluir !== null && $idExcluir > 0) {
            unset($buckets[$idExcluir]);
        }

        $ids = array_values(array_filter(array_map('intval', array_keys($buckets)), static fn (int $id) => $id > 0));
        if ($ids === []) {
            return [];
        }

        $profesores = Profesor::query()->whereIn('id', $ids)->get()->keyBy('id');
        $filas = [];
        foreach ($buckets as $idProf => $info) {
            $idProf = (int) $idProf;
            $p = $profesores->get($idProf);
            $nombre = $p instanceof Profesor
                ? trim($p->nombre_completo)
                : '';
            if ($nombre === '' || $nombre === ',') {
                $nombre = 'Legajo #'.$idProf;
            }

            $partes = [];
            if ($info['a_cargo']) {
                $partes[] = 'Docente a cargo';
            }
            if ($info['otro']) {
                $partes[] = 'Otro docente';
            }
            if ($info['cursos_ppc'] !== []) {
                $cursos = self::textoCursosDe($info['cursos_ppc'], $etiquetasCursos);
                $partes[] = $cursos !== '' ? 'Docente del curso ('.$cursos.')' : 'Docente del curso';
            }
            if ($info['cursos_pre'] !== []) {
                $cursos = self::textoCursosDe($info['cursos_pre'], $etiquetasCursos);
                $partes[] = $cursos !== '' ? 'Preceptor ('.$cursos.')' : 'Preceptor';
            }
            if ($partes === []) {
                continue;
            }

            $filas[] = [
                'id' => $idProf,
                'nombre' => $nombre,
                'participaciones' => $partes,
            ];
        }

        usort($filas, static fn (array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));

        return $filas;
    }

    public static function htmlConfirmarComunicar(int $id, ?int $idExcluir = null): string
    {
        $act = self::cargarCompleta($id);
        $filas = self::destinatariosComunicacionDetalle($act, $idExcluir);
        if ($filas === []) {
            return '';
        }

        $items = '';
        foreach ($filas as $fila) {
            $nombre = e($fila['nombre']);
            $part = e(implode(' · ', $fila['participaciones']));
            $items .= '<li style="margin:0.2rem 0;"><strong>'.$nombre.'</strong> — '.$part.'</li>';
        }

        $intro = e(self::textoConfirmarComunicar());
        $pieMail = self::refuerzoMailActivo()
            ? ''
            : '<p style="margin:0.85rem 0 0;text-align:left;font-size:0.85rem;color:#666;">El correo de refuerzo está desactivado en desarrollo (APP_ENV=local).</p>';

        return '<p style="margin:0 0 0.65rem;text-align:left;font-size:0.95rem;color:#444;">'.$intro.'</p>'
            .'<ul style="margin:0;padding-left:1.15rem;text-align:left;font-size:0.9rem;color:#333;max-height:16rem;overflow:auto;">'.$items.'</ul>'
            .$pieMail
            .'<p style="margin:0.85rem 0 0;text-align:left;font-size:0.95rem;color:#444;">¿Continuar?</p>';
    }

    /**
     * @return array{ok: bool, mensaje: string, cantidad: int, refuerzo_mail_pedido: bool, refuerzo_mail_desarrollo: bool}
     */
    public static function comunicarInvolucrados(int $id): array
    {
        $act = self::cargarCompleta($id);
        abort_unless($act->estaAprobada(), 403, 'Solo se comunica una actividad aprobada.');

        /** @var Profesor|null $emisor */
        $emisor = Auth::user();
        if (! $emisor instanceof Profesor) {
            return self::resultadoComunicar(false, 'No hay un usuario de personal autenticado.', 0);
        }

        $ids = self::idsDestinatariosComunicacion($act);
        $ids = array_values(array_diff($ids, [(int) $emisor->id]));
        if ($ids === []) {
            return self::resultadoComunicar(false, 'No hay destinatarios distintos del remitente para esta actividad.', 0);
        }

        $emisor->loadMissing('tipo');
        $rolEmisor = CanalesPolicy::claveRolDeProfesor($emisor);
        $porClave = [];
        $profesores = Profesor::query()->with('tipo')->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $idProf) {
            $p = $profesores->get($idProf);
            if (! $p instanceof Profesor) {
                continue;
            }
            $clave = CanalesPolicy::claveRolDeProfesor($p);
            $porClave[$clave][] = $idProf;
        }

        if ($porClave === []) {
            return self::resultadoComunicar(false, 'No se encontraron destinatarios vigentes.', 0);
        }

        $contenido = self::textoComunicado($act);
        $asunto = 'Actividad institucional aprobada: '.$act->nombre;
        $enviados = 0;
        $omitidosCanal = 0;
        $pidioEmail = false;
        $refuerzoMail = self::refuerzoMailActivo();

        foreach ($porClave as $claveRec => $idsGrupo) {
            if (! CanalesPolicy::puedeIniciar($rolEmisor, $claveRec)) {
                $omitidosCanal += count($idsGrupo);

                continue;
            }
            $medios = CanalesPolicy::mediosPermitidos($rolEmisor, $claveRec);
            if ($medios === []) {
                $omitidosCanal += count($idsGrupo);

                continue;
            }

            if ($refuerzoMail) {
                if (! in_array('email', $medios, true)) {
                    $medios[] = 'email';
                }
                $pidioEmail = true;
            } else {
                $medios = array_values(array_filter($medios, static fn (string $m): bool => $m !== 'email'));
            }

            ComunicacionesRepository::crearHiloConMensaje([
                'asunto' => $asunto,
                'contenido' => $contenido,
                'scope' => 'docentes',
                'id_legajos' => [],
                'id_curso' => null,
                'cursos_envio' => null,
                'id_nivel' => (int) $act->id_nivel,
                'id_terlec' => (int) $act->id_terlec,
                'creado_por_tipo' => 'profesor',
                'creado_por_id' => (int) $emisor->id,
                'creado_por_rol' => $rolEmisor,
                'rol_receptor' => $claveRec,
                'vinculo_familiar' => null,
                'nombre_remitente' => $emisor->nombre_completo,
                'dni_remitente' => (string) ($emisor->dni ?? ''),
                'destinatarios_profesores' => $idsGrupo,
                'familia_puede_responder' => false,
                'docentes_permite_respuestas' => true,
            ], $medios);

            $enviados += count($idsGrupo);
        }

        if ($enviados < 1) {
            return self::resultadoComunicar(
                false,
                'No hay canales de comunicación habilitados hacia los involucrados. Revise la parametrización de canales.',
                0,
            );
        }

        $act->comunicado_at = now();
        $act->save();

        $extra = $omitidosCanal > 0
            ? ' '.$omitidosCanal.' destinatario(s) no recibieron el aviso por falta de canal.'
            : '';
        $mailExtra = $pidioEmail
            ? ' Se envió correo de refuerzo.'
            : ' El correo de refuerzo está desactivado en desarrollo (APP_ENV=local).';

        return self::resultadoComunicar(
            true,
            'Se comunicó a '.$enviados.' involucrado(s).'.$extra.$mailExtra,
            $enviados,
            $pidioEmail,
        );
    }

    /**
     * Organizadores + docentes ppc de los cursos involucrados + preceptores.
     *
     * @return list<int>
     */
    public static function idsDestinatariosComunicacion(ExtActividad $act): array
    {
        $ids = [];
        foreach ($act->docentes as $d) {
            $id = (int) $d->id_profesor;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $idsCursos = self::idsCursosInvolucrados($act);
        $idNivel = (int) $act->id_nivel;
        $idTerlec = (int) $act->id_terlec;

        foreach (self::idsDocentesPpcDeCursos($idsCursos, $idNivel, $idTerlec) as $id) {
            $ids[] = $id;
        }

        foreach ($idsCursos as $idCurso) {
            foreach (PreceptoresPorCurso::idsPreceptores($idCurso, $idNivel, $idTerlec) as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

    /**
     * @return list<int>
     */
    public static function idsCursosInvolucrados(ExtActividad $act): array
    {
        if ($act->tipo_grupo === ExtActividad::TIPO_GRUPO_CURSOS) {
            return $act->cursos->pluck('id_curso')->map(static fn ($id) => (int) $id)->filter()->unique()->values()->all();
        }

        $idsLegajo = $act->alumnos->pluck('id_legajo')->map(static fn ($id) => (int) $id)->filter()->all();
        if ($idsLegajo === []) {
            return [];
        }

        return Matricula::query()
            ->where('idNivel', (int) $act->id_nivel)
            ->where('idTerlec', (int) $act->id_terlec)
            ->whereIn('idLegajos', $idsLegajo)
            ->whereIn('idCondiciones', ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::REGULARES))
            ->pluck('idCursos')
            ->map(static fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Docentes asignados en ppc a materias de los cursos (ciclo/nivel).
     *
     * @param  list<int>  $idsCursos
     * @return list<int>
     */
    public static function idsDocentesPpcDeCursos(array $idsCursos, int $idNivel, int $idTerlec): array
    {
        return array_values(array_map('intval', array_keys(self::cursosPorDocentePpc($idsCursos, $idNivel, $idTerlec))));
    }

    /**
     * @param  list<int>  $idsCursos
     * @return array<int, list<int>> id profesor => ids de curso
     */
    public static function cursosPorDocentePpc(array $idsCursos, int $idNivel, int $idTerlec): array
    {
        $idsCursos = array_values(array_unique(array_filter($idsCursos, static fn (int $id) => $id > 0)));
        if ($idsCursos === [] || ! Schema::hasTable('ppc') || ! Schema::hasTable('materias')) {
            return [];
        }

        $filas = DB::table('ppc as ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->whereIn('m.idCursos', $idsCursos)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->get(['ppc.idProfesor', 'm.idCursos']);

        $out = [];
        foreach ($filas as $fila) {
            $idProf = (int) ($fila->idProfesor ?? 0);
            $idCurso = (int) ($fila->idCursos ?? 0);
            if ($idProf < 1 || $idCurso < 1) {
                continue;
            }
            $out[$idProf] ??= [];
            if (! in_array($idCurso, $out[$idProf], true)) {
                $out[$idProf][] = $idCurso;
            }
        }

        return $out;
    }

    /**
     * Eventos del calendario en un rango de fechas, de todos los niveles del ciclo activo.
     * Por defecto solo aprobados. Con permiso, también las pendientes de todos los niveles.
     *
     * @return Collection<int, object>
     */
    public static function eventosEnRango(Carbon $desde, Carbon $hasta, bool $incluirPendientes = false): Collection
    {
        if (! self::tablasDisponibles()) {
            return collect();
        }

        return ExtFecha::query()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereHas('actividad', function ($q) use ($incluirPendientes) {
                $q->where('id_terlec', self::idTerlecContexto());
                if ($incluirPendientes) {
                    $q->whereIn('estado', [
                        ExtActividad::ESTADO_APROBADO,
                        ExtActividad::ESTADO_PENDIENTE,
                    ]);
                } else {
                    $q->where('estado', ExtActividad::ESTADO_APROBADO);
                }
            })
            ->with(['actividad.tipoRegistro', 'actividad.nivel'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Fechas aprobadas de la semana en curso, de lunes a domingo (widget).
     * La semana es lunes–domingo, igual que el calendario. Las anteriores a hoy
     * se muestran tachadas en la vista.
     *
     * @return Collection<int, ExtFecha>
     */
    public static function proximosEventos(): Collection
    {
        if (! self::tablasDisponibles()) {
            return collect();
        }

        $hoy = Carbon::today();
        $desde = $hoy->copy()->startOfWeek(Carbon::MONDAY);
        $hasta = $hoy->copy()->endOfWeek(Carbon::SUNDAY);

        return ExtFecha::query()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereHas('actividad', function ($q) {
                $q->where('id_nivel', self::idNivelContexto())
                    ->where('id_terlec', self::idTerlecContexto())
                    ->where('estado', ExtActividad::ESTADO_APROBADO);
            })
            ->with(['actividad.tipoRegistro'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * @return Collection<int, Curso>
     */
    public static function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->where('idNivel', self::idNivelContexto())
            ->where('idTerlec', self::idTerlecContexto())
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get();
    }

    /**
     * @return list<array{id: int, label: string, dni: string}>
     */
    public static function alumnosParaSelector(string $filtro = '', int $limit = 80): array
    {
        $t = mb_strtolower(trim($filtro));
        $idsCond = ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::REGULARES);

        $q = Matricula::query()
            ->join('legajos as l', 'l.id', '=', 'matricula.idLegajos')
            ->where('matricula.idNivel', self::idNivelContexto())
            ->where('matricula.idTerlec', self::idTerlecContexto())
            ->whereIn('matricula.idCondiciones', $idsCond)
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'));

        if ($t !== '') {
            $q->where(function ($w) use ($t) {
                $w->whereRaw('LOWER(l.apellido) LIKE ?', ['%'.$t.'%'])
                    ->orWhereRaw('LOWER(l.nombre) LIKE ?', ['%'.$t.'%']);
                if (ctype_digit($t)) {
                    $w->orWhere('l.dni', (int) $t);
                }
            });
        }

        $rows = $q->limit($limit)->get(['l.id', 'l.apellido', 'l.nombre', 'l.dni']);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'label' => trim((string) $r->apellido.', '.(string) $r->nombre),
                'dni' => $r->dni !== null ? (string) $r->dni : '',
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function filtrarIdsAlumnosDelContexto(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $idsCond = ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::REGULARES);

        return Matricula::query()
            ->where('idNivel', self::idNivelContexto())
            ->where('idTerlec', self::idTerlecContexto())
            ->whereIn('idCondiciones', $idsCond)
            ->whereIn('idLegajos', $ids)
            ->pluck('idLegajos')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, label: string, dni: string}>
     */
    public static function alumnosPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $rows = Legajo::query()
            ->whereIn('id', $ids)
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('nombre'))
            ->get(['id', 'apellido', 'nombre', 'dni']);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'label' => trim((string) $r->apellido.', '.(string) $r->nombre),
                'dni' => $r->dni !== null ? (string) $r->dni : '',
            ];
        }

        return $out;
    }

    /**
     * Profesores del nivel con rol Profesor/a (IdTipoProf = 6).
     *
     * @return list<array{id: int, label: string, dni: string}>
     */
    public static function docentesAulaParaSelector(string $filtro = '', int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $t = mb_strtolower(trim($filtro));

        $q = Profesor::query()
            ->where('nivel', self::idNivelContexto())
            ->where('IdTipoProf', ProfesorMenuPortal::ID_TIPO_PROFESOR_AULA)
            ->orderBy('apellido')
            ->orderBy('nombre');

        if ($t !== '') {
            $q->where(function ($w) use ($t) {
                $w->whereRaw('LOWER(apellido) LIKE ?', ['%'.$t.'%'])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', ['%'.$t.'%']);
                if (ctype_digit($t)) {
                    $w->orWhere('dni', $t);
                }
            });
        }

        $rows = $q->limit($limit)->get(['id', 'apellido', 'nombre', 'dni']);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'label' => trim((string) $r->apellido.', '.(string) $r->nombre),
                'dni' => $r->dni !== null ? (string) $r->dni : '',
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function filtrarIdsDocentesAula(array $ids): array
    {
        return ComunicacionesRepository::filtrarIdsProfesoresPorIdTipoProf(
            $ids,
            self::idNivelContexto(),
            ProfesorMenuPortal::ID_TIPO_PROFESOR_AULA,
        );
    }

    public static function etiquetaEstado(string $estado): string
    {
        return $estado === ExtActividad::ESTADO_APROBADO ? 'Aprobado' : 'Pendiente';
    }

    public static function formatearHora(?string $hora): string
    {
        $hora = trim((string) $hora);
        if ($hora === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2];
        }

        return $hora;
    }

    /**
     * @param  list<array{fecha: string, hora_inicio: string, hora_fin: string}>  $fechas
     */
    public static function horarioDesdeFechas(array $fechas): string
    {
        $partes = [];
        foreach ($fechas as $fila) {
            $ini = self::formatearHora($fila['hora_inicio'] ?? '');
            $fin = self::formatearHora($fila['hora_fin'] ?? '');
            if ($ini === '' && $fin === '') {
                continue;
            }
            $partes[] = trim($ini.($ini !== '' && $fin !== '' ? ' a ' : '').$fin);
        }

        return implode('; ', array_unique($partes));
    }

    public static function textoResumenFechas(ExtActividad $act): string
    {
        $out = [];
        foreach ($act->fechas as $f) {
            $d = $f->fecha instanceof Carbon ? $f->fecha->format('d/m/Y') : Carbon::parse((string) $f->fecha)->format('d/m/Y');
            $ini = self::formatearHora((string) ($f->hora_inicio ?? ''));
            $fin = self::formatearHora((string) ($f->hora_fin ?? ''));
            $hora = trim($ini.($ini !== '' && $fin !== '' ? '–' : '').$fin);
            $out[] = $hora !== '' ? $d.' '.$hora : $d;
        }

        return implode(', ', $out);
    }

    public static function mensajeDesdeQueryException(QueryException $e): string
    {
        return 'No se pudo guardar la actividad. Verifique los datos e intente nuevamente.';
    }

    /**
     * @param  list<int>  $idsCursos
     * @return array<int, string>
     */
    private static function etiquetasCursos(array $idsCursos, int $idNivel, int $idTerlec): array
    {
        $idsCursos = array_values(array_unique(array_filter($idsCursos, static fn (int $id) => $id > 0)));
        if ($idsCursos === []) {
            return [];
        }

        return Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->whereIn('Id', $idsCursos)
            ->get()
            ->mapWithKeys(static function (Curso $c): array {
                $nombre = trim($c->nombreParaListado());

                return [(int) $c->getKey() => $nombre !== '' ? $nombre : ('Curso #'.(int) $c->getKey())];
            })
            ->all();
    }

    /**
     * @param  list<int>  $idsCursos
     * @param  array<int, string>  $etiquetas
     */
    private static function textoCursosDe(array $idsCursos, array $etiquetas): string
    {
        $nombres = [];
        foreach ($idsCursos as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $nombres[] = $etiquetas[$id] ?? ('Curso #'.$id);
        }

        return implode(', ', $nombres);
    }

    /**
     * @return array{ok: bool, mensaje: string, cantidad: int, refuerzo_mail_pedido: bool, refuerzo_mail_desarrollo: bool}
     */
    private static function resultadoComunicar(bool $ok, string $mensaje, int $cantidad, bool $pidioEmail = false): array
    {
        return [
            'ok' => $ok,
            'mensaje' => $mensaje,
            'cantidad' => $cantidad,
            'refuerzo_mail_pedido' => $pidioEmail,
            'refuerzo_mail_desarrollo' => ! self::refuerzoMailActivo(),
        ];
    }

    private static function textoComunicado(ExtActividad $act): string
    {
        $fechas = self::textoResumenFechas($act);
        $grupo = self::textoGrupoInvolucrado($act);
        $aCargo = $act->docentes
            ->where('rol', ExtActividad::ROL_A_CARGO)
            ->map(fn ($d) => $d->profesor?->nombre_completo)
            ->filter()
            ->implode('; ');
        $otros = $act->docentes
            ->where('rol', ExtActividad::ROL_OTRO)
            ->map(fn ($d) => $d->profesor?->nombre_completo)
            ->filter()
            ->implode('; ');

        $esViaje = self::tipoEsViajeEducativo((int) $act->id_tipo_registro);
        $lineas = [
            'Se aprobó la siguiente actividad:',
            '',
            'Actividad: '.$act->nombre,
            'Tipo: '.($act->tipoRegistro?->nombre ?? 'Viaje Educativo'),
            'Fechas: '.($fechas !== '' ? $fechas : '—'),
        ];
        if ($esViaje) {
            $lineas[] = 'Lugar: '.($act->lugar ?: '—');
        }
        $lineas[] = 'Horario: '.($act->horario ?: '—');
        $lineas[] = 'Grupo involucrado: '.($grupo !== '' ? $grupo : '—');
        $lineas[] = 'Docente a cargo: '.($aCargo !== '' ? $aCargo : '—');
        if ($otros !== '') {
            $lineas[] = 'Otros docentes: '.$otros;
        }
        if ($esViaje && trim((string) $act->descripcion) !== '') {
            $lineas[] = '';
            $lineas[] = 'Descripción:';
            $lineas[] = trim((string) $act->descripcion);
        }
        if (trim((string) $act->evaluacion) !== '') {
            $lineas[] = '';
            $lineas[] = 'Evaluación:';
            $lineas[] = trim((string) $act->evaluacion);
        }
        if (trim((string) ($act->presupuesto ?? '')) !== '') {
            $lineas[] = '';
            $lineas[] = 'Presupuesto de la actividad:';
            $lineas[] = trim((string) $act->presupuesto);
        }

        return implode("\n", $lineas);
    }

    public static function textoGrupoInvolucrado(ExtActividad $act): string
    {
        if ($act->tipo_grupo === ExtActividad::TIPO_GRUPO_CURSOS) {
            return $act->cursos
                ->map(function ($rel) {
                    $c = $rel->curso;
                    if (! $c instanceof Curso) {
                        return null;
                    }
                    $c->loadMissing(['curplan', 'turnoClase']);

                    return $c->nombreParaListado();
                })
                ->filter()
                ->implode(', ');
        }

        return $act->alumnos
            ->map(function ($rel) {
                $l = $rel->legajo;
                if (! $l instanceof Legajo) {
                    return null;
                }

                return trim((string) $l->apellido.', '.(string) $l->nombre);
            })
            ->filter()
            ->implode('; ');
    }
}
