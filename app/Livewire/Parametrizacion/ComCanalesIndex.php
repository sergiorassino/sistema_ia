<?php

namespace App\Livewire\Parametrizacion;

use App\Comunicaciones\CanalesPolicy;
use App\Models\ComCanal;
use App\Support\Comunicaciones\ComCanalRolCatalog;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ComCanalesIndex extends Component
{
    public int $idNivel = 0;

    public string $nivelNombre = '';

    // Edición inline de un canal
    public ?int $editandoId = null;
    public bool $editPuedeIniciar   = false;
    public bool $editPuedeResponder = false;
    public array $editMedios        = [];
    public bool $editActivo         = true;

    // Alta de canal
    public bool $mostrandoFormNuevo = false;
    public string $nuevoRolEmisor = '';
    public string $nuevoRolReceptor = '';
    public bool $nuevoPuedeIniciar = false;
    public bool $nuevoPuedeResponder = false;
    public array $nuevoMedios = [];
    public bool $nuevoActivo = true;

    /** Modal confirmar borrado de canal */
    public bool $showConfirmEliminar = false;
    public ?int $eliminarId = null;
    public string $eliminarEtiqueta = '';

    public function mount(): void
    {
        abort_unless(tienePermiso(5), 403, 'Sin permiso para administrar canales de comunicación.');

        $this->idNivel = (int) (schoolCtx()->idNivel ?? 0);
        $this->nivelNombre = schoolCtx()->nivelNombre();
    }

    public function abrirFormNuevo(): void
    {
        $this->cancelarEdicion();
        $this->mostrandoFormNuevo = true;
        $claves = ComCanal::rolesClave();
        $this->nuevoRolEmisor = $claves[0] ?? ComCanalRolCatalog::CLAVE_FAMILIA;
        $this->nuevoRolReceptor = ComCanalRolCatalog::CLAVE_FAMILIA;
        foreach ($claves as $clave) {
            if ($clave !== $this->nuevoRolEmisor) {
                $this->nuevoRolReceptor = $clave;
                break;
            }
        }
        $this->nuevoPuedeIniciar = false;
        $this->nuevoPuedeResponder = false;
        $this->nuevoMedios = ['push', 'email'];
        $this->nuevoActivo = true;
    }

    public function cancelarFormNuevo(): void
    {
        $this->mostrandoFormNuevo = false;
    }

    public function guardarNuevo(): void
    {
        abort_unless(tienePermiso(5), 403);

        if ($this->idNivel <= 0) {
            $this->addError('nuevoRolReceptor', 'Seleccione un nivel activo en el menú de secretaría antes de crear canales.');

            return;
        }

        $roles = ComCanal::rolesClave();

        $this->validate([
            'nuevoRolEmisor'       => ['required', 'string', Rule::in($roles)],
            'nuevoRolReceptor'     => ['required', 'string', Rule::in($roles)],
            'nuevoPuedeIniciar'    => 'boolean',
            'nuevoPuedeResponder'  => 'boolean',
            'nuevoMedios'          => 'array',
            'nuevoMedios.*'        => 'string|in:push,email,whatsapp',
            'nuevoActivo'          => 'boolean',
        ], [], [
            'nuevoRolEmisor'   => 'emisor',
            'nuevoRolReceptor' => 'receptor',
        ]);

        $yaExiste = ComCanal::query()
            ->where('id_nivel', $this->idNivel)
            ->where('rol_emisor', $this->nuevoRolEmisor)
            ->where('rol_receptor', $this->nuevoRolReceptor)
            ->exists();

        if ($yaExiste) {
            $this->addError('nuevoRolReceptor', 'Ya existe un canal para esta combinación en este nivel.');

            return;
        }

        $medios = array_values(array_unique($this->nuevoMedios));

        $canal = new ComCanal([
            'id_nivel'        => $this->idNivel,
            'rol_emisor'      => $this->nuevoRolEmisor,
            'rol_receptor'    => $this->nuevoRolReceptor,
            'puede_iniciar'   => $this->nuevoPuedeIniciar,
            'puede_responder' => $this->nuevoPuedeResponder,
            'activo'          => $this->nuevoActivo,
        ]);
        $canal->medios_permitidos = $medios;
        $canal->created_at = now();
        $canal->updated_at = now();
        $canal->save();

        CanalesPolicy::invalidar($canal->rol_emisor, $canal->rol_receptor, $this->idNivel);

        $this->mostrandoFormNuevo = false;
        session()->flash('success', 'Canal creado correctamente.');
    }

    public function toggleMedioNuevo(string $medio): void
    {
        if (in_array($medio, $this->nuevoMedios, true)) {
            $this->nuevoMedios = array_values(array_filter($this->nuevoMedios, fn ($m) => $m !== $medio));
        } else {
            $this->nuevoMedios[] = $medio;
        }
    }

    public function iniciarEdicion(int $id): void
    {
        $this->cancelarFormNuevo();
        $canal = $this->canalDelNivel($id);
        $this->editandoId         = $id;
        $this->editPuedeIniciar   = $canal->puede_iniciar;
        $this->editPuedeResponder = $canal->puede_responder;
        $this->editMedios         = $canal->medios_permitidos ?? [];
        $this->editActivo         = $canal->activo;
    }

    public function cancelarEdicion(): void
    {
        $this->editandoId = null;
    }

    public function guardar(): void
    {
        abort_unless(tienePermiso(5), 403);

        $this->validate([
            'editPuedeIniciar'   => 'boolean',
            'editPuedeResponder' => 'boolean',
            'editMedios'         => 'array',
            'editMedios.*'       => 'string|in:push,email,whatsapp',
            'editActivo'         => 'boolean',
        ]);

        $canal = $this->canalDelNivel((int) $this->editandoId);
        $canal->puede_iniciar   = $this->editPuedeIniciar;
        $canal->puede_responder = $this->editPuedeResponder;
        $canal->medios_permitidos = array_values(array_unique($this->editMedios));
        $canal->activo          = $this->editActivo;
        $canal->updated_at      = now();
        $canal->save();

        CanalesPolicy::invalidar($canal->rol_emisor, $canal->rol_receptor, $this->idNivel);
        CanalesPolicy::invalidar($canal->rol_receptor, $canal->rol_emisor, $this->idNivel);

        $this->editandoId = null;
        session()->flash('success', 'Canal actualizado correctamente.');
    }

    public function toggleMedio(string $medio): void
    {
        if (in_array($medio, $this->editMedios, true)) {
            $this->editMedios = array_values(array_filter($this->editMedios, fn ($m) => $m !== $medio));
        } else {
            $this->editMedios[] = $medio;
        }
    }

    public function confirmarEliminar(int $id): void
    {
        abort_unless(tienePermiso(5), 403);

        $canal = $this->canalDelNivel($id);
        $etiquetas = ComCanal::etiquetasRoles();
        $de = $etiquetas[$canal->rol_emisor] ?? $canal->rol_emisor;
        $para = $etiquetas[$canal->rol_receptor] ?? $canal->rol_receptor;

        $this->cancelarFormNuevo();
        if ($this->editandoId === $id) {
            $this->cancelarEdicion();
        }

        $this->eliminarId = $id;
        $this->eliminarEtiqueta = "{$de} → {$para}";
        $this->showConfirmEliminar = true;
    }

    public function cerrarConfirmEliminar(): void
    {
        $this->showConfirmEliminar = false;
        $this->eliminarId = null;
        $this->eliminarEtiqueta = '';
    }

    public function eliminarCanal(): void
    {
        abort_unless(tienePermiso(5), 403);

        if ($this->eliminarId === null) {
            $this->cerrarConfirmEliminar();

            return;
        }

        $canal = $this->canalDelNivel($this->eliminarId);
        $rolEmisor = $canal->rol_emisor;
        $rolReceptor = $canal->rol_receptor;

        $canal->delete();

        CanalesPolicy::invalidar($rolEmisor, $rolReceptor, $this->idNivel);
        CanalesPolicy::invalidar($rolReceptor, $rolEmisor, $this->idNivel);

        if ($this->editandoId === $this->eliminarId) {
            $this->cancelarEdicion();
        }

        $this->cerrarConfirmEliminar();
        session()->flash('success', 'Canal eliminado correctamente.');
    }

    public function render()
    {
        $canales = $this->idNivel > 0
            ? ComCanal::query()
                ->where('id_nivel', $this->idNivel)
                ->orderBy('rol_emisor')
                ->orderBy('rol_receptor')
                ->get()
            : collect();

        $etiquetas = ComCanal::etiquetasRoles();
        $mediosDisponibles = ComCanal::mediosDisponibles();

        return view('comunicaciones::livewire.parametrizacion.com-canales-index', [
            'canales'           => $canales,
            'etiquetas'         => $etiquetas,
            'mediosDisponibles' => $mediosDisponibles,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Canales de Comunicación']);
    }

    private function canalDelNivel(int $id): ComCanal
    {
        abort_if($this->idNivel <= 0, 403, 'Sin nivel activo.');

        return ComCanal::query()
            ->where('id', $id)
            ->where('id_nivel', $this->idNivel)
            ->firstOrFail();
    }
}
