<?php

namespace App\Livewire\EmailsMasivos;

use App\Models\EmailEnviado;
use App\Models\EmailEscrito;
use App\Models\Profesor;
use App\Support\EmailsMasivos\DestinatariosEmailsMasivos;
use App\Support\EmailsMasivos\EmailsMasivosAdjuntosStorage;
use App\Support\EmailsMasivos\EmailsMasivosEscritoEnvios;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class EmailsMasivosCampanaShow extends Component
{
    public int $idSeed;

    public function mount(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_ESTUDIANTES), 403);
        abort_unless(Schema::hasTable('emails_enviados'), 404);

        $this->idSeed = $id;
    }

    public function confirmarEliminarCampana(): void
    {
        if (! tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_BORRAR)) {
            $this->dispatch('se-swal-error', mensaje: 'No tiene permiso para borrar envíos del historial de correo masivo.');

            return;
        }

        $ctx = schoolCtx();
        $seed = EmailsMasivosEscritoEnvios::seedEnAlcance($this->idSeed, (int) $ctx->idNivel);
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
        $this->redirectRoute('emails-masivos.historial', navigate: true);
    }

    public function render()
    {
        $ctx = schoolCtx();
        $seed = EmailEnviado::query()
            ->where('id', $this->idSeed)
            ->where('idNiveles', (int) $ctx->idNivel)
            ->first();

        abort_if($seed === null, 404);

        $envios = EmailEnviado::query()
            ->from('emails_enviados as e')
            ->leftJoin('legajos as l', 'l.id', '=', 'e.idLegajos')
            ->where('e.idProfesores', $seed->idProfesores)
            ->where('e.fechhora', $seed->fechhora)
            ->where('e.subject', $seed->subject)
            ->where('e.texto', $seed->texto)
            ->where('e.idNiveles', (int) $ctx->idNivel)
            ->orderBy('e.mailDestino')
            ->get([
                'e.*',
                DB::raw("TRIM(CONCAT(l.apellido, ', ', l.nombre)) as alumno_label"),
            ]);

        $profesor = Profesor::query()->find($seed->idProfesores);
        $adjuntos = DestinatariosEmailsMasivos::parseAttached((string) ($seed->attached ?? ''));
        $adjuntosLinks = [];
        foreach ($adjuntos as $nombre) {
            $idEscrito = $this->inferirIdEmailEscrito($seed);
            if ($idEscrito > 0) {
                $ref = OpaqueRouteToken::forEmailsMasivosAdjunto((int) $seed->idTerlec, $idEscrito, $nombre);
                $adjuntosLinks[] = [
                    'nombre' => $nombre,
                    'url' => route('emails-masivos.adjunto', ['ref' => $ref]),
                    'disponible' => EmailsMasivosAdjuntosStorage::rutaArchivo((int) $seed->idTerlec, $idEscrito, $nombre) !== null,
                ];
            } else {
                $adjuntosLinks[] = ['nombre' => $nombre, 'url' => null, 'disponible' => false];
            }
        }

        return view('livewire.emails-masivos.emails-masivos-campana-show', [
            'seed' => $seed,
            'envios' => $envios,
            'profesor' => $profesor,
            'adjuntosLinks' => $adjuntosLinks,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Detalle envío correo masivo']);
    }

    private function inferirIdEmailEscrito(EmailEnviado $seed): int
    {
        $match = EmailEscrito::query()
            ->where('subject', $seed->subject)
            ->where('text', $seed->texto)
            ->where('attached', $seed->attached ?? '')
            ->orderByDesc('id')
            ->value('id');

        return (int) ($match ?? 0);
    }
}
