<?php

namespace App\Livewire\Cuotas;

use App\Models\CuotasBeca;
use App\Support\Cuotas\GeneracionCuotaEstudianteService;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * ABM de tipos de beca (`cuotasbecas`) — perfil Administración.
 */
class TiposBecaIndex extends Component
{
    public bool $showModal = false;

    public bool $showConfirm = false;

    public ?int $editId = null;

    public ?int $deleteId = null;

    public string $deleteInfo = '';

    public string $nombreBeca = '';

    public string $porcentaje = '';

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeTiposBeca(), 403, 'Sin permiso para tipos de beca.');
    }

    protected function rules(): array
    {
        $id = $this->editId;

        return [
            'nombreBeca' => [
                'required',
                'string',
                'max:80',
                'unique:cuotasbecas,nombreBeca'.($id ? ",{$id},id" : ''),
            ],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombreBeca.required' => 'El nombre del tipo de beca es obligatorio.',
            'nombreBeca.max' => 'El nombre no puede superar los 80 caracteres.',
            'nombreBeca.unique' => 'Ya existe un tipo de beca con ese nombre.',
            'porcentaje.required' => 'El porcentaje de descuento es obligatorio.',
            'porcentaje.numeric' => 'El porcentaje debe ser un número.',
            'porcentaje.min' => 'El porcentaje no puede ser negativo.',
            'porcentaje.max' => 'El porcentaje no puede superar 100.',
        ];
    }

    public function openCreate(): void
    {
        $this->reset('nombreBeca', 'porcentaje', 'editId');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $beca = CuotasBeca::query()->findOrFail($id);
        $this->editId = $id;
        $this->nombreBeca = (string) ($beca->nombreBeca ?? '');
        $this->porcentaje = $this->formatearPorcentaje((float) ($beca->porcentaje ?? 0));
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $key = 'cuotas-becas:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('nombreBeca', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $nombre = trim($this->nombreBeca);
        $porcentaje = round((float) str_replace(',', '.', trim($this->porcentaje)), 2);

        if ($this->editId) {
            CuotasBeca::query()->findOrFail($this->editId)->update([
                'nombreBeca' => $nombre,
                'porcentaje' => $porcentaje,
            ]);
            session()->flash('success', "Tipo de beca \"{$nombre}\" actualizado.");
        } else {
            CuotasBeca::query()->create([
                'nombreBeca' => $nombre,
                'porcentaje' => $porcentaje,
            ]);
            session()->flash('success', "Tipo de beca \"{$nombre}\" creado.");
        }

        $this->showModal = false;
        $this->reset('nombreBeca', 'porcentaje', 'editId');
    }

    public function confirmDelete(int $id): void
    {
        if ($id === GeneracionCuotaEstudianteService::BECA_CUOTA_ENTERA) {
            $this->deleteId = null;
            $this->deleteInfo = 'No se puede eliminar el registro del sistema «Cuota entera» (C/E).';
            $this->showConfirm = true;

            return;
        }

        $beca = CuotasBeca::query()->findOrFail($id);
        $nombre = trim((string) ($beca->nombreBeca ?? ''));

        $countGeneradas = DB::table('cuotasgeneradas')->where('idCuotasbecas', $id)->count();
        $countMatricula = DB::table('matricula')->where('idCuotasbecas', $id)->count();
        $total = $countGeneradas + $countMatricula;

        if ($total > 0) {
            $detalle = collect([
                $countGeneradas ? "{$countGeneradas} cuotas generadas" : null,
                $countMatricula ? "{$countMatricula} matrículas" : null,
            ])->filter()->implode(', ');

            $this->deleteId = null;
            $this->deleteInfo = "No se puede eliminar \"{$nombre}\" porque está en uso: {$detalle}.";
        } else {
            $this->deleteId = $id;
            $this->deleteInfo = "¿Confirma eliminar el tipo de beca \"{$nombre}\"?";
        }

        $this->showConfirm = true;
    }

    public function delete(): void
    {
        $key = 'cuotas-becas:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirm = false;
            $this->reset('deleteId', 'deleteInfo');

            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId && $this->deleteId !== GeneracionCuotaEstudianteService::BECA_CUOTA_ENTERA) {
            $beca = CuotasBeca::query()->findOrFail($this->deleteId);
            $nombre = trim((string) ($beca->nombreBeca ?? ''));
            $beca->delete();
            session()->flash('success', "Tipo de beca \"{$nombre}\" eliminado.");
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $becas = CuotasBeca::query()->orderBy('porcentaje')->orderBy('nombreBeca')->get();

        return view('livewire.cuotas.tipos-beca-index', compact('becas'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Tipos de Beca']);
    }

    private function formatearPorcentaje(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
