<?php

namespace App\Livewire\MatriculaWeb;

use App\Livewire\Concerns\RequiresPermisoMatriculaWeb;
use App\Models\DocEstudianteTipo;
use App\Support\PermisosMatriculaWeb;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class DocumentosEstudianteTiposIndex extends Component
{
    use RequiresPermisoMatriculaWeb;

    public bool $showModal = false;

    public bool $showConfirm = false;

    public ?int $editId = null;

    public ?int $deleteId = null;

    public string $deleteInfo = '';

    public string $clave = '';

    public string $etiqueta = '';

    public string $explicacion = '';

    /** @var list<string> */
    public array $extensionesSeleccionadas = ['jpg', 'jpeg', 'pdf'];

    public int $maxArchivos = 1;

    public string $maxMb = '';

    public bool $obligatorio = false;

    public bool $activo = true;

    public int $orden = 0;

    protected function permisoMatriculaWebOrden(): int
    {
        return PermisosMatriculaWeb::DOCUMENTOS_ESTUDIANTE_FAMILIA;
    }

    public function mount(): void
    {
        abort_unless(DocEstudianteTipo::tablaDisponible(), 503, 'La parametrización de documentos aún no está disponible en este entorno.');
    }

    protected function rules(): array
    {
        $id = $this->editId;

        return [
            'clave' => [
                $id ? 'nullable' : 'required',
                'string',
                'max:40',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('doc_estudiante_tipos', 'clave')->ignore($id),
            ],
            'etiqueta' => ['required', 'string', 'max:120'],
            'explicacion' => ['nullable', 'string', 'max:'.DocEstudianteTipo::MAX_EXPLICACION_LENGTH],
            'extensionesSeleccionadas' => ['required', 'array', 'min:1'],
            'extensionesSeleccionadas.*' => ['string', Rule::in(DocEstudianteTipo::EXTENSIONES_SOPORTADAS)],
            'maxArchivos' => ['required', 'integer', 'min:1', 'max:'.DocEstudianteTipo::MAX_ARCHIVOS_LIMITE],
            'maxMb' => ['required', 'integer', 'min:1', 'max:'.DocEstudianteTipo::MAX_MB_LIMITE],
            'obligatorio' => ['boolean'],
            'activo' => ['boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function messages(): array
    {
        return [
            'clave.required' => 'La clave es obligatoria.',
            'clave.regex' => 'La clave solo puede contener letras minúsculas, números, guión y guión bajo.',
            'clave.unique' => 'Ya existe un documento con esa clave.',
            'etiqueta.required' => 'La etiqueta es obligatoria.',
            'extensionesSeleccionadas.required' => 'Seleccione al menos una extensión permitida.',
            'extensionesSeleccionadas.min' => 'Seleccione al menos una extensión permitida.',
        ];
    }

    public function openCreate(): void
    {
        $this->reset(
            'clave',
            'etiqueta',
            'explicacion',
            'extensionesSeleccionadas',
            'maxArchivos',
            'maxMb',
            'obligatorio',
            'activo',
            'orden',
            'editId',
        );
        $this->extensionesSeleccionadas = ['jpg', 'jpeg', 'pdf'];
        $this->maxArchivos = 1;
        $this->maxMb = (string) DocEstudianteTipo::MAX_MB_DEFAULT;
        $this->activo = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $tipo = DocEstudianteTipo::query()->findOrFail($id);
        $this->editId = $id;
        $this->clave = (string) $tipo->clave;
        $this->etiqueta = (string) $tipo->etiqueta;
        $this->explicacion = (string) ($tipo->explicacion ?? '');
        $this->extensionesSeleccionadas = $tipo->extensionesNormalizadas();
        $this->maxArchivos = max(1, (int) $tipo->max_archivos);
        $this->maxMb = $tipo->max_mb !== null
            ? (string) (int) $tipo->max_mb
            : (string) DocEstudianteTipo::MAX_MB_DEFAULT;
        $this->obligatorio = (bool) $tipo->obligatorio;
        $this->activo = (bool) $tipo->activo;
        $this->orden = (int) $tipo->orden;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $key = 'doc-est-tipos:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('etiqueta', 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $payload = [
            'etiqueta' => trim($this->etiqueta),
            'explicacion' => DocEstudianteTipo::explicacionNormalizada($this->explicacion),
            'extensiones' => array_values(array_unique($this->extensionesSeleccionadas)),
            'max_archivos' => (int) $this->maxArchivos,
            'max_mb' => trim($this->maxMb) === '' ? DocEstudianteTipo::MAX_MB_DEFAULT : (int) $this->maxMb,
            'obligatorio' => $this->obligatorio,
            'activo' => $this->activo,
            'orden' => (int) $this->orden,
        ];

        if ($this->editId) {
            DocEstudianteTipo::query()->findOrFail($this->editId)->update($payload);
            session()->flash('success', 'Documento actualizado.');
        } else {
            $payload['clave'] = DocEstudianteTipo::normalizarClave($this->clave);
            DocEstudianteTipo::query()->create($payload);
            session()->flash('success', 'Documento agregado.');
        }

        $this->showModal = false;
        $this->reset(
            'clave',
            'etiqueta',
            'explicacion',
            'extensionesSeleccionadas',
            'maxArchivos',
            'maxMb',
            'obligatorio',
            'activo',
            'orden',
            'editId',
        );
    }

    public function toggleActivo(int $id): void
    {
        $tipo = DocEstudianteTipo::query()->findOrFail($id);
        $tipo->activo = ! $tipo->activo;
        $tipo->save();
    }

    public function confirmDelete(int $id): void
    {
        $tipo = DocEstudianteTipo::query()->findOrFail($id);
        $this->deleteId = $id;
        $this->deleteInfo = '¿Confirma eliminar «'.trim((string) $tipo->etiqueta).'»? Los PDF ya subidos por las familias no se borran del servidor.';
        $this->showConfirm = true;
    }

    public function delete(): void
    {
        if ($this->deleteId) {
            DocEstudianteTipo::query()->whereKey($this->deleteId)->delete();
            session()->flash('success', 'Documento eliminado de la parametrización.');
        }

        $this->showConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $tipos = DocEstudianteTipo::query()->ordenados()->get();

        return view('livewire.matricula-web.documentos-estudiante-tipos-index', [
            'tipos' => $tipos,
            'extensionesDisponibles' => DocEstudianteTipo::EXTENSIONES_SOPORTADAS,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Documentos a subir — Matrícula web']);
    }
}
