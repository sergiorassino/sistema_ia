<?php

namespace App\Livewire\Mora;

use App\Support\Mora\GestionMorososFiltros;
use App\Support\Mora\PermisosMora;
use App\Support\Security\OpaqueRouteToken;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Gestión de morosos — filtros y acciones (Administración).
 */
class GestionMorososIndex extends Component
{
    public string $fechaCalculo = '';

    public bool $chkFamilia = false;

    public int $idFamilia = 0;

    public bool $chkAlumno = false;

    public int $idAlumno = 0;

    public bool $chkVencDesde = false;

    public string $vencDesde = '';

    public bool $chkVencHasta = false;

    public string $vencHasta = '';

    public bool $chkExcluir = false;

    /** @var list<int> */
    public array $idsExcluirCuotas = [];

    public bool $chkCurso = false;

    /** @var list<int> */
    public array $idsCursos = [];

    public bool $chkMasDe = false;

    public int $masDe = 1;

    public bool $chkHasta = false;

    public int $hasta = 1;

    public bool $chkSoloFuera = false;

    public bool $chkExceptoFuera = false;

    public bool $chkAno = false;

    public int $idTerlec = 0;

    public bool $chkSoloBecados = false;

    /** @var list<int> */
    public array $idsBecas = [];

    public function mount(): void
    {
        abort_unless(PermisosMora::puedeGestionMorosos(), 403);

        $this->fechaCalculo = Carbon::today()->format('Y-m-d');
        $this->idTerlec = (int) schoolCtx()->idTerlec;
    }

    public function updatedChkSoloFuera(bool $valor): void
    {
        if ($valor) {
            $this->chkExceptoFuera = false;
        }
    }

    public function updatedChkExceptoFuera(bool $valor): void
    {
        if ($valor) {
            $this->chkSoloFuera = false;
        }
    }

    public function puedeGenerarPdf(): bool
    {
        return GestionMorososFiltros::puedeGenerarPdf($this->filtrosCrudos());
    }

    public function getPdfUrlProperty(): string
    {
        return $this->urlPdfMorosos('mora.gestion-morosos.pdf', OpaqueRouteToken::forListadoMorosos(...));
    }

    public function getPdfNotificacionUrlProperty(): string
    {
        return $this->urlPdfMorosos('mora.gestion-morosos.notificacion', OpaqueRouteToken::forNotificacionDeudaMorosos(...));
    }

    /**
     * @param  callable(array<string, mixed>): string  $crearRef
     */
    private function urlPdfMorosos(string $ruta, callable $crearRef): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '#';
        }

        $filtros = GestionMorososFiltros::normalizarDesdeLivewire($this->filtrosCrudos());

        return route($ruta, ['ref' => $crearRef($filtros)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosCrudos(): array
    {
        return [
            'fechaCalculo' => $this->fechaCalculo,
            'chkFamilia' => $this->chkFamilia,
            'idFamilia' => $this->idFamilia,
            'chkAlumno' => $this->chkAlumno,
            'idAlumno' => $this->idAlumno,
            'chkVencDesde' => $this->chkVencDesde,
            'vencDesde' => $this->vencDesde,
            'chkVencHasta' => $this->chkVencHasta,
            'vencHasta' => $this->vencHasta,
            'chkExcluir' => $this->chkExcluir,
            'idsExcluirCuotas' => $this->idsExcluirCuotas,
            'chkCurso' => $this->chkCurso,
            'idsCursos' => $this->idsCursos,
            'chkMasDe' => $this->chkMasDe,
            'masDe' => $this->masDe,
            'chkHasta' => $this->chkHasta,
            'hasta' => $this->hasta,
            'chkSoloFuera' => $this->chkSoloFuera,
            'chkExceptoFuera' => $this->chkExceptoFuera,
            'chkAno' => $this->chkAno,
            'idTerlec' => $this->idTerlec,
            'chkSoloBecados' => $this->chkSoloBecados,
            'idsBecas' => $this->idsBecas,
        ];
    }

    public function render()
    {
        $cursos = GestionMorososFiltros::cursosParaSelector();

        return view('livewire.mora.gestion-morosos-index', [
            'familias' => GestionMorososFiltros::familiasParaSelector(),
            'alumnos' => GestionMorososFiltros::alumnosParaSelector(),
            'cuotas' => GestionMorososFiltros::cuotasParaExcluir(),
            'cursos' => $cursos,
            'becas' => GestionMorososFiltros::becasParaSelector(),
            'terlecs' => GestionMorososFiltros::terlecsParaSelector(),
            'etiquetaCurso' => fn ($curso) => mb_strtoupper(trim((string) ($curso->cursec ?? $curso->nombreParaListado() ?? ''))),
            'puedeGenerarPdf' => $this->puedeGenerarPdf(),
            'pdfUrl' => $this->pdfUrl,
            'pdfNotificacionUrl' => $this->pdfNotificacionUrl,
            'anoContexto' => schoolCtx()->terlecAno(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de Morosos']);
    }
}
