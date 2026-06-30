<?php

namespace App\Livewire\EmailsMasivos;

use App\Models\EmailEnviado;
use App\Models\Profesor;
use App\Push\DestinatariosRepository;
use App\Support\EmailsMasivos\EmailsMasivosConfig;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class EmailsMasivosIndex extends Component
{
    use WithPagination;

    public string $filtroAsunto = '';

    public string $periodo = 'actual';

    public ?int $idProfesorFiltro = null;

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

    public function render()
    {
        $ctx = schoolCtx();

        $query = EmailEnviado::query()
            ->where('idNiveles', (int) $ctx->idNivel);

        if ($this->periodo === 'actual') {
            $query->where('idTerlec', (int) $ctx->idTerlec);
        }

        if ($this->idProfesorFiltro) {
            $query->where('idProfesores', $this->idProfesorFiltro);
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

        return view('livewire.emails-masivos.emails-masivos-index', [
            'campanias' => $campanias,
            'profesores' => $profesores,
            'maxDestinatarios' => EmailsMasivosConfig::maxDestinatariosPorEnvio(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Correo masivo a estudiantes']);
    }
}
