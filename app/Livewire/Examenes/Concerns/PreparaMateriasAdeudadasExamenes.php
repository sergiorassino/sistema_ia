<?php

namespace App\Livewire\Examenes\Concerns;

use App\Models\Terlec;
use App\Support\Examenes\MateriasAdeudadasCondicionRecalculo;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

trait PreparaMateriasAdeudadasExamenes
{
    public int $idTurnoPreparacion = 0;

    public int $idTerlecPreparacion = 0;

    /** Confirmado en la visita actual (tras pulsar Recalcular y continuar). */
    public bool $preparacionAceptadaEnEstaVisita = false;

    public ?string $mensajeRecalculo = null;

    private bool $recalculoCondicionesEjecutado = false;

    /** @var list<object{id:int, turno:?string, nturno:string}> */
    public array $turnosDisponibles = [];

    /** @var list<object{id:int, ano:int}> */
    public array $terlecsDisponibles = [];

    protected function moduloMateriasAdeudadas(): string
    {
        return MateriasAdeudadasPreparacion::MODULO_LISTADO;
    }

    /** Si true, no se reutiliza visita_ok al montar (solo entrada nueva desde menú). */
    protected function omitirRestauracionSesionPreparacion(): bool
    {
        return false;
    }

    /** Gestión: los selects de turno/año permanecen visibles aunque ya se haya recalculado. */
    protected function siempreMostrarSelectsPreparacion(): bool
    {
        return false;
    }

    /** Si false, no se preselecciona el primer turno al abrir el formulario. */
    protected function usarValoresMinimosPreparacionPorDefecto(): bool
    {
        return true;
    }

    protected function bootPreparacionMateriasAdeudadas(): void
    {
        $this->turnosDisponibles = MateriasAdeudadasPreparacion::turnosDisponibles();
        $this->terlecsDisponibles = MateriasAdeudadasPreparacion::ciclosLectivosDisponibles();
    }

    protected function asegurarCatalogosPreparacionCargados(): void
    {
        if ($this->turnosDisponibles === [] || $this->terlecsDisponibles === []) {
            $this->bootPreparacionMateriasAdeudadas();
        }
    }

    /** Entrada desde menú: ruta /entrar marca en sesión que debe mostrarse el formulario. */
    protected function configurarEntradaPreparacionMateriasAdeudadas(): void
    {
        $this->bootPreparacionMateriasAdeudadas();

        if ($this->esActualizacionLivewire()) {
            return;
        }

        $modulo = $this->moduloMateriasAdeudadas();

        if (MateriasAdeudadasPreparacion::consumirSolicitudFormularioPreparacion($modulo)) {
            $this->iniciarPreparacionMateriasAdeudadas();

            return;
        }

        if (! $this->omitirRestauracionSesionPreparacion()
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion($modulo)) {
            $this->restaurarPreparacionConfirmadaDesdeSesion();

            return;
        }

        $this->iniciarPreparacionMateriasAdeudadas();
    }

    public function hydratePreparaMateriasAdeudadasExamenes(): void
    {
        $this->asegurarCatalogosPreparacionCargados();

        if (! $this->omitirRestauracionSesionPreparacion()
            && ! $this->preparacionAceptadaEnEstaVisita
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion($this->moduloMateriasAdeudadas())) {
            $this->restaurarPreparacionConfirmadaDesdeSesion();
        }
    }

    protected function esActualizacionLivewire(): bool
    {
        return request()->hasHeader('X-Livewire');
    }

    protected function restaurarPreparacionConfirmadaDesdeSesion(): void
    {
        $modulo = $this->moduloMateriasAdeudadas();
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return;
        }

        $valores = MateriasAdeudadasPreparacion::datosConfirmadosParaRestaurar($ctx, $modulo);
        if ($valores === null) {
            MateriasAdeudadasPreparacion::marcarVisitaSinConfirmar($modulo);

            return;
        }

        $this->idTurnoPreparacion = $valores['idTurno'];
        $this->idTerlecPreparacion = $valores['idTerlec'];
        $this->preparacionAceptadaEnEstaVisita = true;
        $this->recalculoCondicionesEjecutado = MateriasAdeudadasPreparacion::recalculoEjecutadoEnVisita($modulo);
    }

    protected function iniciarPreparacionMateriasAdeudadas(): void
    {
        $modulo = $this->moduloMateriasAdeudadas();
        $ctx = schoolCtx();

        $this->preparacionAceptadaEnEstaVisita = false;
        $this->recalculoCondicionesEjecutado = false;
        $this->mensajeRecalculo = null;

        MateriasAdeudadasPreparacion::marcarVisitaSinConfirmar($modulo);

        $this->idTurnoPreparacion = 0;
        $this->idTerlecPreparacion = (int) ($ctx->idTerlec ?? 0);

        $precarga = MateriasAdeudadasPreparacion::valoresParaPrecargar($ctx, $modulo);
        if ($precarga !== null) {
            $this->idTurnoPreparacion = $precarga['idTurno'];
            $this->idTerlecPreparacion = $precarga['idTerlec'];
        }

        if ($this->usarValoresMinimosPreparacionPorDefecto()) {
            $this->asegurarValoresMinimosPreparacion();
        }
    }

    protected function asegurarValoresMinimosPreparacion(): void
    {
        $this->asegurarCatalogosPreparacionCargados();

        $ctx = schoolCtx();
        if ($this->idTerlecPreparacion < 1 && $ctx->isValid()) {
            $this->idTerlecPreparacion = (int) $ctx->idTerlec;
        }

        if ($this->idTurnoPreparacion < 1 && $this->turnosDisponibles !== []) {
            $this->idTurnoPreparacion = (int) $this->turnosDisponibles[0]->id;
        }
    }

    protected function preparacionConfirmada(): bool
    {
        if (! schoolCtx()->isValid()) {
            return false;
        }

        if ($this->preparacionAceptadaEnEstaVisita) {
            return true;
        }

        if (! $this->omitirRestauracionSesionPreparacion()
            && MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion($this->moduloMateriasAdeudadas())) {
            $this->restaurarPreparacionConfirmadaDesdeSesion();

            return $this->preparacionAceptadaEnEstaVisita;
        }

        return false;
    }

    protected function recalcularCondicionesAlIniciarModulo(): void
    {
        $modulo = $this->moduloMateriasAdeudadas();

        if ($this->recalculoCondicionesEjecutado
            || MateriasAdeudadasPreparacion::recalculoEjecutadoEnVisita($modulo)
            || ! $this->preparacionAceptadaEnEstaVisita) {
            if (MateriasAdeudadasPreparacion::recalculoEjecutadoEnVisita($modulo)) {
                $this->recalculoCondicionesEjecutado = true;
            }

            return;
        }

        $ctx = schoolCtx();
        if (! $ctx->isValid() || $this->idTurnoPreparacion < 1 || $this->idTerlecPreparacion < 1) {
            return;
        }

        $stats = MateriasAdeudadasCondicionRecalculo::recalcularNivel(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $this->idTerlecPreparacion,
            $this->idTurnoPreparacion,
        );

        $this->recalculoCondicionesEjecutado = true;
        MateriasAdeudadasPreparacion::marcarRecalculoEjecutadoEnVisita($modulo);

        $this->mensajeRecalculo = sprintf(
            'Condiciones recalculadas para %s · año lectivo %s (%d registro%s revisado%s, %d actualizado%s).',
            MateriasAdeudadasPreparacion::etiquetaTurno($this->idTurnoPreparacion),
            MateriasAdeudadasPreparacion::anoTerlec($this->idTerlecPreparacion) ?? '—',
            $stats['procesados'],
            $stats['procesados'] === 1 ? '' : 's',
            $stats['procesados'] === 1 ? '' : 's',
            $stats['actualizados'],
            $stats['actualizados'] === 1 ? '' : 's',
        );
    }

    public function confirmarPreparacionMateriasAdeudadas(): void
    {
        $this->asegurarCatalogosPreparacionCargados();
        $this->ejecutarConfirmarPreparacion($this->moduloMateriasAdeudadas());
    }

    protected function ejecutarConfirmarPreparacion(string $modulo): void
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return;
        }

        $this->idTurnoPreparacion = (int) $this->idTurnoPreparacion;
        $this->idTerlecPreparacion = (int) $this->idTerlecPreparacion;

        $turnoIds = collect($this->turnosDisponibles)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $terlecIds = Terlec::paraSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->validate([
            'idTurnoPreparacion' => ['required', 'integer', 'min:1', Rule::in($turnoIds)],
            'idTerlecPreparacion' => ['required', 'integer', 'min:1', Rule::in($terlecIds)],
        ], [
            'idTurnoPreparacion.required' => 'Seleccioná el turno de examen.',
            'idTurnoPreparacion.min' => 'Seleccioná el turno de examen.',
            'idTerlecPreparacion.required' => 'Seleccioná el año lectivo del turno.',
            'idTerlecPreparacion.min' => 'Seleccioná el año lectivo del turno.',
        ]);

        $key = 'examenes-preparacion:'.$ctx->idProfesor.':'.$modulo;
        if (RateLimiter::tooManyAttempts($key, 8)) {
            $this->addError('idTurnoPreparacion', 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->preparacionAceptadaEnEstaVisita = true;

        MateriasAdeudadasPreparacion::guardar(
            (int) $ctx->idNivel,
            $this->idTurnoPreparacion,
            $this->idTerlecPreparacion,
            $modulo,
        );
        MateriasAdeudadasPreparacion::marcarVisitaConfirmada($modulo);

        // Cada confirmación vuelve a recalcular (gestión permite cambiar turno/año y repetir).
        MateriasAdeudadasPreparacion::olvidarRecalculoEnVisita($modulo);
        $this->recalculoCondicionesEjecutado = false;

        try {
            $this->recalcularCondicionesAlIniciarModulo();
        } catch (\Throwable $e) {
            report($e);
            $this->mensajeRecalculo = 'La preparación quedó registrada, pero el recálculo no pudo completarse. Reintentá o contactá soporte.';
        }
    }

    public function cambiarPreparacionMateriasAdeudadas(): void
    {
        $modulo = $this->moduloMateriasAdeudadas();
        MateriasAdeudadasPreparacion::marcarVisitaSinConfirmar($modulo);
        $this->preparacionAceptadaEnEstaVisita = false;
        $this->mensajeRecalculo = null;
        $this->recalculoCondicionesEjecutado = false;
        $this->bootPreparacionMateriasAdeudadas();
        $this->iniciarPreparacionMateriasAdeudadas();
    }

    /**
     * @return array<string, mixed>
     */
    protected function datosVistaPreparacion(): array
    {
        $this->asegurarCatalogosPreparacionCargados();

        $preparacionLista = $this->preparacionConfirmada();
        $siempreSelects = $this->siempreMostrarSelectsPreparacion();

        return [
            'turnosDisponibles' => $this->turnosDisponibles,
            'terlecsDisponibles' => $this->terlecsDisponibles,
            'preparacionLista' => $preparacionLista,
            'siempreMostrarSelectsPreparacion' => $siempreSelects,
            'mostrarFormularioPreparacion' => $siempreSelects ? true : ! $preparacionLista,
            'mensajeRecalculo' => $this->mensajeRecalculo,
            'etiquetaTurnoPreparacion' => $preparacionLista
                ? MateriasAdeudadasPreparacion::etiquetaTurno($this->idTurnoPreparacion)
                : null,
            'anoTerlecPreparacion' => $preparacionLista
                ? MateriasAdeudadasPreparacion::anoTerlec($this->idTerlecPreparacion)
                : null,
        ];
    }
}
