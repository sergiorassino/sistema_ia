<?php

namespace App\Livewire\Push;

use App\Models\Ento;
use App\Push\DestinatariosRepository;
use App\Push\PushSubscriptionRepository;
use App\Push\WebPushService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EnviarPush extends Component
{
    public string $tipoDestino = 'alumno'; // alumno|curso|colegio

    public string $alumnoSearch = '';

    /** @var list<array{id:int,label:string,dni:?string}> */
    public array $alumnoResults = [];

    public ?int $alumnoId = null;

    public ?string $alumnoLabel = null;

    public ?int $cursoId = null;

    public string $title = '';

    public string $body = '';

    public ?string $url = null;

    public array $preview = [
        'destinatarios' => 0,
        'suscriptos' => 0,
        'no_suscriptos' => 0,
    ];

    public ?array $lastSend = null;

    public function updatedAlumnoSearch(): void
    {
        $ctx = schoolCtx();
        $this->alumnoResults = ($ctx->idNivel && $ctx->idTerlec && trim($this->alumnoSearch) !== '')
            ? DestinatariosRepository::buscarAlumnos((int) $ctx->idNivel, (int) $ctx->idTerlec, $this->alumnoSearch, 20)
            : [];
    }

    public function selectAlumno(int $id, string $label): void
    {
        $this->alumnoId = $id;
        $this->alumnoLabel = $label;
        $this->alumnoSearch = '';
        $this->alumnoResults = [];
    }

    public function clearAlumno(): void
    {
        $this->alumnoId = null;
        $this->alumnoLabel = null;
    }

    private function destinatariosUserKeys(): array
    {
        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        if ($this->tipoDestino === 'alumno') {
            return $this->alumnoId ? [(string) $this->alumnoId] : [];
        }

        if ($this->tipoDestino === 'curso') {
            return ($idNivel > 0 && $idTerlec > 0 && $this->cursoId)
                ? DestinatariosRepository::alumnosPorCurso($idNivel, $idTerlec, (int) $this->cursoId)
                : [];
        }

        if ($this->tipoDestino === 'colegio') {
            return ($idNivel > 0 && $idTerlec > 0)
                ? DestinatariosRepository::alumnosDelColegio($idNivel, $idTerlec)
                : [];
        }

        return [];
    }

    public function previewNow(): void
    {
        $keys = $this->destinatariosUserKeys();
        $subscribed = PushSubscriptionRepository::getSubscribedUserKeys($keys);
        $this->preview = [
            'destinatarios' => count($keys),
            'suscriptos' => count($subscribed),
            'no_suscriptos' => max(0, count($keys) - count($subscribed)),
        ];
    }

    private function rulesForSend(): array
    {
        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        return [
            'tipoDestino' => ['required', Rule::in(['alumno', 'curso', 'colegio'])],
            'alumnoId' => [Rule::requiredIf($this->tipoDestino === 'alumno'), 'nullable', 'integer'],
            'cursoId' => [Rule::requiredIf($this->tipoDestino === 'curso'), 'nullable', 'integer'],
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:'.WebPushService::MAX_MENSAJE_CARACTERES],
            'url' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function send(): void
    {
        $key = 'push:send:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('title', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate($this->rulesForSend());

        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);
        $insti = Ento::query()->where('idNivel', $idNivel)->value('insti');
        $nombreColegio = is_string($insti) && trim($insti) !== '' ? trim($insti) : 'Notificación';

        $keys = $this->destinatariosUserKeys();
        if (empty($keys)) {
            $this->addError('tipoDestino', 'No hay destinatarios para enviar.');

            return;
        }

        $url = $this->url !== null && trim($this->url) !== '' ? trim($this->url) : url('/');

        $result = WebPushService::sendToUsers($keys, $this->title, $this->body, $url, $nombreColegio);

        $this->lastSend = [
            'sent' => $result['ok'],
            'failed' => $result['fail'],
            'failed_user_keys' => $result['failed_user_keys'] ?? [],
        ];

        $this->previewNow();
        session()->flash('success', 'Notificación enviada.');
    }

    public function render()
    {
        $ctx = schoolCtx();
        $cursos = ($ctx->idNivel && $ctx->idTerlec)
            ? DestinatariosRepository::cursosDelContexto((int) $ctx->idNivel, (int) $ctx->idTerlec)
            : [];

        return view('livewire.push.enviar-push', [
            'cursos' => $cursos,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Enviar notificación push']);
    }
}
