<?php

namespace App\Livewire\Parametrizacion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Mail\MailInstitucionalConfig;
use App\Support\PermisosConfiguracion;
use App\Models\Ento;
use App\Models\Terlec;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
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

    public string $activeTab = 'institucion';

    // Tab correo Gmail institucional
    public string $mailGmailUser     = '';
    public string $mailGmailPassword = '';
    /** true si ya hay credenciales en ento (ctaEnvioMail + passEnvioMail) del nivel */
    public bool $mailConfigurado = false;

    public string $insti = '';
    public string $cue = '';
    public string $ee = '';
    public string $cuit = '';
    public string $cuitFact = '';
    public string $domicFact = '';
    public string $condIvaInst = '';
    public string $aporteEstatal = '';
    public string $ptoVta = '';
    public string $afipCertCarpeta = '';
    public string $afipCertKey = '';
    public string $afipCertCrt = '';
    public string $condicionIva = '';
    public string $ingresosBrutos = '';
    public string $fechaInicioAct = '';
    public string $categoria = '';
    public string $direccion = '';
    public string $localidad = '';
    public string $departamento = '';
    public string $provincia = '';
    public string $telefono = '';
    public string $mail = '';
    public string $replegal = '';

    public string $siroPrefijoCPE = '';

    public string $siroMje = '';

    public string $siroIdentCuenta = '';

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public bool $removeLogo = false;

    public ?string $currentLogoUrl = null;

    public int|string $idTerlecVerNotas = '';

    public bool $cargaNotasOff = false;

    public string $notasOffMensaje = '';

    public bool $verNotasOff = false;

    public string $verOffMensaje = '';

    public bool $verBimesOff = false;

    public string $bimesOffMensaje = '';

    public bool $imprBoleOff = false;

    public bool $verDatosFicha = true;

    public string $mensajeBloqPeda = '';

    public string $mensajeBloqAdmi = '';

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['institucion', 'parametros', 'correo'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function mount(): void
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);

        /** @var Ento $ento */
        $ento = Ento::query()->firstOrNew(['idNivel' => $idNivel]);

        $this->insti = (string) ($ento->insti ?? '');
        $this->cue = (string) ($ento->cue ?? '');
        $this->ee = (string) ($ento->ee ?? '');
        $this->cuit = Schema::hasColumn('ento', 'cuit')
            ? (string) ($ento->cuit ?? '')
            : '';
        $this->cuitFact = Schema::hasColumn('ento', 'cuitFact')
            ? (string) ($ento->cuitFact ?? '')
            : '';
        $this->domicFact = Schema::hasColumn('ento', 'domicFact')
            ? (string) ($ento->domicFact ?? '')
            : '';
        $this->condIvaInst = Schema::hasColumn('ento', 'condIvaInst')
            ? (string) ($ento->condIvaInst ?? '')
            : '';
        $this->aporteEstatal = Schema::hasColumn('ento', 'aporteEstatal')
            ? (string) ($ento->aporteEstatal ?? '')
            : '';
        $this->ptoVta = (string) ((int) ($ento->ptoVta ?? 0) ?: '');
        $this->afipCertCarpeta = (string) ($ento->afipCertCarpeta ?? '');
        $this->afipCertKey = (string) ($ento->afipCertKey ?? '');
        $this->afipCertCrt = (string) ($ento->afipCertCrt ?? '');
        $this->condicionIva = (string) ($ento->condicionIva ?? '');
        $this->ingresosBrutos = (string) ($ento->ingresosBrutos ?? '');
        $this->fechaInicioAct = self::fechaAfipParaInput($ento->getAttributes()['fechaInicioAct'] ?? null);
        $this->categoria = (string) ($ento->categoria ?? '');
        $this->direccion = (string) ($ento->direccion ?? '');
        $this->localidad = (string) ($ento->localidad ?? '');
        $this->departamento = (string) ($ento->departamento ?? '');
        $this->provincia = (string) ($ento->provincia ?? '');
        $this->telefono = (string) ($ento->telefono ?? '');
        $this->mail = (string) ($ento->mail ?? '');
        $this->replegal = (string) ($ento->replegal ?? '');

        $this->siroPrefijoCPE = (string) ($ento->siroPrefijoCPE ?? '');
        $this->siroMje = (string) ($ento->siroMje ?? '');
        $this->siroIdentCuenta = (string) ($ento->siroIdentCuenta ?? '');

        $this->currentLogoUrl = schoolLogoUrl();

        $this->cargarParametrosOperativosDesdeEnto($ento);
        $this->cargarMailConfigDesdeEnto();
    }

    protected function rules(): array
    {
        $terlecIds = Terlec::paraSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rules = [
            'insti' => ['nullable', 'string', 'max:120'],
            'cue' => ['nullable', 'string', 'max:30'],
            'ee' => ['nullable', 'string', 'max:30'],
            'cuit' => ['nullable', 'string', 'max:20'],
            'cuitFact' => ['nullable', 'string', 'max:13'],
            'domicFact' => ['nullable', 'string', 'max:100'],
            'condIvaInst' => ['nullable', 'string', 'max:40'],
            'aporteEstatal' => ['nullable', 'string', 'max:10'],
            'ptoVta' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'afipCertCarpeta' => ['nullable', 'string', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'afipCertKey' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'afipCertCrt' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'condicionIva' => ['nullable', 'string', 'max:80'],
            'ingresosBrutos' => ['nullable', 'string', 'max:40'],
            'fechaInicioAct' => ['nullable', 'date'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'localidad' => ['nullable', 'string', 'max:80'],
            'departamento' => ['nullable', 'string', 'max:80'],
            'provincia' => ['nullable', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'mail' => ['nullable', 'email:rfc', 'max:120'],
            'replegal' => ['nullable', 'string', 'max:120'],
            'removeLogo' => ['boolean'],
            'idTerlecVerNotas' => ['nullable', 'integer', Rule::in($terlecIds)],
            'cargaNotasOff' => ['boolean'],
            'notasOffMensaje' => ['nullable', 'string', 'max:500'],
            'verNotasOff' => ['boolean'],
            'verOffMensaje' => ['nullable', 'string', 'max:500'],
            'verBimesOff' => ['boolean'],
            'bimesOffMensaje' => ['nullable', 'string', 'max:500'],
            'imprBoleOff' => ['boolean'],
            'verDatosFicha' => ['boolean'],
            'mensajeBloqPeda' => ['nullable', 'string', 'max:500'],
            'mensajeBloqAdmi' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->puedeEditarCamposSiro()) {
            // Opcionales al guardar: no todos los colegios usan cuotas/SIRO.
            // Si se informan, deben ser válidos; SIRO exige que estén cargados al operar.
            $rules['siroPrefijoCPE'] = ['nullable', 'string', 'regex:/^\d{2}$/'];
            $rules['siroMje'] = ['nullable', 'string', 'max:40'];
            $rules['siroIdentCuenta'] = ['nullable', 'string', 'max:20', 'regex:/^\d+$/', 'not_regex:/^0+$/'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'mail.email' => 'El mail no tiene un formato válido.',
            'afipCertCarpeta.regex' => 'La carpeta de certificados solo puede contener letras, números, guión y guión bajo.',
            'afipCertKey.regex' => 'El nombre del archivo .key no es válido.',
            'afipCertCrt.regex' => 'El nombre del archivo .crt no es válido.',
            'siroPrefijoCPE.regex' => 'El prefijo CPE SIRO debe ser exactamente 2 dígitos (ej. 00, 09).',
            'siroIdentCuenta.regex' => 'La cuenta SIRO solo puede contener dígitos.',
            'siroIdentCuenta.not_regex' => 'La cuenta recaudadora SIRO no puede ser solo ceros.',
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

    public function getLogoPreviewUrlProperty(): ?string
    {
        if ($this->removeLogo && ! ($this->logo instanceof TemporaryUploadedFile)) {
            return null;
        }

        // Archivo recién elegido: vista previa en el navegador (Alpine + blob URL en la vista).
        // temporaryUrl() (ruta firmada livewire.preview-file) suele devolver 401/403 en subcarpeta o HTTPS.
        return $this->currentLogoUrl;
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

        if ($this->facturacionAfipHabilitadaEnTenant()) {
            if (Schema::hasColumn('ento', 'cuitFact') && trim($this->cuitFact) === '') {
                $this->addError('cuitFact', 'El CUIT de facturación es obligatorio para emitir comprobantes AFIP.');

                return;
            }

            $carpeta = trim($this->afipCertCarpeta);
            $key = trim($this->afipCertKey);
            $crt = trim($this->afipCertCrt);
            $alguno = $carpeta !== '' || $key !== '' || $crt !== '';
            $todos = $carpeta !== '' && $key !== '' && $crt !== '';
            if ($alguno && ! $todos) {
                $this->addError('afipCertCarpeta', 'Complete carpeta, archivo .key y archivo .crt de AFIP, o deje los tres vacíos.');

                return;
            }
        }

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
            'ptoVta' => ($v = trim($this->ptoVta)) !== '' ? (int) $v : null,
            'condicionIva' => ($v = trim($this->condicionIva)) !== '' ? $v : null,
            'ingresosBrutos' => ($v = trim($this->ingresosBrutos)) !== '' ? $v : null,
            'fechaInicioAct' => ($v = trim($this->fechaInicioAct)) !== '' ? $v : null,
            'categoria' => ($v = trim($this->categoria)) !== '' ? $v : null,
            'direccion' => ($v = trim($this->direccion)) !== '' ? $v : null,
            'localidad' => ($v = trim($this->localidad)) !== '' ? $v : null,
            'departamento' => ($v = trim($this->departamento)) !== '' ? $v : null,
            'provincia' => ($v = trim($this->provincia)) !== '' ? $v : null,
            'telefono' => ($v = trim($this->telefono)) !== '' ? $v : null,
            'mail' => ($v = trim($this->mail)) !== '' ? $v : null,
            'replegal' => ($v = trim($this->replegal)) !== '' ? $v : null,
        ];

        if ($this->facturacionAfipHabilitadaEnTenant()) {
            $payload['cuitFact'] = ($v = trim($this->cuitFact)) !== '' ? $v : null;
            $payload['domicFact'] = ($v = trim($this->domicFact)) !== '' ? $v : null;
            $payload['condIvaInst'] = ($v = trim($this->condIvaInst)) !== '' ? $v : null;
            $payload['aporteEstatal'] = ($v = trim($this->aporteEstatal)) !== '' ? $v : null;
            $payload['afipCertCarpeta'] = ($v = trim($this->afipCertCarpeta)) !== '' ? $v : null;
            $payload['afipCertKey'] = ($v = trim($this->afipCertKey)) !== '' ? $v : null;
            $payload['afipCertCrt'] = ($v = trim($this->afipCertCrt)) !== '' ? $v : null;
        }

        if ($this->puedeEditarCamposSiro()) {
            $payload['siroPrefijoCPE'] = ($v = trim($this->siroPrefijoCPE)) !== '' ? $v : null;
            $payload['siroMje'] = ($v = trim($this->siroMje)) !== '' ? $v : null;
            $payload['siroIdentCuenta'] = ($v = trim($this->siroIdentCuenta)) !== '' ? $v : null;
        }

        $payload = array_merge($payload, $this->payloadParametrosOperativos());

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

        $preparado = PersistenciaColumnas::prepararPayload('ento', $payload, ['idNivel']);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $this->reportarColumnasEntoFaltantes($preparado['columnas_con_valor_sin_columna']);

            return;
        }

        $payloadEnto = array_merge($preparado['payload'], ['idNivel' => $idNivel]);

        try {
            Ento::query()->updateOrCreate(
                ['idNivel' => $idNivel],
                $payloadEnto,
            );
        } catch (QueryException $e) {
            Log::warning('parametros-sistema: error al guardar en ento', [
                'idNivel' => $idNivel,
                'message' => $e->getMessage(),
            ]);
            $this->reportarErrorPersistenciaEnto(
                'insti',
                PersistenciaColumnas::mensajeDesdeQueryException($e)
                    ?? 'No se pudo guardar en la base de datos. Intente nuevamente o contacte al administrador.',
            );

            return;
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'ento',
            ['idNivel' => $idNivel],
            $preparado['payload'],
        );
        if ($noPersistidas !== []) {
            $this->reportarColumnasEntoNoPersistidas($noPersistidas);

            return;
        }

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

        $this->dispatch('parametros-logo-guardado');

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

    public function saveMailConfig(): void
    {
        $key = 'parametros-mail:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, 60);

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel < 1) {
            $this->dispatch('se-swal-error', mensaje: 'Sin nivel activo en el contexto.');
            return;
        }

        if (! MailInstitucionalConfig::columnasEntoDisponibles()) {
            $this->dispatch(
                'se-swal-error',
                mensaje: 'Faltan columnas ento.ctaEnvioMail / ento.passEnvioMail. Aplicá la migración (php artisan migrate) o el SQL idempotente.'
            );
            return;
        }

        $this->validateOnly('mailGmailUser', [
            'mailGmailUser' => ['required', 'email:rfc', 'max:120', 'regex:/^.+@(gmail\.com|[a-z0-9.-]+\.edu\.ar)$/i'],
        ], [
            'mailGmailUser.required'   => 'La cuenta de Gmail es obligatoria.',
            'mailGmailUser.email'      => 'Ingresá una dirección de correo válida.',
            'mailGmailUser.regex'      => 'Debe ser una cuenta de Gmail (@gmail.com) o institucional Google Workspace (.edu.ar).',
        ]);

        // Contraseña: obligatoria solo si aún no está configurada en ento
        $pwdActual = trim(MailInstitucionalConfig::leer($idNivel)['password']);
        $pwdNueva  = trim($this->mailGmailPassword);

        if ($pwdActual === '' && $pwdNueva === '') {
            $this->addError('mailGmailPassword', 'Ingresá la contraseña de aplicación de Gmail.');
            return;
        }

        if ($pwdNueva !== '' && strlen(str_replace(' ', '', $pwdNueva)) < 8) {
            $this->addError('mailGmailPassword', 'La contraseña de aplicación debe tener al menos 8 caracteres.');
            return;
        }

        $user     = trim($this->mailGmailUser);
        $password = $pwdNueva !== '' ? $pwdNueva : $pwdActual;

        try {
            MailInstitucionalConfig::guardar($user, $password, $idNivel);
        } catch (\Throwable $e) {
            Log::warning('parametros-mail: error al guardar', ['message' => $e->getMessage()]);
            $this->dispatch('se-swal-error', mensaje: 'No se pudo guardar la configuración: '.$e->getMessage());
            return;
        }

        $this->mailGmailPassword = '';
        $this->mailConfigurado   = true;
        $this->cargarMailConfigDesdeEnto();

        $this->dispatch('se-swal-exito', mensaje: 'Configuración de correo guardada en el nivel activo (ento). Probá enviar un comunicado para verificarla.');
    }

    private function cargarMailConfigDesdeEnto(): void
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        $c = MailInstitucionalConfig::leer($idNivel > 0 ? $idNivel : null);

        $this->mailGmailUser     = $c['username'];
        $this->mailGmailPassword = ''; // nunca pre-rellenar la contraseña
        $this->mailConfigurado   = MailInstitucionalConfig::estaConfigurado($idNivel > 0 ? $idNivel : null);
    }

    public function render()
    {
        return view('livewire.parametrizacion.parametros-sistema-form', [
            'nivelNombre' => schoolCtx()->nivelNombre(),
            'facturacionAfipHabilitada' => $this->facturacionAfipHabilitadaEnTenant(),
            'puedeEditarCamposSiro' => $this->puedeEditarCamposSiro(),
            'logoPreviewUrl' => $this->logoPreviewUrl,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Parámetros del sistema']);
    }

    private function puedeEditarCamposSiro(): bool
    {
        return tenantCuotasSiroHabilitado();
    }

    private function facturacionAfipHabilitadaEnTenant(): bool
    {
        return (bool) config('tenant.cuotas.facturacion_afip.habilitado', false);
    }

    private static function fechaAfipParaInput(mixed $valor): string
    {
        return self::fechaLegacyParaInput($valor);
    }

    private static function fechaLegacyParaInput(mixed $valor): string
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
            [$d, $m, $y] = explode('/', $raw);

            return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private function cargarParametrosOperativosDesdeEnto(Ento $ento): void
    {
        $attrs = $ento->getAttributes();

        $this->idTerlecVerNotas = self::terlecIdParaInput($attrs['idTerlecVerNotas'] ?? null);

        $this->cargaNotasOff = self::entoFlagActivo($attrs['cargaNotasOff'] ?? null);
        $this->notasOffMensaje = (string) ($attrs['notasOffMensaje'] ?? '');
        $this->verNotasOff = self::entoFlagActivo($attrs['verNotasOff'] ?? null);
        $this->verOffMensaje = (string) ($attrs['verOffMensaje'] ?? '');
        $this->verBimesOff = self::entoFlagActivo($attrs['verBimesOff'] ?? null);
        $this->bimesOffMensaje = (string) ($attrs['bimesOffMensaje'] ?? '');
        $this->imprBoleOff = self::entoFlagActivo($attrs['imprBoleOff'] ?? null);
        $this->verDatosFicha = Schema::hasColumn('ento', 'verDatosFicha')
            ? self::entoFlagActivo($attrs['verDatosFicha'] ?? 1)
            : true;
        $this->mensajeBloqPeda = (string) ($attrs['mensajeBloqPeda'] ?? '');
        $this->mensajeBloqAdmi = (string) ($attrs['mensajeBloqAdmi'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadParametrosOperativos(): array
    {
        $payload = [];

        $this->asignarTerlecPayload($payload, 'idTerlecVerNotas', $this->idTerlecVerNotas);

        $this->asignarFlagPayload($payload, 'cargaNotasOff', $this->cargaNotasOff);
        $this->asignarTextoPayload($payload, 'notasOffMensaje', $this->notasOffMensaje);
        $this->asignarFlagPayload($payload, 'verNotasOff', $this->verNotasOff);
        $this->asignarTextoPayload($payload, 'verOffMensaje', $this->verOffMensaje);
        $this->asignarFlagPayload($payload, 'verBimesOff', $this->verBimesOff);
        $this->asignarTextoPayload($payload, 'bimesOffMensaje', $this->bimesOffMensaje);
        $this->asignarFlagPayload($payload, 'imprBoleOff', $this->imprBoleOff);
        $this->asignarFlagPayload($payload, 'verDatosFicha', $this->verDatosFicha);
        $this->asignarTextoPayload($payload, 'mensajeBloqPeda', $this->mensajeBloqPeda);
        $this->asignarTextoPayload($payload, 'mensajeBloqAdmi', $this->mensajeBloqAdmi);

        return $payload;
    }

    /**
     * Valores vacíos pueden omitirse si la columna no existe (docs §14).
     * Si el usuario cargó un valor y falta la columna, {@see PersistenciaColumnas::prepararPayload} reporta error.
     */
    private function asignarTerlecPayload(array &$payload, string $columna, int|string $valor): void
    {
        $id = trim((string) $valor);
        if ($id !== '') {
            $payload[$columna] = (int) $id;

            return;
        }

        if ($this->entoTieneColumna($columna)) {
            $payload[$columna] = null;
        }
    }

    private function asignarFlagPayload(array &$payload, string $columna, bool $activo): void
    {
        if ($activo) {
            $payload[$columna] = 1;

            return;
        }

        if ($this->entoTieneColumna($columna)) {
            $payload[$columna] = 0;
        }
    }

    private function asignarTextoPayload(array &$payload, string $columna, string $valor): void
    {
        $texto = trim($valor);
        if ($texto !== '') {
            $payload[$columna] = $texto;

            return;
        }

        if ($this->entoTieneColumna($columna)) {
            $payload[$columna] = null;
        }
    }

    private function entoTieneColumna(string $columna): bool
    {
        return Schema::hasTable('ento') && Schema::hasColumn('ento', $columna);
    }

    /**
     * @param  list<string>  $columnas
     */
    private function reportarColumnasEntoFaltantes(array $columnas): void
    {
        $mensaje = PersistenciaColumnas::mensajeColumnasInexistentes('ento', $columnas);
        $this->reportarErrorPersistenciaEnto($columnas[0] ?? 'insti', $mensaje);
    }

    /**
     * @param  list<string>  $columnas
     */
    private function reportarColumnasEntoNoPersistidas(array $columnas): void
    {
        $mensaje = PersistenciaColumnas::mensajeColumnasNoPersistidas('ento', $columnas);
        $this->reportarErrorPersistenciaEnto($columnas[0] ?? 'insti', $mensaje);
    }

    private function reportarErrorPersistenciaEnto(string $columna, string $mensaje): void
    {
        $campo = $this->campoFormularioParaColumnaEnto($columna) ?? 'insti';
        $this->addError($campo, $mensaje);

        if ($campo !== 'insti' && $this->activeTab !== 'parametros') {
            $this->addError('insti', $mensaje);
        }

        $this->dispatch('se-swal-error', mensaje: $mensaje);
    }

    private function campoFormularioParaColumnaEnto(string $columna): ?string
    {
        return match ($columna) {
            'idTerlecVerNotas' => 'idTerlecVerNotas',
            'logo_path', 'logo_original_name' => 'logo',
            default => $columna,
        };
    }

    private static function terlecIdParaInput(mixed $valor): int|string
    {
        $id = (int) ($valor ?? 0);

        return $id > 0 ? $id : '';
    }

    private static function entoFlagActivo(mixed $valor): bool
    {
        return (int) ($valor ?? 0) === 1;
    }
}

