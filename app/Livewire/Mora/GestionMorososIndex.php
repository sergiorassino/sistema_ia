<?php

namespace App\Livewire\Mora;

use App\Support\Mail\MailDesarrollo;
use App\Support\Mora\GestionMorososConsulta;
use App\Support\Mora\GestionMorososFiltros;
use App\Support\Mora\GestionMorososPdfPedido;
use App\Support\Mora\NotificacionDeudaCorreo;
use App\Support\Mora\PermisosMora;
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

    public bool $chkNivel = false;

    public int $idNivel = 0;

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

    public bool $modalMailAbierto = false;

    /** @var list<array<string, mixed>> */
    public array $destinatariosMail = [];

    public int $destinatariosMailTotal = 0;

    public int $destinatariosMailListos = 0;

    public int $destinatariosMailIncompletos = 0;

    /** @var list<array{idNivel: int, nivel: string, cuenta: string}> */
    public array $cuentasSmtpMail = [];

    /** @var list<string> */
    public array $avisosSmtpMail = [];

    /** @var array<string, mixed> */
    public array $filtrosMailSnapshot = [];

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

    public function updatedChkNivel(): void
    {
        $this->sincronizarCursosConNivel();
    }

    public function updatedIdNivel(): void
    {
        $this->sincronizarCursosConNivel();
    }

    public function puedeGenerarPdf(): bool
    {
        return GestionMorososFiltros::puedeGenerarPdf($this->filtrosCrudos());
    }

    public function abrirPdfListado(): void
    {
        $this->abrirPdf('mora.gestion-morosos.pdf', GestionMorososPdfPedido::TIPO_LISTADO);
    }

    public function abrirPdfNotificacion(): void
    {
        $this->abrirPdf('mora.gestion-morosos.notificacion', GestionMorososPdfPedido::TIPO_NOTIFICACION);
    }

    public function abrirPreviewMailNotificacion(): void
    {
        abort_unless(PermisosMora::puedeGestionMorosos(), 403);

        if (! $this->puedeGenerarPdf()) {
            $this->dispatch('se-swal-error', mensaje: 'Revise los filtros activos antes de armar el envío.');

            return;
        }

        $filtros = GestionMorososFiltros::normalizarDesdeLivewire($this->filtrosCrudos());

        if (! GestionMorososConsulta::tieneCuotasAdeudadas($filtros)) {
            $this->dispatch('se-swal-aviso', mensaje: 'No hay registros.');

            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $lista = NotificacionDeudaCorreo::listarDestinatarios($filtros);
        if ($lista['total'] < 1) {
            $this->dispatch('se-swal-aviso', mensaje: 'No hay destinatarios para los filtros indicados.');

            return;
        }

        $this->filtrosMailSnapshot = $filtros;
        $this->destinatariosMail = $lista['destinatarios'];
        $this->destinatariosMailTotal = $lista['total'];
        $this->destinatariosMailListos = $lista['listos'];
        $this->destinatariosMailIncompletos = $lista['incompletos'];
        $this->cuentasSmtpMail = $lista['cuentasSmtp'];
        $this->avisosSmtpMail = $lista['avisosSmtp'];
        $this->modalMailAbierto = true;
    }

    public function cerrarModalMail(): void
    {
        $this->modalMailAbierto = false;
        $this->destinatariosMail = [];
        $this->destinatariosMailTotal = 0;
        $this->destinatariosMailListos = 0;
        $this->destinatariosMailIncompletos = 0;
        $this->cuentasSmtpMail = [];
        $this->avisosSmtpMail = [];
        $this->filtrosMailSnapshot = [];
    }

    public function enviarMailNotificacion(): void
    {
        abort_unless(PermisosMora::puedeGestionMorosos(), 403);

        if (! $this->modalMailAbierto) {
            return;
        }

        if ($this->destinatariosMailListos < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No hay destinatarios listos para enviar.');

            return;
        }

        $filtros = $this->filtrosMailSnapshot;
        if ($filtros === []) {
            $this->dispatch('se-swal-error', mensaje: 'Vuelva a armar la lista de destinatarios.');

            return;
        }

        $resultado = NotificacionDeudaCorreo::enviar($filtros, (int) schoolCtx()->idProfesor);
        $this->cerrarModalMail();

        if ($resultado['enviados'] > 0 && $resultado['errores'] === 0) {
            $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);

            return;
        }

        if ($resultado['enviados'] > 0) {
            $this->dispatch('se-swal-aviso', mensaje: $resultado['mensaje']);

            return;
        }

        $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);
    }

    private function abrirPdf(string $ruta, string $tipo): void
    {
        if (! $this->puedeGenerarPdf()) {
            $this->dispatch('se-swal-error', mensaje: 'Revise los filtros activos antes de generar el PDF.');

            return;
        }

        $filtros = GestionMorososFiltros::normalizarDesdeLivewire($this->filtrosCrudos());

        if (! GestionMorososConsulta::tieneCuotasAdeudadas($filtros)) {
            $this->dispatch('se-swal-aviso', mensaje: 'No hay registros.');

            return;
        }

        $ref = GestionMorososPdfPedido::guardar($filtros, $tipo);

        $this->dispatch('mora-gestion-morosos-abrir-pdf', url: se_route_url($ruta, ['ref' => $ref]));
    }

    private function idNivelFiltroCursos(): ?int
    {
        if (! $this->chkNivel || $this->idNivel < 1) {
            return null;
        }

        return $this->idNivel;
    }

    private function sincronizarCursosConNivel(): void
    {
        if ($this->idsCursos === []) {
            return;
        }

        $permitidos = GestionMorososFiltros::cursosParaSelector($this->idNivelFiltroCursos())
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->idsCursos = array_values(array_filter(
            array_map(fn ($id) => (int) $id, $this->idsCursos),
            fn (int $id) => in_array($id, $permitidos, true),
        ));
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
            'chkNivel' => $this->chkNivel,
            'idNivel' => $this->idNivel,
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
        $cursos = GestionMorososFiltros::cursosParaSelector($this->idNivelFiltroCursos());

        return view('livewire.mora.gestion-morosos-index', [
            'familias' => GestionMorososFiltros::familiasParaSelector(),
            'alumnos' => GestionMorososFiltros::alumnosParaSelector(),
            'cuotas' => GestionMorososFiltros::cuotasParaExcluir(),
            'niveles' => GestionMorososFiltros::nivelesParaSelector(),
            'cursos' => $cursos,
            'becas' => GestionMorososFiltros::becasParaSelector(),
            'terlecs' => GestionMorososFiltros::terlecsParaSelector(),
            'etiquetaCurso' => fn ($curso) => mb_strtoupper(trim((string) ($curso->cursec ?? $curso->nombreParaListado() ?? ''))),
            'puedeGenerarPdf' => $this->puedeGenerarPdf(),
            'anoContexto' => schoolCtx()->terlecAno(),
            'mailLocalLog' => MailDesarrollo::bloquearSmtp(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de Morosos']);
    }
}
