<?php

namespace App\Livewire\Docentes\LibroDeTemas;

use App\Livewire\Docentes\LibroDeTemas\Concerns\InteractsWithLibroDeTemas;
use App\Models\LibroDeTema;
use App\Support\Database\PersistenciaColumnas;
use App\Support\LibroDeTemas\LibroDeTemasService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use stdClass;

class LibroDeTemasClases extends Component
{
    use InteractsWithLibroDeTemas;
    use WithPagination;

    public const POR_PAGINA = 50;

    #[Locked]
    public int $idMateria = 0;

    public string $buscar = '';

    public bool $modalAbierto = false;

    public ?int $editId = null;

    public string $fecha = '';

    public string $claseNro = '';

    public string $unidad = '';

    public string $caracter = '';

    public string $temas = '';

    public string $actividades = '';

    public string $observaciones = '';

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(int $materia): void
    {
        $this->inicializarLibroDeTemas();
        $this->idMateria = $materia;
        abort_unless($this->materiaActual() !== null, 404);
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function abrirNueva(): void
    {
        $this->resetFormulario();
        $this->fecha = Carbon::today()->format('Y-m-d');
        $this->claseNro = (string) LibroDeTemasService::proximoClaseNro($this->idMateria);
        $this->unidad = (string) LibroDeTemasService::ultimaUnidad($this->idMateria);
        $this->modalAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        $reg = LibroDeTemasService::scopedOrFail($id, $this->idMateria);
        $this->resetValidation();
        $this->editId = (int) $reg->id;
        $this->fecha = $reg->fecha?->format('Y-m-d') ?? '';
        $this->claseNro = (string) (int) $reg->claseNro;
        $this->unidad = (string) (int) $reg->unidad;
        $this->caracter = (string) $reg->caracter;
        $this->temas = (string) $reg->temas;
        $this->actividades = (string) $reg->actividades;
        $this->observaciones = (string) $reg->observaciones;
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    public function guardar(): void
    {
        if (! RateLimiter::attempt('libro-temas-guardar-'.(auth()->id() ?? 0), 40, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        abort_unless($this->materiaActual() !== null, 404);

        $this->validate([
            'fecha' => ['required', 'date'],
            'claseNro' => ['required', 'integer', 'min:0', 'max:99999'],
            'unidad' => ['required', 'integer', 'min:0', 'max:99999'],
            'caracter' => ['nullable', 'string', 'max:50'],
            'temas' => ['nullable', 'string'],
            'actividades' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
        ], [
            'fecha.required' => 'Indique la fecha de la clase.',
            'fecha.date' => 'La fecha no es válida.',
            'claseNro.required' => 'Indique el número de clase.',
            'claseNro.integer' => 'El número de clase debe ser un entero.',
            'unidad.required' => 'Indique la unidad.',
            'unidad.integer' => 'La unidad debe ser un entero.',
            'caracter.max' => 'El carácter no puede superar los 50 caracteres.',
        ]);

        $payload = [
            'idMateria' => $this->idMateria,
            'fecha' => $this->fecha,
            'claseNro' => (int) $this->claseNro,
            'unidad' => (int) $this->unidad,
            'caracter' => trim($this->caracter),
            'temas' => trim($this->temas),
            'actividades' => trim($this->actividades),
            'observaciones' => trim($this->observaciones),
        ];

        $esEdicion = $this->editId !== null;
        if (! $this->persistirPayload($payload, $this->editId, 'No se pudo guardar la clase.')) {
            return;
        }

        $this->modalAbierto = false;
        $this->resetFormulario();
        if ($esEdicion) {
            $this->dispatch('se-swal-exito', mensaje: 'Clase actualizada.');
        }
    }

    public function copiarUltima(): void
    {
        if (! RateLimiter::attempt('libro-temas-copiar-'.(auth()->id() ?? 0), 20, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        abort_unless($this->materiaActual() !== null, 404);

        $ultima = LibroDeTemasService::ultimaClase($this->idMateria);
        if ($ultima === null) {
            $this->dispatch('se-swal-error', mensaje: 'No hay una clase guardada para copiar.');

            return;
        }

        $payload = [
            'idMateria' => $this->idMateria,
            'fecha' => $ultima->fecha?->format('Y-m-d'),
            'claseNro' => (int) $ultima->claseNro,
            'unidad' => (int) $ultima->unidad,
            'caracter' => trim((string) $ultima->caracter),
            'temas' => (string) $ultima->temas,
            'actividades' => (string) $ultima->actividades,
            'observaciones' => (string) $ultima->observaciones,
        ];

        if (! $this->persistirPayload($payload, null, 'No se pudo copiar la última clase.')) {
            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Se insertó una copia de la última clase.');
    }

    public function eliminar(int $id): void
    {
        if (! RateLimiter::attempt('libro-temas-eliminar-'.(auth()->id() ?? 0), 20, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        abort_unless($this->materiaActual() !== null, 404);

        $reg = LibroDeTemasService::scopedOrFail($id, $this->idMateria);

        try {
            $reg->delete();
        } catch (QueryException $e) {
            $this->dispatch(
                'se-swal-error',
                mensaje: PersistenciaColumnas::mensajeDesdeQueryException($e) ?? 'No se pudo eliminar la clase.',
            );

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: 'Clase eliminada.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistirPayload(array $payload, ?int $editId, string $errorGenerico): bool
    {
        $payload = PersistenciaColumnas::adaptarEnterosVacios(LibroDeTemasService::TABLA, $payload);
        $payload = PersistenciaColumnas::reemplazarNulosExplicitos(LibroDeTemasService::TABLA, $payload);

        $preparado = PersistenciaColumnas::prepararPayload(LibroDeTemasService::TABLA, $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $this->dispatch(
                'se-swal-error',
                mensaje: PersistenciaColumnas::mensajeColumnasInexistentes(
                    LibroDeTemasService::TABLA,
                    $preparado['columnas_con_valor_sin_columna'],
                ),
            );

            return false;
        }

        $paraGuardar = PersistenciaColumnas::completarNotNullSinDefault(
            LibroDeTemasService::TABLA,
            $preparado['payload'],
        );

        try {
            if ($editId !== null) {
                $reg = LibroDeTemasService::scopedOrFail($editId, $this->idMateria);
                $reg->fill($paraGuardar);
                $reg->save();
                $idGuardado = (int) $reg->id;
            } else {
                $reg = LibroDeTema::query()->create($paraGuardar);
                $idGuardado = (int) $reg->id;
            }
        } catch (QueryException $e) {
            $this->dispatch(
                'se-swal-error',
                mensaje: PersistenciaColumnas::mensajeDesdeQueryException($e) ?? $errorGenerico,
            );

            return false;
        }

        $esperados = $payload;
        unset($esperados['idMateria']);
        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            LibroDeTemasService::TABLA,
            ['id' => $idGuardado],
            $esperados,
        );
        if ($noPersistidas !== []) {
            $this->dispatch(
                'se-swal-error',
                mensaje: PersistenciaColumnas::mensajeColumnasNoPersistidas(
                    LibroDeTemasService::TABLA,
                    $noPersistidas,
                ),
            );

            return false;
        }

        return true;
    }

    private function resetFormulario(): void
    {
        $this->resetValidation();
        $this->editId = null;
        $this->fecha = '';
        $this->claseNro = '';
        $this->unidad = '';
        $this->caracter = '';
        $this->temas = '';
        $this->actividades = '';
        $this->observaciones = '';
    }

    private function materiaActual(): ?stdClass
    {
        return LibroDeTemasService::materiaEnAlcance($this->idMateria, $this->soloPpcDelProfesor());
    }

    public function render()
    {
        $materia = $this->materiaActual();
        abort_unless($materia !== null, 404);

        $query = LibroDeTemasService::queryClases($this->idMateria);
        $texto = trim($this->buscar);
        if ($texto !== '') {
            $like = '%'.LibroDeTemasService::likeEscape($texto).'%';
            $query->where(function ($q) use ($like) {
                $q->where('temas', 'like', $like)
                    ->orWhere('actividades', 'like', $like)
                    ->orWhere('observaciones', 'like', $like)
                    ->orWhere('caracter', 'like', $like);
            });
        }

        return view('livewire.docentes.libro-de-temas.clases', [
            'materia' => $materia,
            'registros' => $query->paginate(self::POR_PAGINA),
            'sugerenciasCaracter' => LibroDeTemasService::sugerenciasCaracter(),
        ])->layout($this->layoutLibroDeTemas(), ['pageTitle' => 'Libro de temas']);
    }
}
