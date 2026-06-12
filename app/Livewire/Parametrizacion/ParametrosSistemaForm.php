<?php

namespace App\Livewire\Parametrizacion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Models\Ento;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ParametrosSistemaForm extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::PARAMETROS_SISTEMA;
    }
    use WithFileUploads;

    public string $insti = '';
    public string $cue = '';
    public string $ee = '';
    public string $cuit = '';
    public string $categoria = '';
    public string $direccion = '';
    public string $localidad = '';
    public string $departamento = '';
    public string $provincia = '';
    public string $telefono = '';
    public string $mail = '';
    public string $replegal = '';

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public bool $removeLogo = false;

    public ?string $currentLogoUrl = null;

    public function mount(): void
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);

        /** @var Ento $ento */
        $ento = Ento::query()->firstOrNew(['idNivel' => $idNivel]);

        $this->insti = (string) ($ento->insti ?? '');
        $this->cue = (string) ($ento->cue ?? '');
        $this->ee = (string) ($ento->ee ?? '');
        $this->cuit = (string) ($ento->cuit ?? '');
        $this->categoria = (string) ($ento->categoria ?? '');
        $this->direccion = (string) ($ento->direccion ?? '');
        $this->localidad = (string) ($ento->localidad ?? '');
        $this->departamento = (string) ($ento->departamento ?? '');
        $this->provincia = (string) ($ento->provincia ?? '');
        $this->telefono = (string) ($ento->telefono ?? '');
        $this->mail = (string) ($ento->mail ?? '');
        $this->replegal = (string) ($ento->replegal ?? '');

        $this->currentLogoUrl = schoolLogoUrl();
    }

    protected function rules(): array
    {
        return [
            'insti' => ['nullable', 'string', 'max:120'],
            'cue' => ['nullable', 'string', 'max:30'],
            'ee' => ['nullable', 'string', 'max:30'],
            'cuit' => ['nullable', 'string', 'max:20'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'localidad' => ['nullable', 'string', 'max:80'],
            'departamento' => ['nullable', 'string', 'max:80'],
            'provincia' => ['nullable', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'mail' => ['nullable', 'email:rfc', 'max:120'],
            'replegal' => ['nullable', 'string', 'max:120'],
            'removeLogo' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'mail.email' => 'El mail no tiene un formato válido.',
        ];
    }

    public function updatedLogo(): void
    {
        $this->resetValidation('logo');

        if ($this->logo === null) {
            return;
        }

        if (! $this->logo instanceof TemporaryUploadedFile) {
            return;
        }

        $this->removeLogo = false;

        $error = $this->validarLogoSubido($this->logo);
        if ($error !== null) {
            $this->addError('logo', $error);
            $this->logo = null;
        }
    }

    /** Llamado desde el navegador cuando Livewire no puede subir el archivo temporal. */
    public function onLogoUploadFailed(): void
    {
        $this->addError(
            'logo',
            'No se pudo subir el archivo al servidor. Compruebe tamaño (máx. 2 MB), formato JPG/PNG y que la sesión siga activa.'
        );
    }

    public function save(): void
    {
        $key = 'parametros-sistema:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('insti', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        if ($this->logo instanceof TemporaryUploadedFile) {
            $errorLogo = $this->validarLogoSubido($this->logo);
            if ($errorLogo !== null) {
                $this->addError('logo', $errorLogo);

                return;
            }
        } elseif ($this->logo !== null) {
            $this->addError(
                'logo',
                'La subida del logo no finalizó. Espere a que desaparezca «Subiendo archivo…» y vuelva a pulsar Guardar.'
            );

            return;
        }

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            abort(403);
        }

        $payload = [
            'insti' => ($v = trim($this->insti)) !== '' ? $v : null,
            'cue' => ($v = trim($this->cue)) !== '' ? $v : null,
            'ee' => ($v = trim($this->ee)) !== '' ? $v : null,
            'cuit' => ($v = trim($this->cuit)) !== '' ? $v : null,
            'categoria' => ($v = trim($this->categoria)) !== '' ? $v : null,
            'direccion' => ($v = trim($this->direccion)) !== '' ? $v : null,
            'localidad' => ($v = trim($this->localidad)) !== '' ? $v : null,
            'departamento' => ($v = trim($this->departamento)) !== '' ? $v : null,
            'provincia' => ($v = trim($this->provincia)) !== '' ? $v : null,
            'telefono' => ($v = trim($this->telefono)) !== '' ? $v : null,
            'mail' => ($v = trim($this->mail)) !== '' ? $v : null,
            'replegal' => ($v = trim($this->replegal)) !== '' ? $v : null,
        ];

        $logoPathEsperado = null;

        /** @var Ento|null $entoActual */
        $entoActual = Ento::query()->where('idNivel', $idNivel)->first();

        // Logo: remove tiene prioridad; si luego se sube nuevo, se reemplaza.
        if ($this->removeLogo) {
            $old = (string) ($entoActual?->logo_path ?? '');
            if ($old !== '') {
                Storage::disk('public')->delete($old);
            }
            $payload['logo_path'] = null;
            $payload['logo_original_name'] = null;
        }

        if ($this->logo instanceof TemporaryUploadedFile) {
            $logoPath = $this->persistLogoFile($idNivel, (string) ($entoActual?->logo_path ?? ''));
            if ($logoPath === null) {
                return;
            }

            $payload['logo_path'] = $logoPath;
            $payload['logo_original_name'] = (string) $this->logo->getClientOriginalName();
            $logoPathEsperado = $logoPath;
        }

        Ento::query()->updateOrCreate(
            ['idNivel' => $idNivel],
            array_merge($payload, ['idNivel' => $idNivel]),
        );

        if ($logoPathEsperado !== null) {
            $persistido = trim((string) Ento::query()
                ->where('idNivel', $idNivel)
                ->value('logo_path'));

            if ($persistido !== $logoPathEsperado) {
                $this->addError(
                    'logo',
                    'El archivo se subió pero no quedó registrado en la base de datos. Verifique que existan las columnas ento.logo_path y ento.logo_original_name.'
                );

                return;
            }
        }

        $this->currentLogoUrl = schoolLogoUrl();
        $this->logo = null;
        $this->removeLogo = false;

        session()->flash('success', 'Parámetros del sistema actualizados.');
    }

    /**
     * Validación del logo sin regla Laravel «image» (en Livewire suele fallar isValid() y
     * mostrar un falso error de upload_max_filesize aunque el archivo sea pequeño).
     */
    private function validarLogoSubido(TemporaryUploadedFile $file): ?string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return 'El logo debe ser JPG/JPEG/PNG.';
        }

        $bytes = (int) ($file->getSize() ?? 0);
        if ($bytes < 1) {
            return 'El archivo está vacío o no se terminó de subir. Espere a que desaparezca «Subiendo archivo…» y vuelva a seleccionarlo.';
        }

        if ($bytes > 2 * 1024 * 1024) {
            return 'El logo no puede superar los 2 MB.';
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            $path = method_exists($file, 'path') ? $file->path() : null;
        }
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return 'No se pudo leer el archivo en el servidor. Verifique permisos de escritura en storage/app/livewire-tmp.';
        }

        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            return 'El archivo seleccionado no es una imagen válida (JPG/PNG).';
        }

        return null;
    }

    /**
     * Guarda el logo en storage/app/public/ento/logos/{tenant}/nivel-{id}.
     *
     * @return string|null Ruta relativa al disco public, o null si falló (ya se agregó error al formulario).
     */
    private function persistLogoFile(int $idNivel, string $previousPath): ?string
    {
        if (! $this->logo instanceof TemporaryUploadedFile) {
            return null;
        }

        $dir = 'ento/logos/'.tenantSlug().'/nivel-'.$idNivel;
        $ext = strtolower((string) $this->logo->getClientOriginalExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $ext = 'jpg';
        }
        $filename = 'logo.'.$ext;

        $disk = Storage::disk('public');

        try {
            $disk->makeDirectory($dir, 0755, true);

            $newPath = $this->logo->storeAs($dir, $filename, 'public');
        } catch (\Throwable $e) {
            Log::warning('parametros-sistema: error al guardar logo', [
                'dir' => $dir,
                'message' => $e->getMessage(),
            ]);
            $newPath = false;
        }

        if (! is_string($newPath) || $newPath === '' || ! $disk->exists($newPath)) {
            $this->addError(
                'logo',
                'No se pudo guardar el archivo del logo. En el servidor: permisos de escritura en storage/app/public (y storage/app/livewire-tmp), ejecutar php artisan storage:link, y TENANT_SLUG definido en .env antes de config:cache.'
            );

            return null;
        }

        if ($previousPath !== '' && $previousPath !== $newPath) {
            $disk->delete($previousPath);
        }

        return $newPath;
    }

    public function render()
    {
        return view('livewire.parametrizacion.parametros-sistema-form', [
            'nivelNombre' => schoolCtx()->nivelNombre(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Parámetros del sistema']);
    }
}

