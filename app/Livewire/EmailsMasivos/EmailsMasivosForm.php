<?php

namespace App\Livewire\EmailsMasivos;

use App\Models\EmailEscrito;
use App\Support\EmailsMasivos\DestinatariosEmailsMasivos;
use App\Support\EmailsMasivos\EmailsMasivosAdjuntosStorage;
use App\Support\EmailsMasivos\EmailsMasivosConfig;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;

class EmailsMasivosForm extends Component
{
    use WithFileUploads;

    public ?int $idEscrito = null;

    public string $asunto = '';

    public string $contenidoHtml = '';

    /** @var list<string> nombres ya guardados en attached */
    public array $adjuntosExistentes = [];

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $adjuntosNuevos = [];

    public function mount(?int $id = null): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_ESTUDIANTES), 403);
        abort_unless(Schema::hasTable('emails_escritos'), 404);

        if ($id !== null && $id > 0) {
            $escrito = EmailEscrito::query()->find($id);
            abort_if($escrito === null, 404);

            $this->idEscrito = (int) $escrito->id;
            $this->asunto = (string) $escrito->subject;
            $this->contenidoHtml = (string) $escrito->text;
            $this->adjuntosExistentes = DestinatariosEmailsMasivos::parseAttached((string) ($escrito->attached ?? ''));
        }
    }

    public function quitarAdjuntoExistente(int $index): void
    {
        unset($this->adjuntosExistentes[$index]);
        $this->adjuntosExistentes = array_values($this->adjuntosExistentes);
    }

    public function quitarAdjuntoNuevo(int $index): void
    {
        unset($this->adjuntosNuevos[$index]);
        $this->adjuntosNuevos = array_values($this->adjuntosNuevos);
    }

    public function guardar(): void
    {
        $this->validate([
            'asunto' => ['required', 'string', 'max:254'],
            'contenidoHtml' => ['required', 'string', 'min:3'],
        ], [
            'asunto.required' => 'Ingrese el asunto.',
            'contenidoHtml.required' => 'Redacte el cuerpo del mensaje.',
        ]);

        $nombresNuevos = [];
        foreach ($this->adjuntosNuevos as $file) {
            if ($file->getSize() > EmailsMasivosConfig::adjuntoMaxBytes()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'adjuntosNuevos' => 'Cada adjunto debe pesar como máximo '
                        . (int) config('emails_masivos.adjunto_max_mb', 10) . ' MB.',
                ]);
            }
            $nombresNuevos[] = EmailsMasivosAdjuntosStorage::nombreSeguro($file->getClientOriginalName());
        }

        $todosNombres = array_merge($this->adjuntosExistentes, $nombresNuevos);
        if ($err = EmailsMasivosAdjuntosStorage::validarListaNombres($todosNombres)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'adjuntosNuevos' => $err,
            ]);
        }

        $attachedStr = implode('|', $todosNombres);
        $ctx = schoolCtx();
        $idTerlec = (int) $ctx->idTerlec;

        if ($this->idEscrito) {
            $escrito = EmailEscrito::query()->find($this->idEscrito);
            abort_if($escrito === null, 404);
            $escrito->update([
                'subject' => mb_substr(trim($this->asunto), 0, 254),
                'text' => $this->contenidoHtml,
                'attached' => $attachedStr,
            ]);
        } else {
            $escrito = EmailEscrito::query()->create([
                'subject' => mb_substr(trim($this->asunto), 0, 254),
                'text' => $this->contenidoHtml,
                'attached' => $attachedStr,
            ]);
            $this->idEscrito = (int) $escrito->id;
        }

        if ($this->adjuntosNuevos !== []) {
            EmailsMasivosAdjuntosStorage::guardarParaCampana(
                $idTerlec,
                (int) $escrito->id,
                $this->adjuntosNuevos,
            );
            $this->adjuntosNuevos = [];
            $this->adjuntosExistentes = $todosNombres;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Mensaje guardado.');
        $this->redirectRoute('emails-masivos.index', navigate: true);
    }

    public function render()
    {
        $titulo = $this->idEscrito ? 'Editar mensaje' : 'Nuevo mensaje';

        return view('livewire.emails-masivos.emails-masivos-form', [
            'titulo' => $titulo,
        ])->layout(layoutMenuStaff(), ['pageTitle' => $titulo]);
    }
}
