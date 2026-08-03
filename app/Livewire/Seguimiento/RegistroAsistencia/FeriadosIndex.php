<?php

namespace App\Livewire\Seguimiento\RegistroAsistencia;

use App\Models\Feriado;
use App\Support\Database\PersistenciaColumnas;
use App\Support\PermisosIaCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class FeriadosIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public bool $showModal = false;

    public ?int $editId = null;

    public string $fechaFeriado = '';

    public string $nombre = '';

    public string $busqueda = '';

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::REGISTRO_ASISTENCIA), 403, 'Sin permiso para feriados.');
        abort_unless(Schema::hasTable('feriados'), 404, 'La tabla feriados no está disponible en este colegio.');
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'fechaFeriado' => ['required', 'date'],
            'nombre' => ['required', 'string', 'max:120'],
        ];
    }

    protected function messages(): array
    {
        return [
            'fechaFeriado.required' => 'La fecha es obligatoria.',
            'fechaFeriado.date' => 'La fecha no es válida.',
            'nombre.required' => 'El nombre del feriado es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 120 caracteres.',
        ];
    }

    public function openCreate(): void
    {
        $this->reset('fechaFeriado', 'nombre', 'editId');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $f = $this->feriadoDelNivel($id);
        if ($f === null) {
            return;
        }
        $this->editId = $id;
        $this->fechaFeriado = $f->fechaFeriado?->format('Y-m-d') ?? '';
        $this->nombre = trim((string) $f->nombre);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $key = 'feriados:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->addError('nombre', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $idNivel = (int) schoolCtx()->idNivel;
        $payload = [
            'fechaFeriado' => $this->fechaFeriado,
            'nombre' => trim($this->nombre),
            'idNivel' => $idNivel,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('feriados', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $this->addError(
                'nombre',
                PersistenciaColumnas::mensajeColumnasInexistentes('feriados', $preparado['columnas_con_valor_sin_columna'])
            );

            return;
        }

        try {
            if ($this->editId) {
                $f = $this->feriadoDelNivel($this->editId);
                if ($f === null) {
                    $this->addError('nombre', 'El feriado no pertenece a este nivel.');

                    return;
                }
                $f->update($preparado['payload']);
                $idGuardado = (int) $f->id;
                $msg = 'Feriado actualizado.';
            } else {
                $f = Feriado::create($preparado['payload']);
                $idGuardado = (int) $f->id;
                $msg = 'Feriado creado.';
            }
        } catch (QueryException $e) {
            $this->addError(
                'nombre',
                PersistenciaColumnas::mensajeDesdeQueryException($e) ?? 'No se pudo guardar el feriado.'
            );

            return;
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'feriados',
            ['id' => $idGuardado],
            [
                'fechaFeriado' => $payload['fechaFeriado'],
                'nombre' => $payload['nombre'],
                'idNivel' => $idNivel,
            ]
        );
        if ($noPersistidas !== []) {
            $this->addError(
                'nombre',
                PersistenciaColumnas::mensajeColumnasNoPersistidas('feriados', $noPersistidas)
            );

            return;
        }

        $this->showModal = false;
        $this->reset('fechaFeriado', 'nombre', 'editId');
        $this->dispatch('se-swal-exito', mensaje: $msg);
    }

    public function eliminar(int $id): void
    {
        $key = 'feriados:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $f = $this->feriadoDelNivel($id);
        if ($f === null) {
            return;
        }

        $f->delete();
        $this->dispatch('se-swal-exito', mensaje: 'Feriado eliminado.');
    }

    private function feriadoDelNivel(int $id): ?Feriado
    {
        return Feriado::query()
            ->where('id', $id)
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->first();
    }

    public function render()
    {
        $q = Feriado::query()
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->orderByDesc('fechaFeriado')
            ->orderBy('nombre');

        $b = trim($this->busqueda);
        if ($b !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $b).'%';
            $q->where('nombre', 'like', $like);
        }

        $registros = $q->paginate(self::POR_PAGINA);

        return view('livewire.seguimiento.registro-asistencia.feriados-index', [
            'registros' => $registros,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Feriados']);
    }
}
