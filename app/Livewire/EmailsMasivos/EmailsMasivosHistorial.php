<?php

namespace App\Livewire\EmailsMasivos;

use App\Models\EmailEnviado;
use App\Models\Profesor;
use App\Support\EmailsMasivos\EmailsMasivosEscritoEnvios;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class EmailsMasivosHistorial extends Component
{
    use WithPagination;

    public string $filtroAsunto = '';

    public string $periodo = 'actual';

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_ESTUDIANTES), 403);
        abort_unless(Schema::hasTable('emails_enviados'), 404);
    }

    public function updatedFiltroAsunto(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodo(): void
    {
        $this->resetPage();
    }

    public function confirmarEliminarCampana(int $idSeed): void
    {
        if (! tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_BORRAR)) {
            $this->dispatch('se-swal-error', mensaje: 'No tiene permiso para borrar envíos del historial de correo masivo.');

            return;
        }

        $ctx = schoolCtx();
        $seed = EmailsMasivosEscritoEnvios::seedEnAlcance($idSeed, (int) $ctx->idNivel);
        if ($seed === null) {
            $this->dispatch('se-swal-error', mensaje: 'El envío ya no existe.');

            return;
        }

        $total = EmailsMasivosEscritoEnvios::eliminarCampana($seed, (int) $ctx->idNivel);
        if ($total <= 0) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontraron registros de envío para eliminar.');

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Envío eliminado del historial (' . $total . ' registro(s)).');
    }

    public function render()
    {
        $ctx = schoolCtx();

        $query = EmailEnviado::query()
            ->where('idNiveles', (int) $ctx->idNivel);

        if ($this->periodo === 'actual') {
            $query->where('idTerlec', (int) $ctx->idTerlec);
        }

        $asunto = trim($this->filtroAsunto);
        if ($asunto !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $asunto) . '%';
            $query->where('subject', 'like', $like);
        }

        $campanias = $query
            ->selectRaw('MIN(id) as id_seed, idProfesores, subject, fechhora, COUNT(*) as total_envios, MAX(attached) as attached')
            ->groupBy('idProfesores', 'subject', 'fechhora', 'texto')
            ->orderByDesc('fechhora')
            ->orderByDesc('id_seed')
            ->paginate(20);

        $profesoresIds = collect($campanias->items())->pluck('idProfesores')->unique()->filter()->all();
        $profesores = $profesoresIds !== []
            ? Profesor::query()->whereIn('id', $profesoresIds)->get(['id', 'apellido', 'nombre'])->keyBy('id')
            : collect();

        return view('livewire.emails-masivos.emails-masivos-historial', [
            'campanias' => $campanias,
            'profesores' => $profesores,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Historial envíos correo masivo']);
    }
}
