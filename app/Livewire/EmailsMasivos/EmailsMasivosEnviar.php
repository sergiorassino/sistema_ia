<?php

namespace App\Livewire\EmailsMasivos;

use App\Livewire\EmailsMasivos\Concerns\SeleccionDestinatariosCorreoMasivo;
use App\Models\EmailEscrito;
use App\Support\EmailsMasivos\DestinatariosEmailsMasivos;
use App\Support\EmailsMasivos\EmailsMasivosConfig;
use App\Support\EmailsMasivos\EnvioCorreoMasivo;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class EmailsMasivosEnviar extends Component
{
    use SeleccionDestinatariosCorreoMasivo;

    public int $idEscrito;

    public string $asunto = '';

    public string $contenidoHtml = '';

    public string $attached = '';

    /** @var list<string> */
    public array $resultadoDestinatarios = [];

    public function mount(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_ESTUDIANTES), 403);
        abort_unless(Schema::hasTable('emails_escritos') && Schema::hasTable('emails_enviados'), 404);

        $escrito = EmailEscrito::query()->find($id);
        abort_if($escrito === null, 404);

        $this->idEscrito = (int) $escrito->id;
        $this->asunto = (string) $escrito->subject;
        $this->contenidoHtml = (string) $escrito->text;
        $this->attached = (string) ($escrito->attached ?? '');
    }

    public function enviar(): void
    {
        $this->validarSeleccionDestinatarios();
        $destinatarios = $this->calcularDestinatariosEnvio();
        $n = count($destinatarios);

        if ($n > EmailsMasivosConfig::maxDestinatariosPorEnvio()) {
            $this->dispatch('se-swal-error', mensaje: 'La selección supera el máximo de '
                . EmailsMasivosConfig::maxDestinatariosPorEnvio()
                . ' destinatarios. Reduzca el alcance antes de enviar.');

            return;
        }

        if ($n === 0) {
            $this->dispatch('se-swal-error', mensaje: 'No hay destinatarios con email para enviar.');

            return;
        }

        $ctx = schoolCtx();
        $profesor = $ctx->profesor();
        abort_if($profesor === null, 403);

        $escrito = EmailEscrito::query()->find($this->idEscrito);
        abort_if($escrito === null, 404);

        $resultado = EnvioCorreoMasivo::ejecutarDesdeEscrito(
            $escrito,
            $profesor,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $destinatarios,
        );

        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->resultadoDestinatarios = $resultado['destinatarios'];
        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
    }

    public function render()
    {
        $ctx = schoolCtx();
        $profesor = $ctx->profesor();
        $credencialesOk = $profesor !== null
            && trim((string) ($profesor->email ?? '')) !== ''
            && trim((string) ($profesor->emailPass ?? '')) !== '';

        $destinatarios = $this->calcularDestinatariosEnvio();
        $nEnvios = count($destinatarios);

        return view('livewire.emails-masivos.emails-masivos-enviar', [
            'credencialesOk' => $credencialesOk,
            'profesor' => $profesor,
            'destinatariosPreview' => $destinatarios,
            'nEnvios' => $nEnvios,
            'maxEnvio' => EmailsMasivosConfig::maxDestinatariosPorEnvio(),
            'avisoEnvio' => EmailsMasivosConfig::maxDestinatariosAviso(),
            'superaTope' => $nEnvios > EmailsMasivosConfig::maxDestinatariosPorEnvio(),
            'superaAviso' => $nEnvios > EmailsMasivosConfig::maxDestinatariosAviso()
                && $nEnvios <= EmailsMasivosConfig::maxDestinatariosPorEnvio(),
            'simulado' => EmailsMasivosConfig::simulado(),
            'lineasPorCurso' => collect($this->lineasAlumnos)
                ->map(fn (array $linea, int $index) => $linea + ['_i' => $index])
                ->groupBy('idCurso'),
            'adjuntosLista' => DestinatariosEmailsMasivos::parseAttached($this->attached),
            'envioCompletado' => $this->resultadoDestinatarios !== [],
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Enviar correo masivo']);
    }
}
