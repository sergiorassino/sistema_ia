<?php

namespace App\Livewire\Abm\Legajos;

use App\Models\Familia;
use App\Models\Legajo;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class LegajoFamilia extends Component
{
    /** Valor legacy en `legajos.idFamilias` cuando no hay grupo familiar real. */
    public const ID_FAMILIA_SIN_ASIGNAR = 1;

    public ?int $idLegajo = null;

    public ?Legajo $legajo = null;

    public ?Familia $familia = null;

    /** @var Collection<int, Legajo> */
    public Collection $hermanos;

    public string $filtroFamilias = '';

    public int|string $asignarFamiliaId = '';

    public bool $showModalFamilia = false;

    public bool $showConfirmDeleteFamilia = false;

    public bool $showConfirmQuitarAsignacion = false;

    public ?int $editFamiliaId = null;

    public string $familiaApellido = '';

    public string $familiaResponsable = '';

    public string $familiaEmail = '';

    public string $deleteFamiliaInfo = '';

    public ?int $deleteFamiliaId = null;

    public function mount(): void
    {
        $id = ContextoEstudianteSesion::legajo(ContextoEstudianteSesion::LEGAJO_ABM);
        abort_unless($id, 404, 'Debe abrir un legajo de estudiante para consultar la familia.');

        $this->idLegajo = $id;
        $this->hermanos = collect();
        $this->cargarDatos();
    }

    public function cargarDatos(): void
    {
        $this->legajo = Legajo::query()->findOrFail($this->idLegajo);
        $this->familia = null;
        $this->hermanos = collect();

        if (! $this->tieneFamiliaAsignada($this->legajo)) {
            return;
        }

        $idFamilia = (int) $this->legajo->idFamilias;
        $this->familia = Familia::query()->find($idFamilia);

        if (! $this->familia) {
            return;
        }

        $this->hermanos = Legajo::query()
            ->where('idFamilias', $idFamilia)
            ->whereKeyNot($this->idLegajo)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get(['id', 'apellido', 'nombre', 'dni', 'legajo']);
    }

    private function usuarioPuedeEditar(): bool
    {
        return tienePermiso(PermisosIaCatalog::LEGAJOS_FAMILIAS_GESTION);
    }

    /** Mínimo de caracteres para listar coincidencias (evita traer cientos de filas sin criterio). */
    private const MIN_CHARS_BUSQUEDA_FAMILIA = 2;

    public function updatedFiltroFamilias(): void
    {
        $this->asignarFamiliaId = '';
    }

    public function seleccionarFamiliaParaAsignar(int $id): void
    {
        $this->requireModificar();
        abort_unless($id > 0, 422);
        Familia::query()->findOrFail($id);
        $this->asignarFamiliaId = $id;
    }

    private function familiasParaAsignarQuery(): Collection
    {
        $filtro = trim($this->filtroFamilias);
        if (mb_strlen($filtro) < self::MIN_CHARS_BUSQUEDA_FAMILIA) {
            return collect();
        }

        return Familia::query()
            ->where(function ($sub) use ($filtro) {
                $sub->where('apellido', 'like', "%{$filtro}%")
                    ->orWhere('responsable', 'like', "%{$filtro}%")
                    ->orWhere('email', 'like', "%{$filtro}%");
            })
            ->orderBy('apellido')
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'apellido', 'responsable', 'email']);
    }

    private function familiaSeleccionadaParaAsignar(): ?Familia
    {
        $id = (int) $this->asignarFamiliaId;

        return $id > 0 ? Familia::query()->find($id) : null;
    }

    protected function rulesFamilia(): array
    {
        return [
            'familiaApellido' => ['required', 'string', 'max:50'],
            'familiaResponsable' => ['nullable', 'string', 'max:50'],
            'familiaEmail' => ['nullable', 'email', 'max:100'],
        ];
    }

    protected function messagesFamilia(): array
    {
        return [
            'familiaApellido.required' => 'El apellido de la familia es obligatorio.',
            'familiaApellido.max' => 'El apellido no puede superar los 50 caracteres.',
            'familiaResponsable.max' => 'El responsable no puede superar los 50 caracteres.',
            'familiaEmail.email' => 'El email no es válido.',
            'familiaEmail.max' => 'El email no puede superar los 100 caracteres.',
        ];
    }

    private function requireModificar(): void
    {
        abort_unless($this->usuarioPuedeEditar(), 403, 'Sin permiso para modificar familias o asignaciones.');
    }

    public function openCreateFamilia(): void
    {
        $this->requireModificar();
        $this->reset('editFamiliaId', 'familiaApellido', 'familiaResponsable', 'familiaEmail');
        $this->resetValidation();
        $this->showModalFamilia = true;
    }

    public function openEditFamilia(): void
    {
        $this->requireModificar();
        abort_unless($this->familia, 404, 'No hay familia asignada para editar.');

        $this->editFamiliaId = (int) $this->familia->id;
        $this->familiaApellido = (string) ($this->familia->apellido ?? '');
        $this->familiaResponsable = (string) ($this->familia->responsable ?? '');
        $this->familiaEmail = (string) ($this->familia->email ?? '');
        $this->resetValidation();
        $this->showModalFamilia = true;
    }

    public function saveFamilia(bool $asignarAlEstudiante = false): void
    {
        $this->requireModificar();

        $key = 'legajo-familia:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('familiaApellido', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate($this->rulesFamilia(), $this->messagesFamilia());

        $apellido = trim($this->familiaApellido);
        $responsable = trim($this->familiaResponsable);
        $email = trim($this->familiaEmail);
        $payload = [
            'apellido' => $apellido,
            'responsable' => $responsable !== '' ? $responsable : null,
            'email' => $email !== '' ? $email : null,
        ];

        if ($this->editFamiliaId) {
            $f = Familia::query()->findOrFail($this->editFamiliaId);
            $f->update($payload);
            session()->flash('success', 'Familia actualizada.');
        } else {
            $f = Familia::query()->create($payload);
            session()->flash('success', 'Familia creada.');

            if ($asignarAlEstudiante) {
                $this->asignarFamiliaAlEstudiante((int) $f->id, false);
                session()->flash('success', 'Familia creada y asignada al estudiante.');
                $this->showModalFamilia = false;
                $this->reset('editFamiliaId', 'familiaApellido', 'familiaResponsable', 'familiaEmail');
                $this->cargarDatos();

                return;
            }
        }

        $this->showModalFamilia = false;
        $this->reset('editFamiliaId', 'familiaApellido', 'familiaResponsable', 'familiaEmail');
        $this->cargarDatos();
    }

    public function saveFamiliaYAsignar(): void
    {
        $this->saveFamilia(true);
    }

    public function asignarFamiliaSeleccionada(): void
    {
        $this->requireModificar();

        $id = (int) $this->asignarFamiliaId;
        abort_unless($id > 0, 422, 'Seleccione una familia.');

        Familia::query()->findOrFail($id);
        $this->asignarFamiliaAlEstudiante($id);
    }

    private function asignarFamiliaAlEstudiante(int $idFamilia, bool $flash = true): void
    {
        Legajo::query()->whereKey($this->idLegajo)->update(['idFamilias' => $idFamilia]);

        if ($flash) {
            session()->flash('success', 'Familia asignada al estudiante.');
        }

        $this->asignarFamiliaId = '';
        $this->filtroFamilias = '';
        $this->cargarDatos();
    }

    public function confirmQuitarAsignacion(): void
    {
        $this->requireModificar();
        abort_unless($this->tieneFamiliaAsignada($this->legajo), 422, 'El estudiante no tiene una familia asignada.');
        $this->showConfirmQuitarAsignacion = true;
    }

    public function quitarAsignacion(): void
    {
        $this->requireModificar();

        $key = 'legajo-familia:quitar:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            session()->flash('warning', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirmQuitarAsignacion = false;

            return;
        }
        RateLimiter::hit($key, 60);

        Legajo::query()->whereKey($this->idLegajo)->update([
            'idFamilias' => self::ID_FAMILIA_SIN_ASIGNAR,
        ]);

        session()->flash('success', 'Se quitó la asignación de familia de este estudiante.');
        $this->showConfirmQuitarAsignacion = false;
        $this->cargarDatos();
    }

    public function confirmDeleteFamilia(): void
    {
        $this->requireModificar();
        abort_unless($this->familia, 404, 'No hay familia asignada.');

        $id = (int) $this->familia->id;
        $countLegajos = DB::table('legajos')->where('idFamilias', $id)->count();

        if ($countLegajos > 0) {
            $this->deleteFamiliaId = null;
            $this->deleteFamiliaInfo = "No se puede eliminar la familia «{$this->familia->apellido}» porque tiene {$countLegajos} estudiante(s) vinculado(s). Quite las asignaciones o reasígnelos antes.";
        } else {
            $this->deleteFamiliaId = $id;
            $this->deleteFamiliaInfo = "¿Confirma eliminar la familia «{$this->familia->apellido}»? Esta acción no se puede deshacer.";
        }

        $this->showConfirmDeleteFamilia = true;
    }

    public function deleteFamilia(): void
    {
        $this->requireModificar();

        if (! $this->deleteFamiliaId) {
            $this->showConfirmDeleteFamilia = false;

            return;
        }

        $key = 'legajo-familia:delete:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('warning', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showConfirmDeleteFamilia = false;
            $this->reset('deleteFamiliaId', 'deleteFamiliaInfo');

            return;
        }
        RateLimiter::hit($key, 60);

        $f = Familia::query()->findOrFail($this->deleteFamiliaId);
        $nombre = (string) $f->apellido;
        $f->delete();

        session()->flash('success', "Familia «{$nombre}» eliminada.");
        $this->showConfirmDeleteFamilia = false;
        $this->reset('deleteFamiliaId', 'deleteFamiliaInfo');
        $this->cargarDatos();
    }

    public static function tieneFamiliaAsignada(?Legajo $legajo): bool
    {
        if (! $legajo) {
            return false;
        }

        $id = (int) ($legajo->idFamilias ?? 0);

        return $id > 0 && $id !== self::ID_FAMILIA_SIN_ASIGNAR;
    }

    public function render()
    {
        return view('livewire.abm.legajos.familia', [
            'puedeEditar' => $this->usuarioPuedeEditar(),
            'familiasParaAsignar' => $this->familiasParaAsignarQuery(),
            'familiaSeleccionada' => $this->familiaSeleccionadaParaAsignar(),
            'minCharsBusquedaFamilia' => self::MIN_CHARS_BUSQUEDA_FAMILIA,
            'tieneAsignacion' => self::tieneFamiliaAsignada($this->legajo),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Familia del estudiante']);
    }
}
