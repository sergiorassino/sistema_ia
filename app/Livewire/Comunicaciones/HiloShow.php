<?php

namespace App\Livewire\Comunicaciones;

use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComAuditoriaLogger;
use App\Comunicaciones\ComunicacionesGestionSession;
use App\Comunicaciones\ComunicacionesRepository;
use App\Livewire\Concerns\DetalleLecturaDestinatariosModal;
use App\Support\ComunicacionesRutasGestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use App\Models\ComCanal;
use App\Models\ComHilo;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;

class HiloShow extends Component
{
    use DetalleLecturaDestinatariosModal;

    public int $idHilo;
    public string $respuesta = '';
    public bool $mostrarFormRespuesta = false;

    public bool $modalBorrarAbierto = false;

    /** @var int|null */
    public ?int $modalBorrarMensajeId = null;

    public bool $modalBorrarEliminaHiloCompleto = false;

    public function mount(): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403, 'Sin permiso para ver comunicaciones.');

        $id = ComunicacionesGestionSession::idHiloActivo();
        abort_if($id <= 0, 404);
        abort_unless(ComunicacionesGestionSession::puedeVerHilo($id), 404);

        $this->idHilo = $id;
        $this->marcarLeido();
    }

    private function marcarLeido(): void
    {
        $ctx = schoolCtx();
        $idsMensajes = ComunicacionesRepository::marcarLeidoHiloProfesor($this->idHilo, (int) $ctx->idProfesor);
        if ($idsMensajes === []) {
            return;
        }

        ComAuditoriaLogger::registrarMarcarLeidoHilo(
            $this->idHilo,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $idsMensajes,
            idProfesor: (int) $ctx->idProfesor
        );
    }

    public function abrirDetalleLectura(int $idMensaje): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403);

        $ctx = schoolCtx();
        $this->mostrarDetalleLectura(
            ComunicacionesRepository::payloadDetalleLecturaMensajeGestion(
                $idMensaje,
                $this->idHilo,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec
            )
        );
    }

    public function marcarMensajeNoLeido(int $idMensaje): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403, 'Sin permiso para ver comunicaciones.');

        $key = 'com:unread:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->addError('marcarNoLeido', 'Demasiadas acciones. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();
        $ok  = ComunicacionesRepository::marcarNoLeidoMensajeProfesor(
            $idMensaje,
            $this->idHilo,
            (int) $ctx->idProfesor,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec
        );

        if (! $ok) {
            $this->addError('marcarNoLeido', 'No se pudo marcar como no leído.');

            return;
        }

        $msg = ComMensaje::query()
            ->where('id', $idMensaje)
            ->where('id_hilo', $this->idHilo)
            ->first();
        if ($msg !== null) {
            ComAuditoriaLogger::registrarMarcarNoLeido(
                $msg,
                $this->idHilo,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
                idProfesor: (int) $ctx->idProfesor
            );
        }

        $this->resetErrorBag('marcarNoLeido');
        session()->now('success', 'Mensaje marcado como no leído.');
    }

    /**
     * @return array{puede:bool,motivo:string}
     */
    public function infoBorradoMensaje(ComMensaje $msg, ?int $cuerpoInicialId = null, ?int $mensajesEnHilo = null): array
    {
        $ctx = schoolCtx();
        $idProf = (int) $ctx->idProfesor;

        if ((int) $msg->id_hilo !== (int) $this->idHilo) {
            return ['puede' => false, 'motivo' => 'Mensaje fuera del hilo.'];
        }

        if ($cuerpoInicialId === null) {
            $cuerpoInicialId = (int) (ComHilo::query()
                ->where('id', (int) $this->idHilo)
                ->where('id_nivel', (int) $ctx->idNivel)
                ->where('id_terlec', (int) $ctx->idTerlec)
                ->value('cuerpo_inicial_id') ?? 0);
        }

        if ($mensajesEnHilo === null) {
            $mensajesEnHilo = (int) ComMensaje::query()
                ->where('id_hilo', (int) $this->idHilo)
                ->count();
        }

        if ($cuerpoInicialId === (int) $msg->id && $mensajesEnHilo > 1) {
            return ['puede' => false, 'motivo' => 'No se puede borrar el mensaje inicial si ya hay mensajes posteriores.'];
        }

        if (property_exists($msg, 'respuestas_count') && (int) $msg->respuestas_count > 0) {
            return ['puede' => false, 'motivo' => 'No se puede borrar un mensaje que tiene respuestas.'];
        }

        $esPropio = $msg->tipo_remitente === 'profesor' && (int) $msg->id_profesor === $idProf;
        if ($esPropio) {
            if (! tienePermiso(6)) {
                return ['puede' => false, 'motivo' => 'Sin permiso para borrar mensajes propios.'];
            }
            return ['puede' => true, 'motivo' => ''];
        }

        if (! tienePermiso(7)) {
            return ['puede' => false, 'motivo' => 'Sin permiso para borrar mensajes ajenos.'];
        }

        return ['puede' => true, 'motivo' => ''];
    }

    public function puedeBorrarMensaje(ComMensaje $msg): bool
    {
        return (bool) ($this->infoBorradoMensaje($msg)['puede'] ?? false);
    }

    public function abrirModalBorrar(int $idMensaje): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403);

        $ctx = schoolCtx();

        $hilo = ComHilo::query()
            ->where('id', (int) $this->idHilo)
            ->where('id_nivel', (int) $ctx->idNivel)
            ->where('id_terlec', (int) $ctx->idTerlec)
            ->first();

        abort_if($hilo === null, 404);

        $msg = ComMensaje::query()
            ->where('id', (int) $idMensaje)
            ->where('id_hilo', (int) $hilo->id)
            ->withCount('respuestas')
            ->first();

        if ($msg === null) {
            $this->addError('modalBorrar', 'No se encontró el mensaje.');
            return;
        }

        $cant = (int) ComMensaje::query()->where('id_hilo', (int) $hilo->id)->count();
        $info = $this->infoBorradoMensaje($msg, (int) ($hilo->cuerpo_inicial_id ?? 0), $cant);
        if (! $info['puede']) {
            $this->addError('modalBorrar', $info['motivo']);
            return;
        }

        $this->resetErrorBag('modalBorrar');

        $this->modalBorrarMensajeId = (int) $msg->id;
        $this->modalBorrarEliminaHiloCompleto = ((int) ($hilo->cuerpo_inicial_id ?? 0) === (int) $msg->id) && $cant === 1;
        $this->modalBorrarAbierto = true;
    }

    public function cerrarModalBorrar(): void
    {
        $this->modalBorrarAbierto = false;
        $this->modalBorrarMensajeId = null;
        $this->modalBorrarEliminaHiloCompleto = false;
        $this->resetErrorBag('modalBorrar');
    }

    public function confirmarModalBorrar(): void
    {
        $id = $this->modalBorrarMensajeId;
        $this->cerrarModalBorrar();

        if ($id === null) {
            return;
        }

        $this->borrarMensaje($id);
    }

    public function borrarMensaje(int $idMensaje): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403);

        $key = 'com:del:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiadas acciones. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();
        $idProf = (int) $ctx->idProfesor;

        $hilo = ComHilo::query()
            ->where('id', (int) $this->idHilo)
            ->where('id_nivel', (int) $ctx->idNivel)
            ->where('id_terlec', (int) $ctx->idTerlec)
            ->first();

        abort_if($hilo === null, 404);

        $msg = ComMensaje::query()
            ->where('id', (int) $idMensaje)
            ->where('id_hilo', (int) $hilo->id)
            ->withCount('respuestas')
            ->first();

        abort_if($msg === null, 404);

        $borrarHilo = false;
        if ((int) $hilo->cuerpo_inicial_id === (int) $msg->id) {
            $cant = ComMensaje::query()
                ->where('id_hilo', (int) $hilo->id)
                ->count();
            abort_if($cant > 1, 403, 'No se puede borrar el mensaje inicial si ya hay mensajes posteriores.');
            $borrarHilo = true;
        }

        abort_if((int) $msg->respuestas_count > 0, 403, 'No se puede borrar un mensaje que tiene respuestas.');

        $esPropio = $msg->tipo_remitente === 'profesor' && (int) $msg->id_profesor === $idProf;
        if ($esPropio) {
            abort_unless(tienePermiso(6), 403, 'Sin permiso para borrar mensajes propios.');
        } else {
            abort_unless(tienePermiso(7), 403, 'Sin permiso para borrar mensajes ajenos.');
        }

        ComAuditoriaLogger::registrarBorrado($hilo, $msg, $borrarHilo, idProfesor: $idProf);

        DB::transaction(function () use ($msg, $hilo, $borrarHilo) {
            if ($borrarHilo) {
                ComHilo::query()
                    ->where('id', (int) $hilo->id)
                    ->delete();
                return;
            }

            $destIds = ComMensajeDestinatario::query()
                ->where('id_mensaje', (int) $msg->id)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();

            if (count($destIds)) {
                ComMensajeEnvio::query()
                    ->whereIn('id_mensaje_destinatario', $destIds)
                    ->delete();
            }

            ComMensajeDestinatario::query()
                ->where('id_mensaje', (int) $msg->id)
                ->delete();

            ComMensaje::query()
                ->where('id', (int) $msg->id)
                ->delete();

            $ultimo = ComMensaje::query()
                ->where('id_hilo', (int) $hilo->id)
                ->max('created_at');

            $hilo->update([
                'ultimo_mensaje_at' => $ultimo ?? $hilo->created_at ?? now(),
            ]);
        });

        if ($borrarHilo) {
            session()->flash('success', 'Hilo eliminado.');
            $this->redirectRoute(ComunicacionesRutasGestion::nombreRuta('index'));
            return;
        }

        session()->flash('success', 'Mensaje borrado.');
    }

    public function responder(): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoBandejaGestion(), 403);

        $key = 'com:resp:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, config('comunicaciones.rate_limit_max', 20))) {
            $this->addError('respuesta', 'Demasiados envíos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, config('comunicaciones.rate_limit_decay', 60));

        $this->validate([
            'respuesta' => 'required|string|max:' . config('comunicaciones.max_contenido', 2000),
        ]);

        $ctx      = schoolCtx();
        $idProf   = (int) $ctx->idProfesor;
        $profesor = $ctx->profesor();

        abort_if($profesor === null, 403);

        $rolEmisor = CanalesPolicy::claveRolDeProfesor($profesor);

        $hiloCtx = ComunicacionesRepository::hiloGestionProfesorEnContexto(
            $this->idHilo,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec
        );

        abort_if($hiloCtx === null, 404);

        if ($hiloCtx->esComunicacionInternaDocentes()) {
            if (! $hiloCtx->docentesDestinatariosPuedenResponder()) {
                $this->addError('respuesta', 'Este comunicado es solo informativo; no admite respuestas en el hilo.');

                return;
            }
            $rolesTargets = ComunicacionesRepository::rolesDestinatariosRespuestaDocenteResuelto((int) $hiloCtx->id, $idProf, $hiloCtx);
            if (! ComunicacionesRepository::puedeResponderVariosRoles($rolEmisor, $rolesTargets, true)) {
                $this->addError('respuesta', 'Su rol no puede responder en este hilo.');

                return;
            }
            $medios = ComunicacionesRepository::mediosPermitidosRespuestaVariosRoles($rolEmisor, $rolesTargets, true);
            if ($medios === []) {
                $medios = array_values(array_intersect(['push', 'email'], ComCanal::mediosDisponibles()));
            }
            if ($medios === []) {
                $medios = ['push'];
            }
        } else {
            $rolReceptor = \App\Support\Comunicaciones\ComCanalRolCatalog::CLAVE_FAMILIA;
            $idNivelHilo = (int) ($hiloCtx->id_nivel ?? $ctx->idNivel);
            if (! CanalesPolicy::puedeResponder($rolEmisor, $rolReceptor, $idNivelHilo)) {
                $this->addError('respuesta', 'Su rol no puede responder a este comunicado.');

                return;
            }
            $medios = CanalesPolicy::mediosPermitidos($rolEmisor, $rolReceptor, $idNivelHilo);
        }

        $nombreProf = trim("{$profesor->apellido}, {$profesor->nombre}");

        $mensajeResp = ComunicacionesRepository::responder(
            idHilo: $this->idHilo,
            tipoRemitente: 'profesor',
            idRemitente: $idProf,
            rolRemitente: $rolEmisor,
            contenido: $this->respuesta,
            mediosCanal: $medios,
            nombreSnapshot: $nombreProf,
            dniSnapshot: (string) ($profesor->dni ?? '')
        );

        $waLinks = ComunicacionesRepository::enlacesWhatsappWaMeDelMensaje((int) $mensajeResp->id);
        if ($waLinks !== []) {
            session()->flash('whatsapp_wa_links', [
                'hilo_id' => (int) $this->idHilo,
                'links'   => $waLinks,
            ]);
        }

        $this->respuesta              = '';
        $this->mostrarFormRespuesta   = false;
        session()->flash('success', 'Respuesta enviada.');
    }

    public function render()
    {
        $ctx    = schoolCtx();
        $hilo   = ComHilo::with([
            'mensajes' => function ($q) {
                $q->withCount('respuestas')
                    ->with(['destinatarios.envios', 'hilo'])
                    ->orderBy('created_at');
            },
        ])->findOrFail($this->idHilo);

        abort_unless(
            ComunicacionesRepository::profesorPuedeVerHilo(
                (int) $hilo->id,
                (int) $ctx->idProfesor,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec
            ) || tienePermiso(8),
            404
        );

        $profesor        = $ctx->profesor();
        $rolEmisor       = $profesor ? CanalesPolicy::claveRolDeProfesor($profesor) : null;
        $esHiloDocentes = $hilo->esComunicacionInternaDocentes();
        $puedeResponder  = false;
        if ($rolEmisor !== null) {
            if ($esHiloDocentes) {
                $rolesTargets = ComunicacionesRepository::rolesDestinatariosRespuestaDocenteResuelto(
                    (int) $hilo->id,
                    (int) $ctx->idProfesor,
                    $hilo
                );
                $puedeResponder = $hilo->docentesDestinatariosPuedenResponder()
                    && ComunicacionesRepository::puedeResponderVariosRoles($rolEmisor, $rolesTargets, true);
            } else {
                $idNivelHilo = (int) ($hilo->id_nivel ?? $ctx->idNivel);
                $puedeResponder = CanalesPolicy::puedeResponder(
                    $rolEmisor,
                    \App\Support\Comunicaciones\ComCanalRolCatalog::CLAVE_FAMILIA,
                    $idNivelHilo
                );
            }
        }

        $mensajesPorDia = $hilo->mensajes->groupBy(fn ($m) => $m->created_at?->toDateString());

        $paraCompleto = null;
        if ($hilo->creado_por_tipo === 'profesor') {
            if ($hilo->esComunicacionInternaDocentes()) {
                $nombres = ComMensajeDestinatario::query()
                    ->where('id_mensaje', (int) $hilo->cuerpo_inicial_id)
                    ->where('tipo_destinatario', 'profesor')
                    ->whereNotNull('nombre_snapshot')
                    ->orderBy('id_profesor')
                    ->pluck('nombre_snapshot')
                    ->map(fn ($s) => trim((string) $s))
                    ->filter(fn ($s) => $s !== '')
                    ->unique()
                    ->values()
                    ->all();
                $paraCompleto = count($nombres) ? implode(' · ', $nombres) : 'Docentes';
            } elseif ($hilo->scope === 'colegio') {
                $paraCompleto = 'Todo el colegio';
            } elseif (in_array($hilo->scope, ['curso', 'varios_cursos'], true)) {
                $labels = [];
                if (is_array($hilo->cursos_envio)) {
                    foreach ($hilo->cursos_envio as $row) {
                        if (is_array($row) && isset($row['label']) && trim((string) $row['label']) !== '') {
                            $labels[] = trim((string) $row['label']);
                        }
                    }
                }
                if (count($labels) === 0 && $hilo->id_curso) {
                    $cursoLabel = DB::table('cursos as c')
                        ->leftJoin('curplan as cp', 'cp.id', '=', 'c.idCurPlan')
                        ->where('c.Id', (int) $hilo->id_curso)
                        ->value(DB::raw("CASE WHEN TRIM(COALESCE(c.cursec, '')) <> '' THEN TRIM(c.cursec) ELSE TRIM(COALESCE(cp.curPlanCurso, 'Curso')) END"));
                    if ($cursoLabel !== null && trim((string) $cursoLabel) !== '') {
                        $labels[] = trim((string) $cursoLabel);
                    }
                }
                $paraCompleto = count($labels) ? implode(' · ', $labels) : 'Cursos';
            } else {
                $nombres = ComMensajeDestinatario::query()
                    ->where('id_mensaje', (int) $hilo->cuerpo_inicial_id)
                    ->where('tipo_destinatario', 'familia')
                    ->whereNotNull('nombre_snapshot')
                    ->orderBy('id_legajo')
                    ->pluck('nombre_snapshot')
                    ->map(fn ($s) => trim((string) $s))
                    ->filter(fn ($s) => $s !== '')
                    ->unique()
                    ->values()
                    ->all();
                $paraCompleto = count($nombres) ? implode(' · ', $nombres) : '—';
            }
        }

        $waFlash          = session('whatsapp_wa_links');
        $whatsappWaBanner = null;
        if (is_array($waFlash)
            && (int) ($waFlash['hilo_id'] ?? 0) === (int) $hilo->id
            && ! empty($waFlash['links'])
            && is_array($waFlash['links'])) {
            $whatsappWaBanner = $waFlash['links'];
        }

        return view('comunicaciones::livewire.comunicaciones.hilo-show', [
            'hilo'               => $hilo,
            'mensajesPorDia'     => $mensajesPorDia,
            'puedeResponder'     => $puedeResponder,
            'maxContenido'       => config('comunicaciones.max_contenido', 2000),
            'paraCompleto'       => $paraCompleto,
            'idProfesorSesion'   => (int) $ctx->idProfesor,
            'whatsappWaBanner'   => $whatsappWaBanner,
        ])->layout(ComunicacionesRutasGestion::layout(), ['pageTitle' => $hilo->asunto]);
    }
}
