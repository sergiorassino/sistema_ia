<?php

namespace App\Support\Seguimiento;

use App\Comunicaciones\Adapters\MailAdapter;
use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\ComHilo;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;
use App\Models\Gabinete;
use App\Models\Matricula;
use App\Models\Profesor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comparte un registro de gabinete con el equipo (docentes, directivos, preceptores, gabinete)
 * vía comunicación institucional.
 *
 * Un hilo por rol receptor (canales). Medios: push y, si el canal lo tiene, email.
 * WhatsApp no se usa en este envío automático.
 */
final class NotificarDocentesGabinete
{
    public const INTRO = 'Sres. Profesores: compartimos con ustedes el trabajo de seguimiento que se está haciendo con el Gabinete de Orientación.';

    /**
     * @param  list<int>  $idsProfesores
     * @return array{
     *     ok: bool,
     *     cantidad: int,
     *     omitidos_canal: int,
     *     medios_usados: list<string>,
     *     email_incluido: bool,
     *     email_estado: ?string,
     *     email_motivo: ?string,
     *     email_destino: ?string,
     *     email_mailer: ?string,
     *     email_smtp_user: ?string,
     *     motivo_fallo: ?string
     * }
     */
    public static function despachar(Gabinete $registro, Matricula $matriculaContexto, array $idsProfesores): array
    {
        $fallo = static fn (?string $motivo = null): array => [
            'ok'              => false,
            'cantidad'        => 0,
            'omitidos_canal'  => 0,
            'medios_usados'   => [],
            'email_incluido'  => false,
            'email_estado'    => null,
            'email_motivo'    => null,
            'email_destino'   => null,
            'email_mailer'    => null,
            'email_smtp_user' => null,
            'motivo_fallo'    => $motivo,
        ];

        if (! Schema::hasTable('com_hilos')) {
            return $fallo('El módulo de comunicación institucional no está disponible en esta base.');
        }

        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);
        $idEmisor = (int) ($ctx->idProfesor ?? 0);

        if ($idNivel < 1 || $idTerlec < 1 || $idEmisor < 1) {
            return $fallo('Sin contexto de nivel, ciclo o usuario.');
        }

        if ((int) ($matriculaContexto->idNivel ?? 0) !== $idNivel
            || (int) ($matriculaContexto->idTerlec ?? 0) !== $idTerlec) {
            return $fallo('La matrícula no pertenece al contexto actual.');
        }

        $idLegajoAlumno = (int) ($matriculaContexto->idLegajos ?? 0);
        $idLegajoReg = (int) ($registro->matricula?->idLegajos ?? 0);
        if ($idLegajoAlumno < 1 || $idLegajoReg !== $idLegajoAlumno) {
            return $fallo('El registro no corresponde a este alumno.');
        }

        $emisor = Profesor::query()->with('tipo')->find($idEmisor);
        if ($emisor === null) {
            return $fallo('No se encontró el usuario remitente.');
        }

        $ids = ComunicacionesRepository::filtrarIdsProfesoresDelNivel($idsProfesores, $idNivel);
        $ids = array_values(array_diff($ids, [$idEmisor]));
        $ids = self::filtrarIdsDestinatarios($ids, $idNivel);
        if ($ids === []) {
            return $fallo('Seleccione al menos un destinatario del nivel, distinto de usted.');
        }

        $rolEmisor = CanalesPolicy::claveRolDeProfesor($emisor);
        $profesores = Profesor::query()->with('tipo')->whereIn('id', $ids)->get()->keyBy('id');
        $porClave = [];
        foreach ($ids as $idProf) {
            $p = $profesores->get($idProf);
            if (! $p instanceof Profesor) {
                continue;
            }
            $porClave[CanalesPolicy::claveRolDeProfesor($p)][] = $idProf;
        }

        if ($porClave === []) {
            return $fallo('No se encontraron destinatarios vigentes.');
        }

        $matriculaContexto->loadMissing(['legajo', 'curso']);
        $registro->loadMissing(['tipo', 'matricula.curso', 'matricula.terlec', 'matricula.legajo']);

        $alumno = trim(($matriculaContexto->legajo?->apellido ?? '').', '.($matriculaContexto->legajo?->nombre ?? ''));
        $cursoReg = $registro->matricula?->curso?->nombreParaListado()
            ?? $matriculaContexto->curso?->nombreParaListado()
            ?? '';
        $ciclo = trim((string) ($registro->matricula?->terlec?->ano ?? ''));
        $contenido = self::textoMensaje([
            'alumno'      => $alumno,
            'curso'       => $cursoReg,
            'ciclo'       => $ciclo,
            'fecha'       => $registro->fecha ? $registro->fecha->format('d/m/Y') : '',
            'tipo'        => (string) ($registro->tipo?->tipo ?? ''),
            'solicitud'   => (string) ($registro->solipor ?? ''),
            'motivo'      => (string) ($registro->motivo ?? ''),
            'asistentes'  => (string) ($registro->asistentes ?? ''),
            'conclusion'  => (string) ($registro->conclusion ?? ''),
        ]);
        $asunto = self::asunto($alumno);
        $idCurso = (int) ($matriculaContexto->idCursos ?? 0) ?: null;

        $enviados = 0;
        $omitidosCanal = 0;
        $mediosUsados = [];
        $hilos = [];

        foreach ($porClave as $claveRec => $idsGrupo) {
            if (! CanalesPolicy::puedeIniciar($rolEmisor, $claveRec, $idNivel)) {
                $omitidosCanal += count($idsGrupo);

                continue;
            }
            $medios = self::mediosEfectivos(CanalesPolicy::mediosPermitidos($rolEmisor, $claveRec, $idNivel));
            if ($medios === []) {
                $omitidosCanal += count($idsGrupo);

                continue;
            }

            $hilos[] = ComunicacionesRepository::crearHiloConMensaje([
                'asunto'                      => $asunto,
                'contenido'                   => $contenido,
                'scope'                       => 'docentes',
                'id_legajos'                  => [],
                'id_curso'                    => $idCurso,
                'cursos_envio'                => null,
                'id_nivel'                    => $idNivel,
                'id_terlec'                   => $idTerlec,
                'creado_por_tipo'             => 'profesor',
                'creado_por_id'               => $idEmisor,
                'creado_por_rol'              => $rolEmisor,
                'rol_receptor'                => $claveRec,
                'vinculo_familiar'            => null,
                'nombre_remitente'            => $emisor->nombre_completo,
                'dni_remitente'               => (string) ($emisor->dni ?? ''),
                'destinatarios_profesores'    => $idsGrupo,
                'familia_puede_responder'     => false,
                'docentes_permite_respuestas' => true,
            ], $medios);

            $enviados += count($idsGrupo);
            foreach ($medios as $m) {
                $mediosUsados[] = $m;
            }
        }

        if ($enviados < 1) {
            return $fallo('No hay canales de comunicación habilitados hacia los destinatarios seleccionados. Revise Parametrización → Canales de Comunicación.');
        }

        $mediosUsados = array_values(array_unique($mediosUsados));
        $emailIncluido = in_array('email', $mediosUsados, true);
        $resumenEmail = $emailIncluido
            ? self::resumenEnvioEmail($hilos)
            : ['estado' => null, 'motivo' => null, 'destino' => null];

        return [
            'ok'              => true,
            'cantidad'        => $enviados,
            'omitidos_canal'  => $omitidosCanal,
            'medios_usados'   => $mediosUsados,
            'email_incluido'  => $emailIncluido,
            'email_estado'    => $resumenEmail['estado'],
            'email_motivo'    => $resumenEmail['motivo'],
            'email_destino'   => $resumenEmail['destino'],
            'email_mailer'    => (string) config('mail.default'),
            'email_smtp_user' => trim((string) config('mail.mailers.smtp.username', '')),
            'motivo_fallo'    => null,
        ];
    }

    /**
     * @param  array{
     *     alumno?: string,
     *     curso?: string,
     *     ciclo?: string,
     *     fecha?: string,
     *     tipo?: string,
     *     solicitud?: string,
     *     motivo?: string,
     *     asistentes?: string,
     *     conclusion?: string
     * }  $datos
     */
    public static function textoMensaje(array $datos): string
    {
        $vacio = static fn (?string $v): string => ($t = trim((string) $v)) !== '' ? $t : '—';

        $lineas = [
            self::INTRO,
            '',
            'Alumno/a: '.$vacio($datos['alumno'] ?? ''),
            'Curso: '.$vacio($datos['curso'] ?? ''),
        ];
        $ciclo = trim((string) ($datos['ciclo'] ?? ''));
        if ($ciclo !== '') {
            $lineas[] = 'Ciclo lectivo: '.$ciclo;
        }
        $lineas[] = 'Fecha: '.$vacio($datos['fecha'] ?? '');
        $lineas[] = 'Tipo: '.$vacio($datos['tipo'] ?? '');
        $lineas[] = 'Solicitud: '.$vacio($datos['solicitud'] ?? '');
        $lineas[] = 'Motivo: '.$vacio($datos['motivo'] ?? '');
        $lineas[] = 'Asistentes: '.$vacio($datos['asistentes'] ?? '');
        $lineas[] = 'Conclusión: '.$vacio($datos['conclusion'] ?? '');

        return implode("\n", $lineas);
    }

    public static function asunto(string $alumno): string
    {
        $alumno = trim($alumno);
        if ($alumno === '' || $alumno === ',') {
            return 'Seguimiento de gabinete';
        }

        return 'Seguimiento de gabinete — '.$alumno;
    }

    /**
     * Push y email si el canal los habilita. WhatsApp no se dispara en este flujo.
     *
     * @param  list<string>  $mediosCanal
     * @return list<string>
     */
    public static function mediosEfectivos(array $mediosCanal): array
    {
        return array_values(array_filter(
            array_values(array_unique($mediosCanal)),
            static fn (string $m): bool => $m === 'push' || $m === 'email'
        ));
    }

    /**
     * Docentes (profesor/ATP/DOE), directivos, preceptores y gabinete de orientación.
     */
    public static function esDestinatarioCompartir(?string $tipo): bool
    {
        $t = mb_strtolower(trim((string) $tipo));
        if ($t === '' || str_contains($t, 'sin rol')) {
            return false;
        }
        if (str_contains($t, 'profesor') || str_contains($t, 'atp') || str_contains($t, 'doe')) {
            return true;
        }
        if (str_contains($t, 'direct') || str_contains($t, 'secret')) {
            return true;
        }
        if (str_contains($t, 'preceptor')) {
            return true;
        }
        if (str_contains($t, 'gabinete') || str_contains($t, 'orientaci') || str_contains($t, 'psicopedagog')) {
            return true;
        }

        return false;
    }

    /**
     * Personal del nivel elegible para compartir un registro de gabinete.
     *
     * @return list<array{id:int,label:string,dni:?string,rol:string,rol_label:string}>
     */
    public static function personalParaSelector(int $idNivel, string $filtro = '', int $limit = 800, ?int $excluirId = null): array
    {
        $out = [];
        foreach (ComunicacionesRepository::profesoresDelNivelParaSelectorTodos($idNivel, $filtro, 2000, $excluirId) as $row) {
            if (! self::esDestinatarioCompartir((string) ($row['rol_label'] ?? ''))) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Profesores asignados en `ppc` a materias del curso (ciclo/nivel).
     *
     * @return list<int>
     */
    public static function idsDocentesPpcDelCurso(int $idCurso, int $idNivel, int $idTerlec): array
    {
        if ($idCurso < 1 || $idNivel < 1 || $idTerlec < 1) {
            return [];
        }
        if (! Schema::hasTable('ppc') || ! Schema::hasTable('materias')) {
            return [];
        }

        $ids = DB::table('ppc as ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('m.idCursos', $idCurso)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->pluck('ppc.idProfesor');

        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function filtrarIdsDestinatarios(array $ids, int $idNivel): array
    {
        if ($ids === []) {
            return [];
        }

        $permitidos = [];
        foreach (self::personalParaSelector($idNivel, '', 2000) as $row) {
            $permitidos[(int) $row['id']] = true;
        }

        return array_values(array_filter($ids, static fn (int $id): bool => isset($permitidos[$id])));
    }

    /**
     * @param  list<ComHilo>  $hilos
     * @return array{estado: ?string, motivo: ?string, destino: ?string}
     */
    private static function resumenEnvioEmail(array $hilos): array
    {
        $idsMensaje = [];
        foreach ($hilos as $hilo) {
            $id = (int) ($hilo->cuerpo_inicial_id ?? 0);
            if ($id > 0) {
                $idsMensaje[] = $id;
            }
        }
        if ($idsMensaje === []) {
            return ['estado' => null, 'motivo' => 'Sin mensaje inicial del hilo.', 'destino' => null];
        }

        $destinatarios = ComMensajeDestinatario::query()
            ->whereIn('id_mensaje', $idsMensaje)
            ->get();

        if ($destinatarios->isEmpty()) {
            return ['estado' => null, 'motivo' => 'Sin destinatarios del mensaje.', 'destino' => null];
        }

        $idsDest = $destinatarios->pluck('id')->all();
        $envios = ComMensajeEnvio::query()
            ->where('medio', 'email')
            ->whereIn('id_mensaje_destinatario', $idsDest)
            ->orderByDesc('id')
            ->get();

        if ($envios->isEmpty()) {
            return ['estado' => null, 'motivo' => 'No se registró intento de correo.', 'destino' => null];
        }

        $estados = $envios->pluck('estado')->map(static fn ($e) => (string) $e)->all();
        $estado = 'enviado';
        if (in_array('fallido', $estados, true)) {
            $estado = 'fallido';
        } elseif (in_array('pendiente', $estados, true) && ! in_array('enviado', $estados, true)) {
            $estado = 'pendiente';
        } elseif (in_array('no_aplicable', $estados, true) && ! in_array('enviado', $estados, true)) {
            $estado = 'no_aplicable';
        } elseif (in_array('enviado', $estados, true)) {
            $estado = 'enviado';
        } else {
            $estado = (string) ($envios->first()?->estado ?? '');
        }

        $envioRef = $envios->firstWhere('estado', $estado) ?? $envios->first();
        $motivo = $envioRef !== null ? trim((string) ($envioRef->motivo ?? '')) : '';
        $dest = $envioRef !== null
            ? ($destinatarios->firstWhere('id', (int) $envioRef->id_mensaje_destinatario) ?? $destinatarios->first())
            : $destinatarios->first();

        $destino = null;
        if ($dest instanceof ComMensajeDestinatario) {
            $destino = MailAdapter::resolverDireccionCorreo($dest);
            $destino = $destino !== null ? mb_strtolower(trim($destino)) : null;
            if ($destino === '') {
                $destino = null;
            }
        }

        return [
            'estado'  => $estado !== '' ? $estado : null,
            'motivo'  => $motivo !== '' ? $motivo : null,
            'destino' => $destino,
        ];
    }
}
