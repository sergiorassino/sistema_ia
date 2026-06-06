<?php

namespace App\Livewire\Alumnos\Comunicaciones;

use App\Models\Legajo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComAuditoriaLogger;
use App\Comunicaciones\ComunicacionesFamiliaSession;
use App\Comunicaciones\ComunicacionesRepository;
use App\Livewire\Concerns\DetalleLecturaDestinatariosModal;
use App\Models\ComHilo;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;

class HiloShowFamilia extends Component
{
    use DetalleLecturaDestinatariosModal;

    public int $idHilo;

    public string $vinculo   = '';
    public string $respuesta = '';
    public bool $mostrarFormRespuesta = false;

    public bool $modalBorrarAbierto = false;

    /** @var int|null */
    public ?int $modalBorrarMensajeId = null;

    public bool $modalBorrarEliminaHiloCompleto = false;

    public function mount(): void
    {
        $id = ComunicacionesFamiliaSession::idHiloActivo();
        abort_if($id <= 0, 404);

        $this->idHilo = $id;
        $this->assertHiloAccesible();

        $ctx = studentCtx();
        $idsMensajes = ComunicacionesRepository::marcarLeidoHiloFamilia($id, (int) $ctx->idLegajo);
        if ($idsMensajes !== []) {
            ComAuditoriaLogger::registrarMarcarLeidoHilo(
                $id,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
                $idsMensajes,
                idLegajo: (int) $ctx->idLegajo
            );
        }
    }

    public function abrirDetalleLectura(int $idMensaje): void
    {
        $this->assertHiloAccesible();

        $ctx = studentCtx();
        $this->mostrarDetalleLectura(
            ComunicacionesRepository::payloadDetalleLecturaMensajeFamilia(
                $idMensaje,
                $this->idHilo,
                (int) $ctx->idLegajo,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec
            )
        );
    }

    public function marcarMensajeNoLeido(int $idMensaje): void
    {
        $this->assertHiloAccesible();

        $key = 'com:unread:fam:' . (auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->addError('marcarNoLeido', 'Demasiadas acciones. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $ctx = studentCtx();
        $ok  = ComunicacionesRepository::marcarNoLeidoMensajeFamilia(
            $idMensaje,
            $this->idHilo,
            (int) $ctx->idLegajo,
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
                idLegajo: (int) $ctx->idLegajo
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
        $ctx       = studentCtx();
        $idLegajo  = (int) $ctx->idLegajo;

        if ((int) $msg->id_hilo !== (int) $this->idHilo) {
            return ['puede' => false, 'motivo' => 'Mensaje fuera del hilo.'];
        }

        if ($msg->tipo_remitente !== 'familia' || (int) ($msg->id_legajo ?? 0) !== $idLegajo) {
            return ['puede' => false, 'motivo' => 'Solo puede borrar sus propios mensajes.'];
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

        return ['puede' => true, 'motivo' => ''];
    }

    public function abrirModalBorrar(int $idMensaje): void
    {
        $this->assertHiloAccesible();

        $ctx = studentCtx();

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

        $this->modalBorrarMensajeId           = (int) $msg->id;
        $this->modalBorrarEliminaHiloCompleto = ((int) ($hilo->cuerpo_inicial_id ?? 0) === (int) $msg->id) && $cant === 1;
        $this->modalBorrarAbierto             = true;
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
        $this->assertHiloAccesible();

        $key = 'com:del:fam:' . (auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiadas acciones. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, 60);

        $ctx       = studentCtx();
        $idLegajo  = (int) $ctx->idLegajo;

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

        abort_unless(
            $msg->tipo_remitente === 'familia' && (int) ($msg->id_legajo ?? 0) === $idLegajo,
            403,
            'No autorizado.'
        );

        $borrarHilo = false;
        if ((int) $hilo->cuerpo_inicial_id === (int) $msg->id) {
            $cant = ComMensaje::query()
                ->where('id_hilo', (int) $hilo->id)
                ->count();
            abort_if($cant > 1, 403, 'No se puede borrar el mensaje inicial si ya hay mensajes posteriores.');
            $borrarHilo = true;
        }

        abort_if((int) $msg->respuestas_count > 0, 403, 'No se puede borrar un mensaje que tiene respuestas.');

        ComAuditoriaLogger::registrarBorrado($hilo, $msg, $borrarHilo, idLegajo: $idLegajo);

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
            session()->flash('success', 'Comunicado eliminado.');
            $this->redirectRoute('alumnos.comunicaciones.index');
            return;
        }

        session()->flash('success', 'Mensaje borrado.');
    }

    public function responder(): void
    {
        $this->assertHiloAccesible();

        $key = 'com:resp:fam:' . (auth('alumno')->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, config('comunicaciones.rate_limit_max', 20))) {
            $this->addError('respuesta', 'Demasiados envíos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, config('comunicaciones.rate_limit_decay', 60));

        $this->validate([
            'vinculo'   => 'required|in:madre,padre,tutor,resp_admin,otro',
            'respuesta' => 'required|string|max:' . config('comunicaciones.max_contenido', 2000),
        ]);

        $ctx      = studentCtx();
        $idLegajo = (int) $ctx->idLegajo;
        $legajo   = Legajo::find($idLegajo);

        $hilo        = ComHilo::findOrFail($this->idHilo);
        $rolReceptor = (string) ($hilo->creado_por_rol ?? 'preceptor');

        if (! $hilo->familiaPuedeEnviarRespuestas()) {
            $this->addError('respuesta', 'Este comunicado es solo informativo; no admite respuestas.');
            return;
        }

        $idNivelHilo = (int) ($hilo->id_nivel ?? $ctx->idNivel);

        if (! CanalesPolicy::puedeResponder('familia', $rolReceptor, $idNivelHilo)) {
            $this->addError('respuesta', 'No puede responder a este tipo de comunicado.');
            return;
        }

        $medios = CanalesPolicy::mediosPermitidos('familia', $rolReceptor, $idNivelHilo);

        [$nombreSnap, $dniSnap] = $this->snapshotDatosFamiliares($legajo, $this->vinculo);

        ComunicacionesRepository::responder(
            idHilo: $this->idHilo,
            tipoRemitente: 'familia',
            idRemitente: $idLegajo,
            rolRemitente: 'familia',
            contenido: $this->respuesta,
            mediosCanal: $medios,
            vinculo: $this->vinculo,
            nombreSnapshot: $nombreSnap,
            dniSnapshot: $dniSnap
        );

        $this->respuesta            = '';
        $this->mostrarFormRespuesta = false;
        session()->flash('success', 'Respuesta enviada.');
    }

    private function snapshotDatosFamiliares(?Legajo $legajo, string $vinculo): array
    {
        if ($legajo === null) {
            return ['Familiar', ''];
        }
        return match ($vinculo) {
            'madre'      => [trim((string) $legajo->nombremad), (string) ($legajo->dnimad ?? '')],
            'padre'      => [trim((string) $legajo->nombrepad), (string) ($legajo->dnipad ?? '')],
            'tutor'      => [trim((string) $legajo->nombretut), (string) ($legajo->dnitut ?? '')],
            'resp_admin' => [trim((string) $legajo->respAdmiNom), (string) ($legajo->respAdmiDni ?? '')],
            default      => ['Familiar', ''],
        };
    }

    private function assertHiloAccesible(): void
    {
        $ctx = studentCtx();
        abort_unless(
            ComunicacionesRepository::familiaPuedeVerHilo(
                (int) $this->idHilo,
                (int) $ctx->idLegajo,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec
            ),
            404
        );
    }

    public function render()
    {
        $this->assertHiloAccesible();

        $ctx  = studentCtx();
        $hilo = ComHilo::with([
            'mensajes' => function ($q) {
                $q->withCount('respuestas')
                    ->with(['destinatarios.envios', 'hilo'])
                    ->orderBy('created_at');
            },
        ])->findOrFail($this->idHilo);

        $rolHilo   = (string) ($hilo->creado_por_rol ?? 'preceptor');
        $idNivelHilo = (int) ($hilo->id_nivel ?? $ctx->idNivel);
        $puedeResp = $hilo->familiaPuedeEnviarRespuestas()
            && CanalesPolicy::puedeResponder('familia', $rolHilo, $idNivelHilo);

        $mensajesPorDia = $hilo->mensajes->groupBy(fn ($m) => $m->created_at?->toDateString());

        $paraCompleto = null;
        if ($hilo->creado_por_tipo === 'familia') {
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
            $paraCompleto = count($nombres) ? implode(' · ', $nombres) : '—';
        }

        return view('comunicaciones::livewire.alumnos.comunicaciones.hilo-show-familia', [
            'hilo'             => $hilo,
            'mensajesPorDia'   => $mensajesPorDia,
            'puedeResponder'   => $puedeResp,
            'maxContenido'     => config('comunicaciones.max_contenido', 2000),
            'paraCompleto'     => $paraCompleto,
            'idLegajoSesion'   => (int) $ctx->idLegajo,
        ])->layout('layouts.alumno', ['pageTitle' => $hilo->asunto]);
    }
}
