<?php

namespace App\Livewire\Docentes\Capacitacion;

use App\Models\CapacitacionDocente;
use App\Support\CapacitacionDocente\CapacitacionDocenteService;
use App\Support\PermisosIaCatalog;
use App\Support\Security\OpaqueRouteToken;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

class CapacitacionDocenteIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const POR_PAGINA = 50;

    /** listado | resumen */
    public string $vista = 'listado';

    public string $buscar = '';

    public string $filtroProfesor = '';

    public bool $modalAbierto = false;

    public ?int $editId = null;

    public string $id_profesor = '';

    public string $fecha = '';

    public string $nombre = '';

    public string $entidad_otorgante = '';

    public string $duracion = '';

    public string $modalidad = 'presencial';

    /** @var TemporaryUploadedFile|null */
    public $certificadoPdf = null;

    public bool $tieneCertificado = false;

    public bool $quitarCertificado = false;

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'vista' => ['except' => 'listado'],
        'buscar' => ['except' => ''],
        'filtroProfesor' => ['except' => '', 'as' => 'profesor'],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CAPACITACION_DOCENTE), 403, 'Sin permiso para capacitación docente.');
        if (! in_array($this->vista, ['listado', 'resumen'], true)) {
            $this->vista = 'listado';
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroProfesor(): void
    {
        $this->resetPage();
    }

    public function updatedVista(string $value): void
    {
        if (! in_array($value, ['listado', 'resumen'], true)) {
            $this->vista = 'listado';
        }
    }

    public function updatedCertificadoPdf(): void
    {
        $this->resetValidation('certificadoPdf');
        $this->quitarCertificado = false;
        $error = CapacitacionDocenteService::validarPdf($this->certificadoPdf);
        if ($error !== null) {
            $this->addError('certificadoPdf', $error);
            $this->certificadoPdf = null;
        }
    }

    public function abrirNuevo(): void
    {
        $this->resetFormulario();
        $this->fecha = Carbon::today()->format('Y-m-d');
        $this->modalAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        $reg = CapacitacionDocenteService::scopedOrFail($id);
        $this->resetValidation();
        $this->editId = (int) $reg->id;
        $this->id_profesor = (string) (int) $reg->id_profesor;
        $this->fecha = $reg->fecha?->format('Y-m-d') ?? '';
        $this->nombre = (string) $reg->nombre;
        $this->entidad_otorgante = (string) $reg->entidad_otorgante;
        $this->duracion = (string) $reg->duracion;
        $this->modalidad = (string) $reg->modalidad;
        $this->certificadoPdf = null;
        $this->tieneCertificado = $reg->tieneCertificado();
        $this->quitarCertificado = false;
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    public function guardar(): void
    {
        if (! RateLimiter::attempt('cap-doc-guardar-'.(auth()->id() ?? 0), 40, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        if (! CapacitacionDocenteService::tablaDisponible()) {
            $this->dispatch('se-swal-error', mensaje: CapacitacionDocenteService::mensajeTablaFaltante());

            return;
        }

        $idNivel = CapacitacionDocenteService::idNivelContexto();
        if ($idNivel === null || $idNivel < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No hay nivel activo en el contexto.');

            return;
        }

        $idsProfesores = CapacitacionDocenteService::profesoresParaSelector()
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $this->validate([
            'id_profesor' => ['required', 'integer', Rule::in($idsProfesores)],
            'fecha' => ['required', 'date'],
            'nombre' => ['required', 'string', 'max:255'],
            'entidad_otorgante' => ['required', 'string', 'max:255'],
            'duracion' => ['required', 'string', 'max:80'],
            'modalidad' => ['required', Rule::in(CapacitacionDocenteService::modalidades())],
            'certificadoPdf' => ['nullable'],
        ], [
            'id_profesor.required' => 'Seleccione el docente.',
            'id_profesor.in' => 'El docente no pertenece al nivel activo.',
            'fecha.required' => 'Indique la fecha del curso.',
            'nombre.required' => 'Indique el nombre del curso.',
            'entidad_otorgante.required' => 'Indique la entidad otorgante.',
            'duracion.required' => 'Indique la duración.',
            'modalidad.in' => 'Modalidad inválida.',
        ]);

        $profesor = CapacitacionDocenteService::scopedProfesorOrFail((int) $this->id_profesor);

        $rutaAnterior = null;
        $reg = null;

        if ($this->editId !== null) {
            $reg = CapacitacionDocenteService::scopedOrFail($this->editId);
            $rutaAnterior = trim((string) ($reg->certificado_archivo ?? ''));
        }

        if ($this->certificadoPdf instanceof TemporaryUploadedFile) {
            $errorPdf = CapacitacionDocenteService::validarPdf($this->certificadoPdf);
            if ($errorPdf !== null) {
                $this->addError('certificadoPdf', $errorPdf);

                return;
            }
        }

        $payload = [
            'id_profesor' => (int) $this->id_profesor,
            'id_nivel' => $idNivel,
            'fecha' => $this->fecha,
            'nombre' => trim($this->nombre),
            'entidad_otorgante' => trim($this->entidad_otorgante),
            'duracion' => trim($this->duracion),
            'modalidad' => $this->modalidad,
        ];

        if ($this->quitarCertificado && $this->editId !== null && ! ($this->certificadoPdf instanceof TemporaryUploadedFile)) {
            $payload['certificado_archivo'] = null;
        }

        try {
            if ($reg !== null) {
                $reg->fill($payload);
                $reg->save();
            } else {
                $reg = CapacitacionDocente::query()->create($payload);
            }
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo guardar el registro en la base de datos.');

            return;
        }

        $rutaNueva = null;
        if ($this->certificadoPdf instanceof TemporaryUploadedFile) {
            try {
                $rutaNueva = CapacitacionDocenteService::guardarCertificado(
                    $idNivel,
                    $profesor->dni,
                    (int) $reg->id,
                    $this->certificadoPdf,
                );
                $reg->certificado_archivo = $rutaNueva;
                $reg->save();
            } catch (QueryException $e) {
                if ($rutaNueva !== null) {
                    CapacitacionDocenteService::eliminarCertificado($rutaNueva);
                }
                $this->dispatch('se-swal-error', mensaje: 'No se pudo asociar el PDF al registro.');

                return;
            } catch (\Throwable $e) {
                $this->dispatch(
                    'se-swal-error',
                    mensaje: $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo guardar el PDF.',
                );

                return;
            }
        }

        if ($rutaNueva !== null && $rutaAnterior !== null && $rutaAnterior !== '' && $rutaAnterior !== $rutaNueva) {
            CapacitacionDocenteService::eliminarCertificado($rutaAnterior);
        } elseif ($this->quitarCertificado && $rutaAnterior !== null && $rutaAnterior !== '' && $rutaNueva === null) {
            CapacitacionDocenteService::eliminarCertificado($rutaAnterior);
        }

        $this->modalAbierto = false;
        $this->resetFormulario();
        $this->dispatch('se-swal-exito', mensaje: 'Capacitación guardada.');
    }

    public function eliminar(int $id): void
    {
        if (! RateLimiter::attempt('cap-doc-eliminar-'.(auth()->id() ?? 0), 20, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $reg = CapacitacionDocenteService::scopedOrFail($id);
        $ruta = trim((string) ($reg->certificado_archivo ?? ''));

        try {
            $reg->delete();
        } catch (QueryException $e) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo eliminar el registro.');

            return;
        }

        CapacitacionDocenteService::eliminarCertificado($ruta);
        $this->dispatch('se-swal-exito', mensaje: 'Capacitación eliminada.');
    }

    public function urlCertificado(int $id): string
    {
        return route('docentes.capacitacion.certificado', [
            'ref' => OpaqueRouteToken::forCapacitacionDocenteCertificado($id),
        ]);
    }

    private function resetFormulario(): void
    {
        $this->resetValidation();
        $this->editId = null;
        $this->id_profesor = '';
        $this->fecha = '';
        $this->nombre = '';
        $this->entidad_otorgante = '';
        $this->duracion = '';
        $this->modalidad = 'presencial';
        $this->certificadoPdf = null;
        $this->tieneCertificado = false;
        $this->quitarCertificado = false;
    }

    public function render()
    {
        $tablasOk = CapacitacionDocenteService::tablaDisponible();
        $anioActual = (int) date('Y');

        return view('livewire.docentes.capacitacion.index', [
            'tablasOk' => $tablasOk,
            'mensajeTabla' => $tablasOk ? '' : CapacitacionDocenteService::mensajeTablaFaltante(),
            'registros' => $tablasOk && $this->vista === 'listado'
                ? CapacitacionDocenteService::paginar(
                    ((int) $this->filtroProfesor) > 0 ? (int) $this->filtroProfesor : null,
                    $this->buscar,
                    self::POR_PAGINA,
                )
                : null,
            'profesores' => $tablasOk
                ? CapacitacionDocenteService::profesoresParaSelector()
                : collect(),
            'modalidades' => CapacitacionDocenteService::etiquetasModalidad(),
            'anioActual' => $anioActual,
            'resumen' => $tablasOk && $this->vista === 'resumen'
                ? CapacitacionDocenteService::resumenPorDocenteAnio($anioActual)
                : [],
            'totalAnio' => $tablasOk && $this->vista === 'resumen'
                ? CapacitacionDocenteService::totalCursosAnio($anioActual)
                : 0,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Capacitación docente']);
    }
}
