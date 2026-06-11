<?php

namespace App\Livewire\Viajes;

use App\Models\SalidaViaje;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\PermisosIaCatalog;
use App\Support\Viajes\SalidaViajeHtmlSanitizer;
use App\Support\Viajes\SalidaViajeTextoPlantilla;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SalidaViajeForm extends Component
{
    public ?int $viajeId = null;

    public string $formTitulo = '';

    public string $formDesde = '';

    public string $formHasta = '';

    public string $formTexto = '';

    public function mount(?int $id = null): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::VIAJES_SALIDAS_EDUCATIVAS), 403);
        MenuSecretariaPerfil::abortSiNoViajesSalidasEducativas();

        $ctx = schoolCtx();
        if ($ctx->idNivel < 1 || $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }

        if ($id !== null) {
            $viaje = SalidaViaje::queryEnContexto()->findOrFail($id);
            $this->viajeId = $id;
            $this->formTitulo = (string) ($viaje->titulo ?? '');
            $this->formDesde = $viaje->desde?->format('Y-m-d') ?? '';
            $this->formHasta = $viaje->hasta?->format('Y-m-d') ?? '';
            $this->formTexto = (string) ($viaje->texto ?? '');
        } else {
            $this->formTexto = SalidaViajeTextoPlantilla::paraNuevoViaje();
        }
    }

    protected function rules(): array
    {
        return [
            'formTitulo' => ['required', 'string', 'max:200'],
            'formDesde' => ['nullable', 'date'],
            'formHasta' => ['nullable', 'date', 'after_or_equal:formDesde'],
            'formTexto' => ['required', 'string', 'max:65000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'formTitulo.required' => 'El título del viaje es obligatorio.',
            'formTexto.required' => 'La descripción del viaje es obligatoria.',
            'formHasta.after_or_equal' => 'La fecha «hasta» no puede ser anterior a «desde».',
        ];
    }

    public function save(): void
    {
        $key = 'salidas-viajes:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('formTitulo', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->formTitulo = trim($this->formTitulo);
        $this->formTexto = SalidaViajeHtmlSanitizer::limpiar($this->formTexto);

        $this->validate();

        $payload = [
            'titulo' => $this->formTitulo,
            'desde' => $this->formDesde !== '' ? $this->formDesde : null,
            'hasta' => $this->formHasta !== '' ? $this->formHasta : null,
            'texto' => $this->formTexto,
        ];

        $ctx = schoolCtx();
        if (Schema::hasColumn('salidasviajes', 'idTerlec')) {
            $payload['idTerlec'] = (int) $ctx->idTerlec;
        }
        if (Schema::hasColumn('salidasviajes', 'idNivel')) {
            $payload['idNivel'] = (int) $ctx->idNivel;
        }

        if ($this->viajeId) {
            $viaje = SalidaViaje::queryEnContexto()->findOrFail($this->viajeId);
            $viaje->update($payload);
            $mensaje = 'Viaje actualizado correctamente.';
        } else {
            SalidaViaje::query()->create($payload);
            $mensaje = 'Viaje creado correctamente.';
        }

        session()->flash('success', $mensaje);

        $this->redirect(route('viajes.salidas'), navigate: true);
    }

    public function render()
    {
        return view('livewire.viajes.salida-viaje-form', [
            'esNuevo' => $this->viajeId === null,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => $this->viajeId ? 'Editar salida educativa' : 'Nueva salida educativa',
        ]);
    }
}
