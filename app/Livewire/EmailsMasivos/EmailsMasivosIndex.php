<?php

namespace App\Livewire\EmailsMasivos;

use App\Models\EmailEscrito;
use App\Support\EmailsMasivos\EmailsMasivosAdjuntosStorage;
use App\Support\EmailsMasivos\EmailsMasivosEscritoEnvios;
use App\Support\EmailsMasivos\EmailsMasivosConfig;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class EmailsMasivosIndex extends Component
{
    use WithPagination;

    public string $filtroAsunto = '';

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_ESTUDIANTES), 403);
    }

    public function updatedFiltroAsunto(): void
    {
        $this->resetPage();
    }

    public function confirmarEliminar(int $id): void
    {
        if (! tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_BORRAR)) {
            $this->dispatch('se-swal-error', mensaje: 'No tiene permiso para borrar mensajes de correo masivo.');

            return;
        }

        $escrito = EmailEscrito::query()->find($id);
        if ($escrito === null) {
            $this->dispatch('se-swal-error', mensaje: 'El mensaje ya no existe.');

            return;
        }

        $ctx = schoolCtx();
        if (EmailsMasivosEscritoEnvios::escritoTieneEnvios($escrito)) {
            $this->dispatch(
                'se-swal-error',
                mensaje: 'Este mensaje ya fue enviado. Elimine primero todos los envíos registrados en el historial.',
            );

            return;
        }

        $dir = 'emails-masivos/' . tenantSlug() . '/' . (int) $ctx->idTerlec . '/' . $id;
        Storage::disk(EmailsMasivosAdjuntosStorage::DISK)->deleteDirectory($dir);

        $escrito->delete();
        $this->dispatch('se-swal-exito', mensaje: 'Mensaje eliminado.');
    }

    public function render()
    {
        $query = EmailEscrito::query()->orderByDesc('id');

        $asunto = trim($this->filtroAsunto);
        if ($asunto !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $asunto) . '%';
            $query->where('subject', 'like', $like);
        }

        $escritos = $query
            ->select([
                'id',
                'subject',
                'attached',
                DB::raw('SUBSTRING(`text`, 1, 220) AS text'),
            ])
            ->paginate(25);

        $filasEnvio = EmailEscrito::query()
            ->whereIn('id', $escritos->pluck('id'))
            ->get(['id', 'subject', 'text', 'attached']);

        $escritosConEnvios = EmailsMasivosEscritoEnvios::idsConEnvios($filasEnvio);

        return view('livewire.emails-masivos.emails-masivos-index', [
            'escritos' => $escritos,
            'escritosConEnvios' => $escritosConEnvios,
            'maxDestinatarios' => EmailsMasivosConfig::maxDestinatariosPorEnvio(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Correo masivo a estudiantes']);
    }
}
