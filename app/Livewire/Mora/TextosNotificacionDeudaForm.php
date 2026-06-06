<?php

namespace App\Livewire\Mora;

use App\Models\DatoVario;
use App\Support\Mora\PermisosMora;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Edición de textos de la notificación de deuda (`datosvarios`).
 */
class TextosNotificacionDeudaForm extends Component
{
    public string $textoInicNotDeuda = '';

    public string $textoFinalNotDeuda = '';

    public string $textoFinalNotDeudaBec = '';

    public function mount(): void
    {
        abort_unless(PermisosMora::puedeGestionMorosos(), 403);

        $registro = DatoVario::singleton();

        $this->textoInicNotDeuda = (string) ($registro->textoInicNotDeuda ?? '');
        $this->textoFinalNotDeuda = (string) ($registro->textoFinalNotDeuda ?? '');
        $this->textoFinalNotDeudaBec = (string) ($registro->textoFinalNotDeudaBec ?? '');
    }

    protected function rules(): array
    {
        return [
            'textoInicNotDeuda' => ['nullable', 'string', 'max:65000'],
            'textoFinalNotDeuda' => ['nullable', 'string', 'max:65000'],
            'textoFinalNotDeudaBec' => ['nullable', 'string', 'max:65000'],
        ];
    }

    public function guardar(): void
    {
        abort_unless(PermisosMora::puedeGestionMorosos(), 403);

        $key = 'mora:textos-notificacion:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('textoInicNotDeuda', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $registro = DatoVario::singleton();
        $registro->fill([
            'textoInicNotDeuda' => $this->textoInicNotDeuda,
            'textoFinalNotDeuda' => $this->textoFinalNotDeuda,
            'textoFinalNotDeudaBec' => $this->textoFinalNotDeudaBec,
        ]);
        $registro->save();

        session()->flash('success', 'Textos de la notificación actualizados correctamente.');
    }

    public function render()
    {
        return view('livewire.mora.textos-notificacion-deuda-form')
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Textos de la Notificación de Deuda']);
    }
}
