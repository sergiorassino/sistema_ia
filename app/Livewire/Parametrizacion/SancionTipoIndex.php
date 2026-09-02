<?php

namespace App\Livewire\Parametrizacion;

use App\Models\Profesor;
use App\Models\SancionTipo;
use App\Support\Database\PersistenciaColumnas;
use App\Support\PermisosConfiguracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SancionTipoIndex extends Component
{
    public bool $showModal = false;

    public ?int $editId = null;

    public string $tipo = '';

    public string $textoNotifPadres = '';

    public int|string $idProfesorNotif = '';

    public bool $refuerzoMail = false;

    public bool $permiteNotifPadres = true;

    public bool $enResumenComunicado = false;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosConfiguracion::SANCION_TIPOS_CONFIG), 403, 'Sin permiso para administrar tipos de sanción.');
        abort_unless(Schema::hasTable('sanciontipo'), 404, 'La tabla sanciontipo no está disponible en este colegio.');
    }

    protected function rules(): array
    {
        return [
            'tipo'             => ['required', 'string', 'max:120'],
            'textoNotifPadres' => ['nullable', 'string', 'max:2000'],
            'idProfesorNotif'  => ['nullable', 'integer', 'min:1'],
            'refuerzoMail'         => ['boolean'],
            'permiteNotifPadres'   => ['boolean'],
            'enResumenComunicado'  => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'tipo.required' => 'El nombre del tipo es obligatorio.',
            'tipo.max'      => 'El nombre no puede superar los 120 caracteres.',
        ];
    }

    public function openEdit(int $id): void
    {
        $st = SancionTipo::query()->findOrFail($id);

        $this->editId             = $id;
        $this->tipo               = (string) $st->tipo;
        $this->textoNotifPadres   = (string) ($st->textoNotifPadres ?? '');
        $this->idProfesorNotif    = $st->idProfesorNotif ? (string) $st->idProfesorNotif : '';
        $this->refuerzoMail          = (bool) ($st->refuerzoMail ?? false);
        $this->permiteNotifPadres    = isset($st->permiteNotifPadres) ? (bool) $st->permiteNotifPadres : true;
        $this->enResumenComunicado   = (bool) ($st->enResumenComunicado ?? false);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $key = 'sancion-tipo:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->addError('tipo', 'Demasiados intentos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $payload = [
            'tipo'             => trim($this->tipo),
            'textoNotifPadres' => trim($this->textoNotifPadres) !== '' ? trim($this->textoNotifPadres) : null,
            'idProfesorNotif'  => ($this->idProfesorNotif !== '' && (int) $this->idProfesorNotif > 0)
                                    ? (int) $this->idProfesorNotif
                                    : null,
            'refuerzoMail'       => $this->refuerzoMail ? 1 : 0,
            'permiteNotifPadres' => $this->permiteNotifPadres ? 1 : 0,
        ];

        if (Schema::hasColumn('sanciontipo', 'enResumenComunicado')) {
            $payload['enResumenComunicado'] = $this->enResumenComunicado ? 1 : 0;
        } elseif ($this->enResumenComunicado) {
            $payload['enResumenComunicado'] = 1;
        }

        $preparado = PersistenciaColumnas::prepararPayload('sanciontipo', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $this->addError(
                'tipo',
                PersistenciaColumnas::mensajeColumnasInexistentes('sanciontipo', $preparado['columnas_con_valor_sin_columna'])
            );
            return;
        }

        if (! $this->editId) {
            $this->addError('tipo', 'Solo se puede editar un tipo existente.');
            return;
        }

        try {
            $st = SancionTipo::query()->findOrFail($this->editId);
            $st->update($preparado['payload']);
            $idGuardado = (int) $st->id;
            $msg = 'Tipo de sanción actualizado.';
        } catch (QueryException $e) {
            $this->addError(
                'tipo',
                PersistenciaColumnas::mensajeDesdeQueryException($e) ?? 'No se pudo guardar el tipo de sanción.'
            );
            return;
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'sanciontipo',
            ['id' => $idGuardado],
            array_filter($payload, fn ($v) => $v !== null)
        );
        if ($noPersistidas !== []) {
            $this->addError(
                'tipo',
                PersistenciaColumnas::mensajeColumnasNoPersistidas('sanciontipo', $noPersistidas)
            );
            return;
        }

        $this->showModal = false;
        $this->reset('tipo', 'textoNotifPadres', 'idProfesorNotif', 'refuerzoMail', 'permiteNotifPadres', 'enResumenComunicado', 'editId');
        $this->permiteNotifPadres = true; // default al limpiar
        $this->dispatch('se-swal-exito', mensaje: $msg);
    }

    /** @return Collection<int, Profesor> */
    private function profesoresActivos(): Collection
    {
        return Profesor::query()
            ->delNivel((int) schoolCtx()->idNivel)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get(['id', 'apellido', 'nombre']);
    }

    public function render()
    {
        $tipos = SancionTipo::query()
            ->orderBy('tipo')
            ->get();

        $profesores = $this->profesoresActivos();

        return view('livewire.parametrizacion.sancion-tipo-index', [
            'tipos'      => $tipos,
            'profesores' => $profesores,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Tipos de sanción disciplinaria']);
    }
}
