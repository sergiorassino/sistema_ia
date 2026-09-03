<?php

use App\Livewire\Alumnos\ActualizacionDatosPersonalesEstandarForm;
use App\Livewire\Alumnos\ActualizacionDatosPersonalesSanFranciscoAsisForm;
use App\Livewire\Alumnos\ArancelesEscolaresGestionIndex;
use App\Livewire\Alumnos\ArancelesEscolaresIndex;
use App\Models\Ento;
use App\Models\Profesor;
use App\Push\WebPushService;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cuotas\CuotasImportesCatalog;
use App\Support\DocPp\DocPpConsulta;
use App\Support\MatriculaWeb\MatriculaWebDocumentos;
use App\Support\NivelSistema;
use App\Support\PermisosConfiguracion;
use App\Support\PermisosIaCatalog;
use App\Support\ProfesorMenuPortal;
use App\Support\SchoolAlcancePedagogico;
use App\Support\SchoolContext;
use App\Support\StudentContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

if (! function_exists('se_route_url')) {
    /**
     * URL absoluta en el host de la petición actual, con la subcarpeta de APP_URL.
     *
     * No usa el host de APP_URL: si el usuario entró por 127.0.0.1 y APP_URL es
     * localhost (o www vs sin www), la cookie de sesión no viaja y el login
     * de autogestión parece “cerrarse” al elegir una opción del menú.
     */
    function se_route_url(string $name, mixed $parameters = []): string
    {
        $relative = route($name, $parameters, false);
        $appUrl = rtrim((string) config('app.url'), '/');
        $appPath = rtrim((string) (parse_url($appUrl, PHP_URL_PATH) ?: ''), '/');

        if ($appPath !== '' && ($relative === $appPath || str_starts_with($relative, $appPath.'/'))) {
            $relative = substr($relative, strlen($appPath)) ?: '/';
        }

        $root = $appUrl;
        if (app()->bound('request')) {
            $request = request();
            $host = trim((string) $request->getHost());
            if ($host !== '') {
                $root = rtrim($request->getSchemeAndHttpHost(), '/').$appPath;
            }
        }

        return $root.$relative;
    }
}

if (! function_exists('tenantSlug')) {
    /**
     * Identificador del despliegue (TENANT_SLUG) saneado para rutas de almacenamiento.
     */
    function tenantSlug(): string
    {
        $slug = trim((string) config('tenant.slug', ''));
        if ($slug === '') {
            $slug = 'default';
        }

        $slug = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $slug) ?? 'default';
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? strtolower($slug) : 'default';
    }
}

if (! function_exists('schoolCtx')) {
    function schoolCtx(): SchoolContext
    {
        return app(SchoolContext::class);
    }
}

if (! function_exists('studentCtx')) {
    function studentCtx(): StudentContext
    {
        return app(StudentContext::class);
    }
}

if (! function_exists('studentEsNivelSecundario')) {
    /**
     * Nivel secundario / medio en el portal alumno (nombre en tabla `niveles`).
     */
    function studentEsNivelSecundario(): bool
    {
        $nombre = mb_strtolower((string) studentCtx()->nivelNombre());

        return str_contains($nombre, 'secundari') || str_contains($nombre, 'medio');
    }
}

if (! function_exists('studentEsNivelPrimario')) {
    /**
     * Nivel primario en el portal alumno (`niveles.id` = 2).
     */
    function studentEsNivelPrimario(): bool
    {
        return NivelSistema::esPrimario((int) (studentCtx()->idNivel ?? 0));
    }
}

if (! function_exists('profesorEsSecretario')) {
    function profesorEsSecretario(?Profesor $profesor = null): bool
    {
        return ProfesorMenuPortal::esSecretario($profesor);
    }
}

if (! function_exists('schoolEsAdministracion')) {
    function schoolEsAdministracion(): bool
    {
        return schoolCtx()->esAdministracion();
    }
}

if (! function_exists('schoolEsNivelSecundario')) {
    /**
     * Nivel secundario en sesión de secretaría (`niveles.id` = 3).
     */
    function schoolEsNivelSecundario(): bool
    {
        return NivelSistema::esSecundario((int) (schoolCtx()->idNivel ?? 0));
    }
}

if (! function_exists('layoutMenuStaff')) {
    /** Layout del portal staff: Administración o Secretaría pedagógica. */
    function layoutMenuStaff(): string
    {
        return ProfesorMenuPortal::layoutStaff();
    }
}

if (! function_exists('schoolIdNivelPedagogico')) {
    /**
     * Nivel único de filtro (login 1–4). En Administración devuelve 0: usar
     * {@see SchoolAlcancePedagogico::aplicarFiltroColumnaNivel()}.
     */
    function schoolIdNivelPedagogico(): int
    {
        return (int) (SchoolAlcancePedagogico::idNivelFiltroUnico() ?? 0);
    }
}

if (! function_exists('puedeModificarLegajosEstudiantes')) {
    /**
     * Niveles pedagógicos (1–4): permiso orden 2.
     * Nivel Administración (5): permiso orden 47 (cualquier nivel pedagógico) u orden 2.
     */
    function puedeModificarLegajosEstudiantes(): bool
    {
        if (schoolEsAdministracion()) {
            return tienePermiso(PermisosIaCatalog::LEGAJOS_MODIFICAR_ADMIN);
        }

        return tienePermiso(PermisosIaCatalog::LEGAJOS_ESTUDIANTES);
    }
}

if (! function_exists('puedeConsultarLegajosEstudiantes')) {
    /**
     * Consulta de legajos y listados: todos los usuarios del Menú de Secretaría.
     * La edición/alta/baja queda en {@see puedeModificarLegajosEstudiantes()} (permiso orden 2).
     */
    function puedeConsultarLegajosEstudiantes(): bool
    {
        return true;
    }
}

if (! function_exists('puedeConsultarLegajosDocentes')) {
    /**
     * Consulta de legajos y listado de docentes (apellido, nombre, DNI).
     * Con permiso orden 11: alta/edición/baja y datos completos ({@see puedeModificarLegajosDocentes()}).
     */
    function puedeConsultarLegajosDocentes(): bool
    {
        return true;
    }
}

if (! function_exists('puedeModificarLegajosDocentes')) {
    /**
     * Crear, editar y eliminar legajos de docentes con todos los campos (permiso orden 11).
     * Sin este permiso solo se consultan apellido, nombre y DNI (y listados PDF/Excel limitados a esos campos).
     */
    function puedeModificarLegajosDocentes(): bool
    {
        return tienePermiso(\App\Support\PermisosIaCatalog::LEGAJOS_DOCENTES);
    }
}

if (! function_exists('puedeVerDatosPersonalesDocentes')) {
    /**
     * Datos personales completos de docentes en legajo, PDF y Excel.
     * Equivale al permiso orden 11 ({@see puedeModificarLegajosDocentes()}).
     */
    function puedeVerDatosPersonalesDocentes(): bool
    {
        return puedeModificarLegajosDocentes();
    }
}

if (! function_exists('tienePermiso')) {
    /**
     * Permiso concedido en profesores.permisos_ia (cadena 0/1 por orden del catálogo permisos_ia).
     */
    function tienePermiso(int $orden): bool
    {
        if ($orden < 0) {
            return false;
        }

        $profesor = schoolCtx()->profesor();
        if (! $profesor) {
            return false;
        }

        $permisos = trim((string) ($profesor->permisos_ia ?? ''));
        if ($permisos === '') {
            return false;
        }

        return ($permisos[$orden] ?? '0') === '1';
    }
}

if (! function_exists('tienePermisoConfig')) {
    function tienePermisoConfig(int $orden): bool
    {
        return PermisosConfiguracion::tiene($orden);
    }
}

if (! function_exists('seSidebarTooltip')) {
    /**
     * Tooltip del sidebar (Menú de Secretaría): descripción + orden del permiso que controla el ítem.
     *
     * @param  int|list<int>|null  $permiso  Orden en permisos_ia; null si el ítem no exige permiso concreto.
     */
    function seSidebarTooltip(string $descripcion, int|array|null $permiso = null): string
    {
        if ($permiso === null) {
            return $descripcion;
        }

        $ordenes = is_array($permiso) ? $permiso : [$permiso];
        $ordenes = array_values(array_unique(array_map('intval', $ordenes)));
        sort($ordenes);

        if ($ordenes === []) {
            return $descripcion;
        }

        $suffix = count($ordenes) === 1
            ? ' · Permiso '.$ordenes[0]
            : ' · Permisos '.implode(', ', $ordenes);

        return $descripcion.$suffix;
    }
}

if (! function_exists('tieneAlgunPermisoConfiguracion')) {
    function tieneAlgunPermisoConfiguracion(): bool
    {
        return PermisosConfiguracion::tieneAlgunAccesoMenu();
    }
}

if (! function_exists('ensurePublicStorageFileAccessible')) {
    /**
     * Garantiza que un archivo relativo a /storage exista en public/storage (servido por la web).
     * Si solo está en storage/app/public (subidas previas sin enlace), lo copia allí.
     */
    function ensurePublicStorageFileAccessible(string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return false;
        }

        $webAbsolute = public_path('storage/'.$relativePath);
        if (is_file($webAbsolute)) {
            return true;
        }

        $legacyAbsolute = storage_path('app/public/'.$relativePath);
        if (! is_file($legacyAbsolute)) {
            return false;
        }

        $webDir = dirname($webAbsolute);
        if (! is_dir($webDir) && ! @mkdir($webDir, 0755, true) && ! is_dir($webDir)) {
            return false;
        }

        return @copy($legacyAbsolute, $webAbsolute) || is_file($webAbsolute);
    }
}

if (! function_exists('publicStorageRelativePathExists')) {
    function publicStorageRelativePathExists(string $relativePath): bool
    {
        return ensurePublicStorageFileAccessible($relativePath);
    }
}

if (! function_exists('schoolLogoStoragePath')) {
    function schoolLogoStoragePath(bool $refresh = false): ?string
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return null;
        }

        $path = Ento::query()
            ->where('idNivel', $idNivel)
            ->value('logo_path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (! publicStorageRelativePathExists($path)) {
            return null;
        }

        return $path;
    }
}

if (! function_exists('schoolLogoUrl')) {
    function schoolLogoUrl(bool $refresh = false): ?string
    {
        $path = schoolLogoStoragePath($refresh);

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }
}

if (! function_exists('schoolLogoForma')) {
    /**
     * Presentación del logo: `horizontal` (apaisado) o `emblema` (sello circular/cuadrado).
     */
    function schoolLogoForma(): string
    {
        $forma = strtolower(trim((string) config('tenant.institucional.logo_forma', 'horizontal')));

        return in_array($forma, ['emblema', 'horizontal'], true) ? $forma : 'horizontal';
    }
}

if (! function_exists('schoolLogoEsEmblema')) {
    function schoolLogoEsEmblema(): bool
    {
        return schoolLogoForma() === 'emblema';
    }
}

if (! function_exists('studentLogoStoragePath')) {
    function studentLogoStoragePath(): ?string
    {
        $idNivel = (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return null;
        }

        $path = Ento::query()
            ->where('idNivel', $idNivel)
            ->value('logo_path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (! publicStorageRelativePathExists($path)) {
            return null;
        }

        return $path;
    }
}

if (! function_exists('studentLogoUrl')) {
    function studentLogoUrl(): ?string
    {
        $path = studentLogoStoragePath();

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }
}

if (! function_exists('entoLoginLogoStoragePath')) {
    /**
     * Logo institucional de login (`ento.logo_login_path`), replicado en todos los niveles.
     */
    function entoLoginLogoStoragePath(): ?string
    {
        if (! Schema::hasTable('ento') || ! Schema::hasColumn('ento', 'logo_login_path')) {
            return null;
        }

        $path = Ento::query()
            ->whereNotNull('logo_login_path')
            ->where('logo_login_path', '<>', '')
            ->orderBy('idNivel')
            ->value('logo_login_path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (! publicStorageRelativePathExists($path)) {
            return null;
        }

        return $path;
    }
}

if (! function_exists('entoLoginLogoUrl')) {
    function entoLoginLogoUrl(): ?string
    {
        $path = entoLoginLogoStoragePath();

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }
}

if (! function_exists('guestBrandLogoUrl')) {
    /**
     * Logo en pantallas de login (staff y alumnos): logo institucional de login,
     * primer logo por nivel, o imagen genérica del sistema.
     */
    function guestBrandLogoUrl(): string
    {
        return entoLoginLogoUrl()
            ?: entoInstitutionalLogoUrlFallback()
            ?: asset('img/3.png');
    }
}

if (! function_exists('entoInstitutionalLogoStoragePath')) {
    /**
     * Primer `logo_path` definido en `ento` (cualquier nivel).
     */
    function entoInstitutionalLogoStoragePath(): ?string
    {
        $path = Ento::query()
            ->whereNotNull('logo_path')
            ->where('logo_path', '<>', '')
            ->orderBy('idNivel')
            ->value('logo_path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (! publicStorageRelativePathExists($path)) {
            return null;
        }

        return $path;
    }
}

if (! function_exists('entoInstitutionalLogoUrlFallback')) {
    /**
     * Primer logo institucional definido en `ento` (cualquier nivel).
     * Misma fuente que `schoolLogoUrl()` / `studentLogoUrl()` para pantallas sin
     * contexto de nivel (login de estudiantes) o si el nivel activo no tiene `logo_path`.
     */
    function entoInstitutionalLogoUrlFallback(): ?string
    {
        $path = entoInstitutionalLogoStoragePath();

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }
}

if (! function_exists('entoInstitutionalNombre')) {
    /**
     * Primer `ento.insti` no vacío (cualquier nivel). Útil en pantallas públicas sin schoolCtx.
     */
    function entoInstitutionalNombre(): string
    {
        static $memo = null;

        if ($memo !== null) {
            return $memo;
        }

        $insti = trim((string) (Ento::query()
            ->whereNotNull('insti')
            ->where('insti', '<>', '')
            ->orderBy('idNivel')
            ->value('insti') ?? ''));

        return $memo = $insti;
    }
}

if (! function_exists('matriculaWebDocumentoUrl')) {
    /**
     * URL para ver/descargar un PDF de aceptación de matrícula web del nivel indicado (o el activo en secretaría).
     *
     * @param  string  $tipo  compromiso|aec|normas|traslado
     */
    function matriculaWebDocumentoUrl(string $tipo, ?int $idNivel = null): ?string
    {
        if (! MatriculaWebDocumentos::claveValida($tipo)) {
            return null;
        }

        if (MatriculaWebDocumentos::nombreRegistrado($tipo, $idNivel) === null) {
            return null;
        }

        if (MatriculaWebDocumentos::pathAlmacenado($tipo, $idNivel) === null) {
            return null;
        }

        if (auth('alumno')->check()) {
            return route('alumnos.documentos-aceptacion.archivo', ['tipo' => $tipo]);
        }

        return route('matricula-web.documentos.archivo', ['tipo' => $tipo]);
    }
}

if (! function_exists('seAssetVersioned')) {
    /**
     * asset() con ?v=filemtime para iconos (mismo criterio que SILAVET, con bust de caché).
     */
    function seAssetVersioned(string $path): string
    {
        $path = ltrim($path, '/');
        $url = asset($path);
        $file = public_path($path);
        if (is_file($file)) {
            $url .= '?v='.(string) filemtime($file);
        }

        return $url;
    }
}

if (! function_exists('seMonogramFaviconUrls')) {
    /**
     * Favicon de pestaña: círculo blanco con letras SE (gris oscuro), mismo criterio que SILAVET.
     *
     * @return array{light: string, dark: string}
     */
    function seMonogramFaviconUrls(): array
    {
        $url = seAssetVersioned('img/favicon-32.png');

        return [
            'light' => $url,
            'dark' => $url,
        ];
    }
}

if (! function_exists('institutionalFaviconUrl')) {
    /**
     * URL del favicon institucional (círculo SE).
     *
     * @deprecated Preferir seMonogramFaviconUrls() en vistas; se mantiene por compatibilidad.
     */
    function institutionalFaviconUrl(?callable $contextLogo = null): string
    {
        return seMonogramFaviconUrls()['light'];
    }
}

if (! function_exists('pdfHeaderLogoAbsolutePath')) {
    /**
     * Ruta absoluta al logo del colegio para PDFs (nunca el genérico `public/img/3.png`).
     */
    function pdfHeaderLogoAbsolutePath(array $header = []): ?string
    {
        $logo = $header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            return $logo;
        }

        foreach ([studentLogoStoragePath(), schoolLogoStoragePath()] as $relativePath) {
            if (! is_string($relativePath) || trim($relativePath) === '') {
                continue;
            }

            $abs = Storage::disk('public')->path(trim($relativePath));
            if (is_file($abs)) {
                return $abs;
            }
        }

        $path = entoInstitutionalLogoStoragePath();
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $abs = Storage::disk('public')->path(trim($path));

        return is_file($abs) ? $abs : null;
    }
}

if (! function_exists('schoolPdfHeaderData')) {
    /**
     * Datos institucionales para encabezados de PDFs (Dompdf).
     *
     * @return array{insti:string,direccion:string,localidad:string,provincia:string,cue:string,ee:string,logo_file:?string}
     */
    function schoolPdfHeaderData(): array
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return [
                'insti' => '',
                'direccion' => '',
                'localidad' => '',
                'provincia' => '',
                'cue' => '',
                'ee' => '',
                'logo_file' => null,
            ];
        }

        $columnasEnto = ['insti', 'direccion', 'localidad', 'cue', 'ee', 'logo_path'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('ento', 'provincia')) {
            $columnasEnto[] = 'provincia';
        }

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first($columnasEnto);

        $insti = trim((string) ($ento?->insti ?? ''));
        $direccion = trim((string) ($ento?->direccion ?? ''));
        $localidad = trim((string) ($ento?->localidad ?? ''));
        $provincia = trim((string) ($ento?->provincia ?? ''));
        $cue = trim((string) ($ento?->cue ?? ''));
        $ee = trim((string) ($ento?->ee ?? ''));

        $logoFile = null;
        $logoPath = trim((string) ($ento?->logo_path ?? ''));
        if ($logoPath !== '') {
            $abs = Storage::disk('public')->path($logoPath);
            if (is_string($abs) && $abs !== '' && file_exists($abs)) {
                $logoFile = $abs;
            }
        }
        if ($logoFile === null) {
            $fallbackPath = entoInstitutionalLogoStoragePath();
            if (is_string($fallbackPath) && $fallbackPath !== '') {
                $abs = Storage::disk('public')->path($fallbackPath);
                if (is_string($abs) && $abs !== '' && file_exists($abs)) {
                    $logoFile = $abs;
                }
            }
        }

        return [
            'insti' => $insti,
            'direccion' => $direccion,
            'localidad' => $localidad,
            'provincia' => $provincia,
            'cue' => $cue,
            'ee' => $ee,
            'logo_file' => $logoFile,
        ];
    }
}

if (! function_exists('studentPdfHeaderData')) {
    /**
     * Encabezado institucional para PDFs del portal alumno (Dompdf), según `studentCtx()->idNivel`.
     *
     * @return array{insti:string,direccion:string,localidad:string,provincia:string,cue:string,ee:string,logo_file:?string}
     */
    function studentPdfHeaderData(): array
    {
        $idNivel = (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return [
                'insti' => '',
                'direccion' => '',
                'localidad' => '',
                'provincia' => '',
                'cue' => '',
                'ee' => '',
                'logo_file' => null,
            ];
        }

        $columnasEnto = ['insti', 'direccion', 'localidad', 'cue', 'ee', 'logo_path'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('ento', 'provincia')) {
            $columnasEnto[] = 'provincia';
        }

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first($columnasEnto);

        $insti = trim((string) ($ento?->insti ?? ''));
        $direccion = trim((string) ($ento?->direccion ?? ''));
        $localidad = trim((string) ($ento?->localidad ?? ''));
        $provincia = trim((string) ($ento?->provincia ?? ''));
        $cue = trim((string) ($ento?->cue ?? ''));
        $ee = trim((string) ($ento?->ee ?? ''));

        $logoFile = null;
        $logoPath = trim((string) ($ento?->logo_path ?? ''));
        if ($logoPath !== '') {
            $abs = Storage::disk('public')->path($logoPath);
            if (is_string($abs) && $abs !== '' && file_exists($abs)) {
                $logoFile = $abs;
            }
        }
        if ($logoFile === null) {
            $fallbackPath = entoInstitutionalLogoStoragePath();
            if (is_string($fallbackPath) && $fallbackPath !== '') {
                $abs = Storage::disk('public')->path($fallbackPath);
                if (is_string($abs) && $abs !== '' && file_exists($abs)) {
                    $logoFile = $abs;
                }
            }
        }

        return [
            'insti' => $insti,
            'direccion' => $direccion,
            'localidad' => $localidad,
            'provincia' => $provincia,
            'cue' => $cue,
            'ee' => $ee,
            'logo_file' => $logoFile,
        ];
    }
}

if (! function_exists('schoolNombre')) {
    /**
     * Nombre institucional del colegio para el nivel activo en sesión.
     * Lee `ento.insti` filtrado por `schoolCtx()->idNivel`.
     * Sin contexto (pantallas públicas): primer `ento.insti`, luego `config('tenant.nombre')`, luego 'Colegio'.
     */
    function schoolNombre(): string
    {
        static $memo = null;

        if ($memo !== null) {
            return $memo;
        }

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);

        if ($idNivel > 0) {
            $insti = trim((string) (Ento::query()
                ->where('idNivel', $idNivel)
                ->value('insti') ?? ''));

            if ($insti !== '') {
                return $memo = $insti;
            }
        }

        $desdeEnto = entoInstitutionalNombre();
        if ($desdeEnto !== '') {
            return $memo = $desdeEnto;
        }

        return $memo = (string) config('tenant.nombre', 'Colegio');
    }
}

if (! function_exists('tenantCuotasFormulasInicialesPlantilla')) {
    /**
     * Fórmulas por defecto al crear plantilla de cuota (bonif./interés por vencimiento).
     * Defaults en `config/tenant.php`; override en `config/tenants/{slug}.php`.
     *
     * @return array<string, float|string>
     */
    function tenantCuotasFormulasInicialesPlantilla(): array
    {
        return CuotasImportesCatalog::valoresInicialesRegistro();
    }
}

if (! function_exists('tenantCuotasSiroHabilitado')) {
    /**
     * Si el colegio usa SIRO como medio de pago (código electrónico, QR y barras en cupones).
     * Default false; activar en `config/tenants/{slug}.php` → `cuotas.siro.habilitado`.
     */
    function tenantCuotasSiroHabilitado(): bool
    {
        return (bool) config('tenant.cuotas.siro.habilitado', false);
    }
}

if (! function_exists('tenantCuotasSiroCpePrefijo')) {
    /**
     * Prefijo de 2 dígitos del CPE SIRO en config del tenant (opcional, solo documentación / legacy).
     * La generación de CPE usa exclusivamente {@see Ento::$siroPrefijoCPE} del nivel (sin este fallback).
     */
    function tenantCuotasSiroCpePrefijo(): ?string
    {
        if (! tenantCuotasSiroHabilitado()) {
            return null;
        }

        $prefijo = trim((string) config('tenant.cuotas.siro.cpe_prefijo', ''));
        if (preg_match('/^\d{2}$/', $prefijo) === 1) {
            return $prefijo;
        }

        return null;
    }
}

if (! function_exists('tenantCuotasSiroDescargaRendicionCanalesPlanilla')) {
    /**
     * Medios de pago (cuotastipopago) ofrecidos al crear planilla de rendición SIRO.
     * Vacío = todos. Override en `config/tenants/{slug}.php` → `cuotas.siro.descarga_rendicion.canales_planilla`.
     *
     * @return list<string>
     */
    function tenantCuotasSiroDescargaRendicionCanalesPlanilla(): array
    {
        if (! tenantCuotasSiroHabilitado()) {
            return [];
        }

        $canales = config('tenant.cuotas.siro.descarga_rendicion.canales_planilla', []);

        return is_array($canales)
            ? array_values(array_filter(array_map(
                static fn ($c) => trim((string) $c),
                $canales
            )))
            : [];
    }
}

if (! function_exists('tenantCuotasSiroQrUrl')) {
    /**
     * URL del servicio SIRO para QR en cupones (legacy obtenerQR).
     */
    function tenantCuotasSiroQrUrl(): string
    {
        if (! tenantCuotasSiroHabilitado()) {
            return '';
        }

        $url = trim((string) config('tenant.cuotas.siro.qr_url', ''));
        if ($url !== '') {
            return $url;
        }

        return trim((string) config('tenant.autogestion.aranceles_escolares.siro_qr_url', ''));
    }
}

if (! function_exists('tenantCuotasInteresMoraEsDiario')) {
    /**
     * Si los % de recargo en mora (tramos 2–4) se interpretan como tasa diaria.
     * Si es false, el % configurado es el total del tramo (`interes_mora_modo` = total).
     */
    function tenantCuotasInteresMoraEsDiario(): bool
    {
        return tenantCuotasInteresMoraModo() === 'diario';
    }
}

if (! function_exists('tenantCuotasInteresMoraModo')) {
    /**
     * Modo de interpretación del % de mora: `diario` (default) o `total`.
     */
    function tenantCuotasInteresMoraModo(): string
    {
        $modo = (string) config('tenant.cuotas.interes_mora_modo', 'diario');

        return in_array($modo, ['diario', 'total'], true) ? $modo : 'diario';
    }
}

if (! function_exists('tenantCuotasComprobantePagoImplementacion')) {
    /**
     * Variante TCPDF del cupón de pago: `sanfranciscoasis` (default) | `epq`.
     */
    function tenantCuotasComprobantePagoImplementacion(): string
    {
        $impl = trim((string) config('tenant.cuotas.comprobante_pago.implementacion', 'sanfranciscoasis'));

        return $impl !== '' ? $impl : 'sanfranciscoasis';
    }
}

if (! function_exists('tenantCuotasComprobanteImputacionDosCopiasPorHoja')) {
    /**
     * Comprobante de cobro (imputación): dos copias idénticas por hoja A4 (SFQ/EPQ).
     */
    function tenantCuotasComprobanteImputacionDosCopiasPorHoja(): bool
    {
        return (bool) config('tenant.cuotas.comprobante_imputacion.dos_copias_por_hoja', false);
    }
}

if (! function_exists('afipCertificadosDesdeEnto')) {
    /**
     * Rutas de certificado WSAA/WSFE declaradas en `ento` para el nivel activo.
     *
     * @return array{cert_usuario_id: string, cert_key: string, cert_crt: string}|null
     */
    function afipCertificadosDesdeEnto(?int $idNivel = null): ?array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ento')) {
            return null;
        }

        foreach (['afipCertCarpeta', 'afipCertKey', 'afipCertCrt'] as $column) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('ento', $column)) {
                return null;
            }
        }

        $idNivel ??= (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return null;
        }

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['afipCertCarpeta', 'afipCertKey', 'afipCertCrt']);

        if ($ento === null) {
            return null;
        }

        $carpeta = trim((string) ($ento->afipCertCarpeta ?? ''));
        $key = trim((string) ($ento->afipCertKey ?? ''));
        $crt = trim((string) ($ento->afipCertCrt ?? ''));

        if ($carpeta === '' || $key === '' || $crt === '') {
            return null;
        }

        return [
            'cert_usuario_id' => $carpeta,
            'cert_key' => $key,
            'cert_crt' => $crt,
        ];
    }
}

if (! function_exists('tenantCooperadoraReciboEmailHabilitado')) {
    /**
     * Si el módulo cooperadora intenta enviar recibos por email al pagador.
     */
    function tenantCooperadoraReciboEmailHabilitado(): bool
    {
        return (bool) config('tenant.cooperadora.recibo_email.habilitado', true);
    }
}

if (! function_exists('tenantCooperadoraReciboEmailSimulado')) {
    /**
     * true: registra envío simulado (log + estado) sin SMTP.
     * Override en `config/tenants/{slug}.php` → `cooperadora.recibo_email.simulado`.
     */
    function tenantCooperadoraReciboEmailSimulado(): bool
    {
        return (bool) config('tenant.cooperadora.recibo_email.simulado', true);
    }
}

if (! function_exists('tenantCooperadoraReciboEmailMailer')) {
    function tenantCooperadoraReciboEmailMailer(): string
    {
        $mailer = trim((string) config('tenant.cooperadora.recibo_email.mailer', 'cooperadora'));

        return $mailer !== '' ? $mailer : 'cooperadora';
    }
}

if (! function_exists('tenantCooperadoraReciboEmailAsunto')) {
    function tenantCooperadoraReciboEmailAsunto(string $numeroReciboTexto = ''): string
    {
        $base = trim((string) config('tenant.cooperadora.recibo_email.asunto', 'Recibo de pago'));
        if ($base === '') {
            $base = 'Recibo de pago';
        }
        if ($numeroReciboTexto !== '') {
            return $base.' Nº '.$numeroReciboTexto;
        }

        return $base;
    }
}

if (! function_exists('seClientesMailFrom')) {
    /**
     * Remitente del mailer `sistemas_escolares` (recuperación de contraseña, avisos SE).
     * null si faltan SE_CLIENTES_MAIL_* en .env.
     *
     * @return array{address: string, name: string}|null
     */
    function seClientesMailFrom(): ?array
    {
        $address = trim((string) env('SE_CLIENTES_MAIL_FROM_ADDRESS', ''));
        if ($address === '') {
            $address = trim((string) env('SE_CLIENTES_MAIL_USERNAME', ''));
        }
        $password = trim((string) env('SE_CLIENTES_MAIL_PASSWORD', ''));
        $host = trim((string) env('SE_CLIENTES_MAIL_HOST', ''));

        if ($address === '' || $password === '' || $host === '') {
            return null;
        }

        $nameEnv = trim((string) env('SE_CLIENTES_MAIL_FROM_NAME', ''));
        $name = $nameEnv !== ''
            ? $nameEnv
            : trim((string) config('app.name', 'Sistemas Escolares'));

        return [
            'address' => $address,
            'name' => $name,
        ];
    }
}

if (! function_exists('tenantCooperadoraReciboEmailFrom')) {
    /**
     * Remitente del correo cooperadora. null si faltan COOP_MAIL_* en .env.
     * Nombre visible: coop_config.nombre_institucion; si vacío, tenant / COOP_MAIL_FROM_NAME / tenant.nombre.
     *
     * @return array{address: string, name: string}|null
     */
    function tenantCooperadoraReciboEmailFrom(): ?array
    {
        $address = trim((string) env('COOP_MAIL_FROM_ADDRESS', ''));
        if ($address === '') {
            $address = trim((string) env('COOP_MAIL_USERNAME', ''));
        }
        $password = trim((string) env('COOP_MAIL_PASSWORD', ''));
        $host = trim((string) env('COOP_MAIL_HOST', ''));

        if ($address === '' || $password === '' || $host === '') {
            return null;
        }

        $name = '';
        try {
            $name = trim((string) (CooperadoraConfig::datosPdfHeader()['nombre'] ?? ''));
        } catch (Throwable) {
            $name = '';
        }
        if ($name === '') {
            $nameTenant = config('tenant.cooperadora.recibo_email.from_name');
            $nameEnv = trim((string) env('COOP_MAIL_FROM_NAME', ''));
            if (is_string($nameTenant) && trim($nameTenant) !== '') {
                $name = trim($nameTenant);
            } elseif ($nameEnv !== '') {
                $name = $nameEnv;
            } else {
                $name = trim((string) config('tenant.nombre', 'Cooperadora'));
            }
        }

        return [
            'address' => $address,
            'name' => $name,
        ];
    }
}

if (! function_exists('tenantCuotasFacturacionAfipHabilitada')) {
    /**
     * Si el colegio tiene facturación AFIP configurada (certificados y tenant).
     */
    function tenantCuotasFacturacionAfipHabilitada(): bool
    {
        if (! (bool) config('tenant.cuotas.facturacion_afip.habilitado', false)) {
            return false;
        }

        $cfg = tenantCuotasFacturacionAfipConfig();

        return $cfg !== null;
    }
}

if (! function_exists('tenantCuotasFacturacionAfipModo')) {
    /**
     * @return 'devengamiento'|'pago'
     */
    function tenantCuotasFacturacionAfipModo(): string
    {
        $modo = (string) config('tenant.cuotas.facturacion_afip.modo', 'devengamiento');

        return $modo === 'pago' ? 'pago' : 'devengamiento';
    }
}

if (! function_exists('tenantCuotasFacturacionAfipEnPago')) {
    function tenantCuotasFacturacionAfipEnPago(): bool
    {
        return tenantCuotasFacturacionAfipHabilitada()
            && tenantCuotasFacturacionAfipModo() === 'pago';
    }
}

if (! function_exists('tenantCuotasFacturacionAfipEnDevengamiento')) {
    function tenantCuotasFacturacionAfipEnDevengamiento(): bool
    {
        return tenantCuotasFacturacionAfipHabilitada()
            && tenantCuotasFacturacionAfipModo() === 'devengamiento';
    }
}

if (! function_exists('tenantCuotasFacturacionAfipMuestraEnImputacionPago')) {
    function tenantCuotasFacturacionAfipMuestraEnImputacionPago(): bool
    {
        return tenantCuotasFacturacionAfipEnPago();
    }
}

if (! function_exists('tenantCuotasFacturacionAfipConfig')) {
    /**
     * Configuración AFIP del tenant para imputación de pagos.
     *
     * @return array<string, mixed>|null
     */
    function tenantCuotasFacturacionAfipConfig(): ?array
    {
        $cfg = config('tenant.cuotas.facturacion_afip');
        if (! is_array($cfg)) {
            return null;
        }

        $certsEnto = afipCertificadosDesdeEnto();
        $certId = trim((string) ($certsEnto['cert_usuario_id'] ?? $cfg['cert_usuario_id'] ?? ''));
        $certKey = trim((string) ($certsEnto['cert_key'] ?? $cfg['cert_key'] ?? ''));
        $certCrt = trim((string) ($certsEnto['cert_crt'] ?? $cfg['cert_crt'] ?? ''));

        if ($certId === '' || $certKey === '' || $certCrt === '') {
            return null;
        }

        $cfg['cert_usuario_id'] = $certId;
        $cfg['cert_key'] = $certKey;
        $cfg['cert_crt'] = $certCrt;
        $cfg['cbte_tipo'] = (int) ($cfg['cbte_tipo'] ?? 15);
        $cfg['concepto'] = (int) ($cfg['concepto'] ?? 2);
        $cfg['doc_tipo'] = (int) ($cfg['doc_tipo'] ?? 96);
        $cfg['nota_credito_tipo'] = (int) ($cfg['nota_credito_tipo'] ?? 12);
        $cfg['cbte_tipo_asociado'] = (int) ($cfg['cbte_tipo_asociado'] ?? $cfg['cbte_tipo']);
        $cfg['produccion'] = (bool) ($cfg['produccion'] ?? true);

        // `simular => false` explícito en el tenant desactiva simulación también en APP_ENV=local.
        // `simular_local` solo aplica si no se fijó simular en false de forma explícita.
        $simularExplicitamenteFalse = array_key_exists('simular', $cfg) && $cfg['simular'] === false;
        if ($simularExplicitamenteFalse) {
            $cfg['simular'] = false;
        } else {
            $simularExplicito = (bool) ($cfg['simular'] ?? false);
            $simularEnLocal = (bool) ($cfg['simular_local'] ?? true);
            $cfg['simular'] = $simularExplicito
                || (app()->environment('local') && $simularEnLocal);
        }

        return $cfg;
    }
}

if (! function_exists('tenantArcaPadronHabilitado')) {
    function tenantArcaPadronHabilitado(): bool
    {
        if ((bool) config('tenant.arca.padron_a13.habilitado', false)) {
            return true;
        }

        return tenantArcaPadronConfig() !== null;
    }
}

if (! function_exists('tenantArcaPadronConfig')) {
    /**
     * Configuración ARCA Padrón A13 (certificados desde ento + flags del tenant).
     *
     * @return array<string, mixed>|null
     */
    function tenantArcaPadronConfig(): ?array
    {
        $cfg = config('tenant.arca.padron_a13');
        if (! is_array($cfg)) {
            return null;
        }

        $certsEnto = afipCertificadosDesdeEnto();
        $certId = trim((string) ($certsEnto['cert_usuario_id'] ?? ''));
        $certKey = trim((string) ($certsEnto['cert_key'] ?? ''));
        $certCrt = trim((string) ($certsEnto['cert_crt'] ?? ''));

        if ($certId === '' || $certKey === '' || $certCrt === '') {
            return null;
        }

        $cfg['cert_usuario_id'] = $certId;
        $cfg['cert_key'] = $certKey;
        $cfg['cert_crt'] = $certCrt;
        $cfg['produccion'] = (bool) ($cfg['produccion'] ?? true);

        $simularExplicitamenteFalse = array_key_exists('simular', $cfg) && $cfg['simular'] === false;
        if ($simularExplicitamenteFalse) {
            $cfg['simular'] = false;
        } else {
            $simularExplicito = (bool) ($cfg['simular'] ?? false);
            $simularEnLocal = (bool) ($cfg['simular_local'] ?? true);
            $cfg['simular'] = $simularExplicito
                || (app()->environment('local') && $simularEnLocal);
        }

        return $cfg;
    }
}

if (! function_exists('tenantLoginNivelesIds')) {
    /**
     * IDs de `niveles` visibles en `/loginUsuario` para este colegio.
     * `null` = sin filtro (todos los registros de la tabla).
     *
     * @return list<int>|null
     */
    function tenantLoginNivelesIds(): ?array
    {
        return NivelSistema::idsNivelesLoginConfigurados();
    }
}

if (! function_exists('tenantBoletinMuestraTercerMateria')) {
    /**
     * Si el colegio muestra el bloque de tercer materia en boletín y consulta de calificaciones.
     * Default false; activar en `config/tenants/{slug}.php`.
     */
    function tenantBoletinMuestraTercerMateria(): bool
    {
        return (bool) config('tenant.boletin.mostrar_tercer_materia', false);
    }
}

if (! function_exists('tenantExamenesActaVolantePreviosModalidad')) {
    /**
     * Modalidad de armado de actas volantes de previos.
     * Valores: `curso_seccion` (default) | `curso`.
     */
    function tenantExamenesActaVolantePreviosModalidad(): string
    {
        $modo = strtolower(trim((string) config('tenant.examenes.acta_volante_previos_modalidad', 'curso_seccion')));

        return in_array($modo, ['curso', 'curso_seccion'], true)
            ? $modo
            : 'curso_seccion';
    }
}

if (! function_exists('tenantBoletinPrimarioIpeImplementacion')) {
    /**
     * Variante del informe de progreso escolar (primario).
     * Valores: `estandar` (vertical) | `sanjose` (apaisado) | `montecristo` (extracurriculares) | `caixalsf` (vertical Caixal SF).
     */
    function tenantBoletinPrimarioIpeImplementacion(): string
    {
        $impl = trim((string) config('tenant.boletin_primario.ipe_implementacion', 'estandar'));

        return $impl !== '' ? $impl : 'estandar';
    }
}

if (! function_exists('tenantBoletinPrimarioMenuEtiquetaBoletinIpe')) {
    /**
     * Etiqueta del ítem de boletín IPE en CALIFICACIONES (Primario) del Menú de Secretaría.
     * Default `IPE (Informe de Progreso Escolar)`; personalizar en `config/tenants/{slug}.php`.
     */
    function tenantBoletinPrimarioMenuEtiquetaBoletinIpe(): string
    {
        $etiqueta = trim((string) config('tenant.boletin_primario.menu_etiqueta_boletin_ipe', 'IPE (Informe de Progreso Escolar)'));

        return $etiqueta !== '' ? $etiqueta : 'IPE (Informe de Progreso Escolar)';
    }
}

if (! function_exists('tenantBoletinPrimEpqMembretePortadaAbsoluta')) {
    /**
     * Ruta absoluta al membrete de la portada del Boletín (Prim) — implementación epq.
     * Configurable por tenant en `boletin_primario.epq_membrete_portada` (relativa a `public/`).
     */
    function tenantBoletinPrimEpqMembretePortadaAbsoluta(): ?string
    {
        $rel = trim((string) config('tenant.boletin_primario.epq_membrete_portada', ''));
        if ($rel === '') {
            return null;
        }

        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $abs = public_path($rel);

        return is_file($abs) ? $abs : null;
    }
}

if (! function_exists('tenantBoletinSecundarioMenuEtiqueta')) {
    function tenantBoletinSecundarioMenuEtiqueta(): string
    {
        $etiqueta = trim((string) config('tenant.boletin_secundario.menu_etiqueta', 'Boletines (secundario)'));

        return $etiqueta !== '' ? $etiqueta : 'Boletines (secundario)';
    }
}

if (! function_exists('tenantBoletinEpqSecundarioSubtituloInstitucion')) {
    function tenantBoletinEpqSecundarioSubtituloInstitucion(): string
    {
        return trim((string) config('tenant.boletin_secundario.epq_subtitulo_institucion', 'Padres Escolapios'));
    }
}

if (! function_exists('tenantBoletinEpqSecundarioMembreteAbsoluta')) {
    /**
     * Membrete del informe de calificaciones EPQ secundario (`public/` relativo).
     */
    function tenantBoletinEpqSecundarioMembreteAbsoluta(): ?string
    {
        $rel = trim((string) config('tenant.boletin_secundario.epq_membrete', ''));
        if ($rel === '') {
            $rel = trim((string) config('tenant.boletin_primario.epq_membrete_portada', ''));
        }
        if ($rel === '') {
            return null;
        }

        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $abs = public_path($rel);

        return is_file($abs) ? $abs : null;
    }
}

if (! function_exists('tenantCalificacionesInicialInformeProgresoImplementacion')) {
    /**
     * Variante del Informe de Progreso Escolar (inicial).
     * Valores: `estandar` (layout provincial completo) | `montecristo` (sin aprendizajes ni página de cierre).
     */
    function tenantCalificacionesInicialInformeProgresoImplementacion(): string
    {
        $impl = trim((string) config('tenant.calificaciones_inicial.informe_progreso.implementacion', 'estandar'));

        return $impl !== '' ? $impl : 'estandar';
    }
}

if (! function_exists('tenantCalificacionesPrimarioCargaEstudianteImplementacion')) {
    /** Variante activa de carga por estudiante (primario), p. ej. `montecristo`. */
    function tenantCalificacionesPrimarioCargaEstudianteImplementacion(): ?string
    {
        return CalificacionesPrimarioModulos::implementacionConfigurada(
            CalificacionesPrimarioModulos::CARGA_ESTUDIANTE,
        );
    }
}

if (! function_exists('tenantCalificacionesPrimarioCargaMateriaImplementacion')) {
    /** Variante activa de carga por materia (primario), p. ej. `montecristo`. */
    function tenantCalificacionesPrimarioCargaMateriaImplementacion(): ?string
    {
        return CalificacionesPrimarioModulos::implementacionConfigurada(
            CalificacionesPrimarioModulos::CARGA_MATERIA,
        );
    }
}

if (! function_exists('tenantCalificacionesPrimarioPlanillaImplementacion')) {
    /** Variante activa de planilla (primario), p. ej. `montecristo`. */
    function tenantCalificacionesPrimarioPlanillaImplementacion(): ?string
    {
        return CalificacionesPrimarioModulos::implementacionConfigurada(
            CalificacionesPrimarioModulos::PLANILLA,
        );
    }
}

if (! function_exists('tenantPortalDocenteBoletinIpe')) {
    /**
     * Si el Menú de Docentes incluye boletín IPE / síntesis (primario).
     * Default false; activar en `config/tenants/{slug}.php`.
     */
    function tenantPortalDocenteBoletinIpe(): bool
    {
        return (bool) config('tenant.portal_docente.menu.primario.boletin_ipe', false);
    }
}

if (! function_exists('tenantPortalDocenteCuadernoSeguimientoAulico')) {
    /**
     * Si el Menú de Docentes incluye el Cuaderno de Seguimiento Áulico (secundario).
     * Default false; activar en `config/tenants/{slug}.php`.
     */
    function tenantPortalDocenteCuadernoSeguimientoAulico(): bool
    {
        return (bool) config(
            'tenant.portal_docente.menu.secundario.cuaderno_seguimiento_aulico',
            config('tenant.portal_docente.cuaderno_seguimiento_aulico', false),
        );
    }
}

if (! function_exists('tenantPortalDocenteRecursosDidacticosMenuItem')) {
    /**
     * Ítem del grupo Recursos didácticos en el Menú de Docentes (`nueva_reserva` | `listado`).
     */
    function tenantPortalDocenteRecursosDidacticosMenuItem(string $item): bool
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);

        $claveNivel = match ($idNivel) {
            NivelSistema::INICIAL => 'inicial',
            NivelSistema::PRIMARIO => 'primario',
            NivelSistema::SECUNDARIO => 'secundario',
            default => null,
        };

        if ($claveNivel === null) {
            return false;
        }

        return (bool) config("tenant.portal_docente.menu.{$claveNivel}.recursos_didacticos_{$item}", false);
    }
}

if (! function_exists('tenantPortalDocenteRecursosDidacticosNuevaReserva')) {
    /**
     * Si el Menú de Docentes incluye «Nueva reserva» (grupo Recursos didácticos).
     * Default false; activar en `config/tenants/{slug}.php` por nivel pedagógico.
     */
    function tenantPortalDocenteRecursosDidacticosNuevaReserva(): bool
    {
        return tenantPortalDocenteRecursosDidacticosMenuItem('nueva_reserva');
    }
}

if (! function_exists('tenantPortalDocenteRecursosDidacticosListado')) {
    /**
     * Si el Menú de Docentes incluye «Listado de reservas» (solo consulta).
     */
    function tenantPortalDocenteRecursosDidacticosListado(): bool
    {
        return tenantPortalDocenteRecursosDidacticosMenuItem('listado');
    }
}

if (! function_exists('tenantPortalDocenteRecursosDidacticosVisible')) {
    /**
     * Si el grupo Recursos didácticos debe mostrarse en el Menú de Docentes.
     */
    function tenantPortalDocenteRecursosDidacticosVisible(): bool
    {
        return tenantPortalDocenteRecursosDidacticosNuevaReserva()
            || tenantPortalDocenteRecursosDidacticosListado();
    }
}

if (! function_exists('tenantSolicitudEvaluacionHabilitada')) {
    /**
     * Si el colegio usa el módulo Solicitud de evaluación (tabla evaluac).
     * Default false; activar en `config/tenants/{slug}.php` → `modulos.solicitud_evaluacion`.
     */
    function tenantSolicitudEvaluacionHabilitada(): bool
    {
        return (bool) config('tenant.modulos.solicitud_evaluacion', false);
    }
}

if (! function_exists('tenantLibroDeTemasHabilitado')) {
    /**
     * Libro de temas (tabla `librodetemas`) en Secretaría y Menú de Docentes.
     * Default false; activar en `config/tenants/{slug}.php` → `modulos.libro_de_temas`.
     */
    function tenantLibroDeTemasHabilitado(): bool
    {
        return (bool) config('tenant.modulos.libro_de_temas', false);
    }
}

if (! function_exists('tenantPortalDocenteLibroDeTemas')) {
    /**
     * Ítem Libro de temas en el Menú de Docentes (nivel de sesión).
     * Exige el flag de módulo y `portal_docente.menu.{nivel}.libro_de_temas`.
     */
    function tenantPortalDocenteLibroDeTemas(): bool
    {
        if (! tenantLibroDeTemasHabilitado()) {
            return false;
        }

        $claveNivel = \App\Support\LibroDeTemas\LibroDeTemasService::claveNivelMenu();
        if ($claveNivel === null) {
            return false;
        }

        return (bool) config("tenant.portal_docente.menu.{$claveNivel}.libro_de_temas", false);
    }
}

if (! function_exists('tenantProgramasExamenHabilitado')) {
    /**
     * Descarga pública de programas de examen (/programas-examen).
     * Por defecto deshabilitado; activar en `config/tenants/{slug}.php` → `programas_examen.habilitado`.
     */
    function tenantProgramasExamenHabilitado(): bool
    {
        return (bool) config('tenant.programas_examen.habilitado', false);
    }
}

if (! function_exists('tenantProgramasExamenAnios')) {
    /**
     * Años lectivos del formulario público `/programas-examen`.
     * Si el tenant define `programas_examen.anios`, se usan esos (orden decreciente).
     * Si la lista está vacía, fallback a los años de `ento.idTerlecVerNotas`.
     *
     * @return list<int>
     */
    function tenantProgramasExamenAnios(): array
    {
        $configurados = config('tenant.programas_examen.anios', []);
        if (! is_array($configurados) || $configurados === []) {
            return DocPpConsulta::aniosLectivosSistema();
        }

        $anios = [];
        foreach ($configurados as $anio) {
            $n = (int) $anio;
            if ($n > 1990 && $n < 2100) {
                $anios[] = $n;
            }
        }

        $anios = array_values(array_unique($anios));
        rsort($anios);

        return $anios;
    }
}

if (! function_exists('tenantDocPpHabilitado')) {
    /**
     * Módulo nuevo de planificaciones y programas (tabla doc_pp).
     * Activar en `config/tenants/{slug}.php` → `doc_pp.habilitado`.
     */
    function tenantDocPpHabilitado(): bool
    {
        return (bool) config('tenant.doc_pp.habilitado', false);
    }
}

if (! function_exists('entoAutogestionVerDatosYFichaHabilitada')) {
    /**
     * Si el nivel muestra en Menú de Alumnos Actualización de Datos y Ficha de Matrícula.
     * Flag único `ento.verDatosFicha` (Parametrización → Parámetros). Default visible si falta columna/fila.
     */
    function entoAutogestionVerDatosYFichaHabilitada(?int $idNivel = null): bool
    {
        $idNivel ??= (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return false;
        }

        if (! Schema::hasTable('ento') || ! Schema::hasColumn('ento', 'verDatosFicha')) {
            return true;
        }

        $valor = Ento::query()
            ->where('idNivel', $idNivel)
            ->value('verDatosFicha');

        if ($valor === null) {
            return true;
        }

        return (int) $valor === 1;
    }
}

if (! function_exists('tenantAutogestionActualizacionDatosImplementacion')) {
    /**
     * Variante de formulario de actualización de datos (`estandar`, `sanfranciscoasis`, …).
     */
    function tenantAutogestionActualizacionDatosImplementacion(): string
    {
        $impl = (string) config('tenant.autogestion.actualizacion_datos.implementacion', 'estandar');

        return $impl !== '' ? $impl : 'estandar';
    }
}

if (! function_exists('tenantAutogestionActualizacionDatosHabilitada')) {
    /**
     * Si el portal familia incluye actualización de datos personales del legajo.
     * Requiere módulo tenant + `ento.verDatosFicha` del nivel del alumno.
     */
    function tenantAutogestionActualizacionDatosHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.actualizacion_datos.habilitado', true)) {
            return false;
        }

        return entoAutogestionVerDatosYFichaHabilitada();
    }
}

if (! function_exists('tenantAutogestionActualizacionDatosFotoCarnetHabilitada')) {
    /**
     * Si el formulario de actualización de datos del portal familia incluye foto carnet.
     * Independiente de la solapa del ABM de legajos (Secretaría).
     */
    function tenantAutogestionActualizacionDatosFotoCarnetHabilitada(): bool
    {
        return (bool) config('tenant.autogestion.actualizacion_datos.foto_carnet', false);
    }
}

if (! function_exists('tenantAutogestionActualizacionDatosRequiereDocumentos')) {
    /**
     * Si el formulario SFA muestra y exige aceptación de documentos institucionales.
     * Requiere `implementacion = sanfranciscoasis` y `requiere_documentos` (default true).
     */
    function tenantAutogestionActualizacionDatosRequiereDocumentos(): bool
    {
        if (tenantAutogestionActualizacionDatosImplementacion() !== 'sanfranciscoasis') {
            return false;
        }

        return (bool) config('tenant.autogestion.actualizacion_datos.requiere_documentos', true);
    }
}

if (! function_exists('tenantAutogestionActualizacionDatosLivewireComponent')) {
    /**
     * Componente Livewire del formulario según la variante del tenant.
     *
     * @return class-string<Component>
     */
    function tenantAutogestionActualizacionDatosLivewireComponent(): string
    {
        return match (tenantAutogestionActualizacionDatosImplementacion()) {
            'sanfranciscoasis' => ActualizacionDatosPersonalesSanFranciscoAsisForm::class,
            default => ActualizacionDatosPersonalesEstandarForm::class,
        };
    }
}

if (! function_exists('tenantAutogestionFichaMatriculaHabilitada')) {
    /**
     * Si el portal familia incluye impresión de ficha de matrícula en PDF.
     * Requiere módulo tenant + implementación + `ento.verDatosFicha` del nivel del alumno.
     */
    function tenantAutogestionFichaMatriculaHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.ficha_matricula.habilitado', false)) {
            return false;
        }

        if (! filled(config('tenant.autogestion.ficha_matricula.implementacion'))) {
            return false;
        }

        return entoAutogestionVerDatosYFichaHabilitada();
    }
}

if (! function_exists('tenantSecretariaFichaMatriculaImplementacion')) {
    /**
     * Variante de ficha de matrícula para secretaría (`sanfranciscoasis` | `montecristo` | `sanjose` | `iess`).
     */
    function tenantSecretariaFichaMatriculaImplementacion(): ?string
    {
        $valor = config('tenant.secretaria.ficha_matricula.implementacion');

        return filled($valor) ? (string) $valor : null;
    }
}

if (! function_exists('tenantSecretariaFichaMatriculaHabilitada')) {
    /**
     * Si el Menú de Secretaría incluye impresión de ficha de matrícula por curso.
     * `niveles_deshabilitados`: IDs de `niveles` sin ítem ni PDF (p. ej. `[1, 2]` solo secundario).
     */
    function tenantSecretariaFichaMatriculaHabilitada(): bool
    {
        if (! (bool) config('tenant.secretaria.ficha_matricula.habilitado', false)) {
            return false;
        }

        if (! filled(tenantSecretariaFichaMatriculaImplementacion())) {
            return false;
        }

        $nivelesDeshabilitados = config('tenant.secretaria.ficha_matricula.niveles_deshabilitados', []);
        if (is_array($nivelesDeshabilitados) && $nivelesDeshabilitados !== []) {
            $idNivel = (int) (schoolCtx()->idNivel ?? 0);
            if ($idNivel > 0 && in_array($idNivel, array_map('intval', $nivelesDeshabilitados), true)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('tenantSecretariaFichaMatriculaEtiqueta')) {
    /**
     * Título del ítem de menú / pantalla según la variante del tenant.
     */
    function tenantSecretariaFichaMatriculaEtiqueta(): string
    {
        return match (tenantSecretariaFichaMatriculaImplementacion()) {
            'montecristo', 'sanjose' => 'Ficha de Solicitud de Matrícula',
            'sanfranciscoasis', 'iess' => 'Ficha de Matrícula',
            default => 'Ficha de Matrícula',
        };
    }
}

if (! function_exists('tenantAulicaDeudaHabilitada')) {
    /**
     * Si el tenant consulta deuda en Áulica (flag + credenciales AULICA_* en .env).
     */
    function tenantAulicaDeudaHabilitada(): bool
    {
        return \App\Support\Aulica\AulicaConfig::habilitada();
    }
}

if (! function_exists('tenantAulicaDeudaBloqueaAutogestion')) {
    /**
     * Si la deuda Áulica impide ficha y actualización de datos en el portal familia.
     */
    function tenantAulicaDeudaBloqueaAutogestion(): bool
    {
        return \App\Support\Aulica\AulicaConfig::bloquearAutogestion();
    }
}

if (! function_exists('tenantAutogestionInformeInasistenciasHabilitada')) {
    /**
     * Si el portal familia incluye informe de inasistencias en PDF.
     * Default habilitado; desactivar en `config/tenants/{slug}.php` con `habilitado => false`.
     * `niveles_habilitados`: IDs de `niveles` (p. ej. `[3]` solo secundario). Vacío = todos los niveles.
     * `niveles_deshabilitados`: IDs de `niveles` sin el módulo (p. ej. `[1, 2]` inicial y primario).
     */
    function tenantAutogestionInformeInasistenciasHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.informe_inasistencias.habilitado', true)) {
            return false;
        }

        $idNivel = (int) (studentCtx()->idNivel ?? 0);

        $nivelesDeshabilitados = config('tenant.autogestion.informe_inasistencias.niveles_deshabilitados', []);
        if (is_array($nivelesDeshabilitados) && $nivelesDeshabilitados !== [] && $idNivel > 0) {
            if (in_array($idNivel, array_map('intval', $nivelesDeshabilitados), true)) {
                return false;
            }
        }

        $nivelesHabilitados = config('tenant.autogestion.informe_inasistencias.niveles_habilitados', []);
        if (! is_array($nivelesHabilitados) || $nivelesHabilitados === []) {
            return true;
        }

        if ($idNivel <= 0) {
            return false;
        }

        return in_array($idNivel, array_map('intval', $nivelesHabilitados), true);
    }
}

if (! function_exists('tenantSecretariaInformeInasistenciasHabilitada')) {
    /**
     * Si el Menú de Secretaría incluye informe de inasistencias por curso (PDF).
     * `niveles_deshabilitados`: IDs de `niveles` sin ítem ni PDF (p. ej. `[1, 2]` inicial y primario).
     */
    function tenantSecretariaInformeInasistenciasHabilitada(): bool
    {
        $nivelesDeshabilitados = config('tenant.secretaria.informe_inasistencias.niveles_deshabilitados', []);
        if (! is_array($nivelesDeshabilitados) || $nivelesDeshabilitados === []) {
            return true;
        }

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return true;
        }

        return ! in_array($idNivel, array_map('intval', $nivelesDeshabilitados), true);
    }
}

if (! function_exists('tenantRegistroAsistenciaImplementacion')) {
    /**
     * Variante del Registro de Asistencia PDF para un nivel: `con_datos` | `sin_datos`.
     * Config: `tenant.registro_asistencia.por_nivel.{idNivel}`. Sin clave: `con_datos` (todos los niveles).
     */
    function tenantRegistroAsistenciaImplementacion(?int $idNivel = null): string
    {
        $idNivel = $idNivel ?? (int) (schoolCtx()->idNivel ?? 0);
        $mapa = config('tenant.registro_asistencia.por_nivel', []);
        if (! is_array($mapa)) {
            $mapa = [];
        }

        $raw = $mapa[$idNivel] ?? $mapa[(string) $idNivel] ?? null;
        if (is_string($raw) && $raw !== '') {
            return \App\Support\RegistroAsistencia\RegistroAsistenciaCatalog::normalize($raw);
        }

        return \App\Support\RegistroAsistencia\RegistroAsistenciaCatalog::defaultParaNivel($idNivel);
    }
}

if (! function_exists('tenantParteDiarioImplementacion')) {
    /**
     * Modelo de PDF del Parte diario del preceptor: `estandar` | `sanfranciscoasis`.
     * Config: `tenant.parte_diario.implementacion`.
     */
    function tenantParteDiarioImplementacion(): string
    {
        $raw = strtolower(trim((string) config('tenant.parte_diario.implementacion', 'estandar')));

        return match ($raw) {
            'sanfranciscoasis' => 'sanfranciscoasis',
            default => 'estandar',
        };
    }
}

if (! function_exists('tenantSeguimientoComunicadoImplementacion')) {
    /**
     * Modelo de PDF del comunicado de seguimiento disciplinario: `estandar` | `iess`.
     * Config: `tenant.seguimiento.comunicado.implementacion`.
     */
    function tenantSeguimientoComunicadoImplementacion(): string
    {
        $raw = strtolower(trim((string) config('tenant.seguimiento.comunicado.implementacion', 'estandar')));

        return match ($raw) {
            'iess' => 'iess',
            default => 'estandar',
        };
    }
}

if (! function_exists('tenantTeaRegistroImplementacion')) {
    /**
     * Implementación TCPDF de impresos TEA del tenant (`montecristo` o `caixalsf` por defecto).
     */
    function tenantTeaRegistroImplementacion(): string
    {
        $impl = config('tenant.secretaria.tea_registros.implementacion');

        return filled($impl) ? (string) $impl : 'caixalsf';
    }
}

if (! function_exists('tenantTeaRegistroPlantillaPdf')) {
    /**
     * Ruta absoluta a la plantilla PDF estática de un tipo TEA (reinco2025_tipo.id), o null si no está configurada.
     *
     * Config: `tenant.secretaria.tea_registros.plantillas_pdf` — claves 1–5, ruta relativa a resources/.
     */
    function tenantTeaRegistroPlantillaPdf(int $idTipo): ?string
    {
        if ($idTipo <= 0) {
            return null;
        }

        $plantillas = config('tenant.secretaria.tea_registros.plantillas_pdf', []);
        if (! is_array($plantillas)) {
            return null;
        }

        $relative = $plantillas[$idTipo] ?? null;
        if (! filled($relative)) {
            return null;
        }

        $path = resource_path((string) $relative);

        return is_file($path) ? $path : null;
    }
}

if (! function_exists('tenantTeaRegistroPdfDisponible')) {
    function tenantTeaRegistroPdfDisponible(int $idTipo): bool
    {
        if (tenantTeaRegistroPlantillaPdf($idTipo) !== null) {
            return true;
        }

        return match (tenantTeaRegistroImplementacion()) {
            'montecristo' => \App\Support\Tea\TeaRegistroMontecristoTcpdf::soportaTipo($idTipo),
            default => \App\Support\Tea\TeaRegistroCaixalsfTcpdf::soportaTipo($idTipo),
        };
    }
}

if (! function_exists('tenantSecretariaConsultaCalificacionesHabilitada')) {
    /**
     * Si el Menú de Secretaría incluye consulta de calificaciones (secundario).
     * Default habilitado; desactivar en `config/tenants/{slug}.php` con `habilitado => false`.
     */
    function tenantSecretariaConsultaCalificacionesHabilitada(): bool
    {
        return (bool) config('tenant.secretaria.consulta_calificaciones.habilitado', true);
    }
}

if (! function_exists('tenantAutogestionConsultaCalificacionesHabilitada')) {
    /**
     * Si el portal familia incluye consulta de calificaciones (boletín IPE primario o consulta secundario).
     * Default habilitado; desactivar en `config/tenants/{slug}.php` con `habilitado => false`.
     * `niveles_habilitados`: IDs de `niveles` (p. ej. `[3]` solo secundario). Vacío = todos los niveles.
     */
    function tenantAutogestionConsultaCalificacionesHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.consulta_calificaciones.habilitado', true)) {
            return false;
        }

        $nivelesHabilitados = config('tenant.autogestion.consulta_calificaciones.niveles_habilitados', []);
        if (! is_array($nivelesHabilitados) || $nivelesHabilitados === []) {
            return true;
        }

        $idNivel = (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return false;
        }

        return in_array($idNivel, array_map('intval', $nivelesHabilitados), true);
    }
}

if (! function_exists('tenantAutogestionHorarioClaseHabilitada')) {
    /**
     * Si el portal familia incluye horario de clase en PDF.
     * Default deshabilitado; activar en `config/tenants/{slug}.php` con `habilitado => true`.
     * `niveles_habilitados`: IDs de `niveles` (p. ej. `[3]` solo secundario). Vacío = todos los niveles.
     */
    function tenantAutogestionHorarioClaseHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.horario_clase.habilitado', false)) {
            return false;
        }

        $nivelesHabilitados = config('tenant.autogestion.horario_clase.niveles_habilitados', []);
        if (! is_array($nivelesHabilitados) || $nivelesHabilitados === []) {
            return true;
        }

        $idNivel = (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return false;
        }

        return in_array($idNivel, array_map('intval', $nivelesHabilitados), true);
    }
}

if (! function_exists('tenantAutogestionCusHabilitada')) {
    /**
     * Si el portal familia incluye impresión del C.U.S. (Certificado Único de Salud).
     * Default deshabilitado; activar en `config/tenants/{slug}.php` con `habilitado => true`.
     */
    function tenantAutogestionCusHabilitada(): bool
    {
        return tenantAutogestionFlagPorNivel('cus', false);
    }
}

if (! function_exists('tenantAutogestionIsaHabilitada')) {
    /**
     * Si el portal familia incluye impresión del I.S.A. (Informe de Salud Anual).
     * Default deshabilitado; activar en `config/tenants/{slug}.php` con `habilitado => true`.
     */
    function tenantAutogestionIsaHabilitada(): bool
    {
        return tenantAutogestionFlagPorNivel('isa', false);
    }
}

if (! function_exists('tenantAutogestionLibreDeudaHabilitada')) {
    /**
     * Constancia de libre deuda en el Menú de Alumnos.
     * Requiere flag del tenant y credenciales Áulica (para verificar que no hay deuda).
     */
    function tenantAutogestionLibreDeudaHabilitada(): bool
    {
        if (! tenantAutogestionFlagPorNivel('libre_deuda', false)) {
            return false;
        }

        return tenantAulicaDeudaHabilitada();
    }
}

if (! function_exists('tenantAutogestionFlagPorNivel')) {
    /**
     * Flag de módulo de autogestión: `habilitado` + filtros opcionales por nivel.
     */
    function tenantAutogestionFlagPorNivel(string $clave, bool $defaultHabilitado = false): bool
    {
        if (! (bool) config('tenant.autogestion.'.$clave.'.habilitado', $defaultHabilitado)) {
            return false;
        }

        $idNivel = (int) (studentCtx()->idNivel ?? 0);

        $nivelesDeshabilitados = config('tenant.autogestion.'.$clave.'.niveles_deshabilitados', []);
        if (is_array($nivelesDeshabilitados) && $nivelesDeshabilitados !== [] && $idNivel > 0) {
            if (in_array($idNivel, array_map('intval', $nivelesDeshabilitados), true)) {
                return false;
            }
        }

        $nivelesHabilitados = config('tenant.autogestion.'.$clave.'.niveles_habilitados', []);
        if (! is_array($nivelesHabilitados) || $nivelesHabilitados === []) {
            return true;
        }

        if ($idNivel <= 0) {
            return false;
        }

        return in_array($idNivel, array_map('intval', $nivelesHabilitados), true);
    }
}

if (! function_exists('tenantAutogestionComunicacionesHabilitada')) {
    /**
     * Si el portal familia incluye el módulo de comunicación institucional
     * (cuaderno de comunicados y notificaciones push).
     * Default habilitado; desactivar en `config/tenants/{slug}.php` con `habilitado => false`
     * o `niveles_deshabilitados` (IDs de `niveles`, p. ej. primario = 2).
     */
    function tenantAutogestionComunicacionesHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.comunicaciones.habilitado', true)) {
            return false;
        }

        $nivelesDeshabilitados = config('tenant.autogestion.comunicaciones.niveles_deshabilitados', []);
        if (! is_array($nivelesDeshabilitados) || $nivelesDeshabilitados === []) {
            return true;
        }

        $idNivel = (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel > 0 && in_array($idNivel, array_map('intval', $nivelesDeshabilitados), true)) {
            return false;
        }

        return true;
    }
}

if (! function_exists('tenantAutogestionMenuInicioHabilitada')) {
    /**
     * Si el Menú de Alumnos incluye el ítem «Inicio» (escritorio).
     * Default habilitado; ocultar por nivel en `config/tenants/{slug}.php`.
     */
    function tenantAutogestionMenuInicioHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.menu_inicio.habilitado', true)) {
            return false;
        }

        $nivelesDeshabilitados = config('tenant.autogestion.menu_inicio.niveles_deshabilitados', []);
        if (is_array($nivelesDeshabilitados) && $nivelesDeshabilitados !== []) {
            $idNivel = (int) (studentCtx()->idNivel ?? 0);
            if ($idNivel > 0 && in_array($idNivel, array_map('intval', $nivelesDeshabilitados), true)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('tenantAutogestionRutaInicio')) {
    /**
     * Ruta de destino tras login o acceso a `/alumnos`.
     * Si «Inicio» está deshabilitado y hay aranceles, entra directo a Gestión de Aranceles.
     */
    function tenantAutogestionRutaInicio(): string
    {
        if (! (bool) config('tenant.autogestion.menu_inicio.habilitado', true)
            && tenantAutogestionArancelesEscolaresHabilitada()) {
            return 'alumnos.aranceles-escolares';
        }

        return 'alumnos.home';
    }
}

if (! function_exists('tenantAutogestionBoletinIpePrimarioHabilitada')) {
    /**
     * Boletín IPE por etapas en autogestión familia (nivel primario).
     * Activar en `config/tenants/{slug}.php` → `autogestion.boletin_ipe_primario.habilitado`.
     */
    function tenantAutogestionBoletinIpePrimarioHabilitada(): bool
    {
        return (bool) config('tenant.autogestion.boletin_ipe_primario.habilitado', false);
    }
}

if (! function_exists('tenantAutogestionBoletinPrimEpqHabilitada')) {
    /**
     * Boletín (Prim) EPQ — portada y calificaciones en autogestión familia.
     * Activar en `config/tenants/{slug}.php` → `autogestion.boletin_prim_epq.habilitado`.
     */
    function tenantAutogestionBoletinPrimEpqHabilitada(): bool
    {
        return (bool) config('tenant.autogestion.boletin_prim_epq.habilitado', false);
    }
}

if (! function_exists('tenantAutogestionBoletinSecEpqHabilitada')) {
    /**
     * Informe EPQ secundario — consulta de calificaciones en autogestión familia.
     * Activar en `config/tenants/{slug}.php` → `autogestion.boletin_sec_epq.habilitado`.
     */
    function tenantAutogestionBoletinSecEpqHabilitada(): bool
    {
        return (bool) config('tenant.autogestion.boletin_sec_epq.habilitado', false);
    }
}

if (! function_exists('tenantAutogestionInformeProgresoInicialHabilitada')) {
    /**
     * Informe de progreso escolar por etapa en autogestión familia (nivel inicial).
     * Activar en `config/tenants/{slug}.php` → `autogestion.informe_progreso_inicial.habilitado`.
     */
    function tenantAutogestionInformeProgresoInicialHabilitada(): bool
    {
        return (bool) config('tenant.autogestion.informe_progreso_inicial.habilitado', false);
    }
}

if (! function_exists('tenantAutogestionBoletinInicialSfqHabilitada')) {
    /**
     * Informes pedagógicos inicial SFQ (diagnóstico, etapas y Bellas Artes) en autogestión familia.
     * Activar en `config/tenants/{slug}.php` → `autogestion.boletin_inicial_sfq.habilitado`.
     */
    function tenantAutogestionBoletinInicialSfqHabilitada(): bool
    {
        return (bool) config('tenant.autogestion.boletin_inicial_sfq.habilitado', false);
    }
}

if (! function_exists('tenantAutogestionArancelesEscolaresHabilitada')) {
    /**
     * Si el portal familia incluye aranceles escolares (cuotas pendientes + comprobante).
     * Default false; activar en `config/tenants/{slug}.php` con `implementacion` definida.
     */
    function tenantAutogestionArancelesEscolaresHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.aranceles_escolares.habilitado', false)) {
            return false;
        }

        return filled(config('tenant.autogestion.aranceles_escolares.implementacion'));
    }
}

if (! function_exists('tenantAutogestionArancelesEscolaresMenuEtiqueta')) {
    /**
     * Etiqueta del ítem de aranceles en el Menú de Alumnos.
     * Personalizar en `config/tenants/{slug}.php` → `autogestion.aranceles_escolares.menu_etiqueta`.
     */
    function tenantAutogestionArancelesEscolaresMenuEtiqueta(): string
    {
        $etiqueta = trim((string) config('tenant.autogestion.aranceles_escolares.menu_etiqueta', 'Aranceles Escolares'));

        return $etiqueta !== '' ? $etiqueta : 'Aranceles Escolares';
    }
}

if (! function_exists('tenantAutogestionArancelesEscolaresImplementacion')) {
    /**
     * Variante del módulo de aranceles en autogestión (`sanfranciscoasis`, `gestion_aranceles`, etc.).
     */
    function tenantAutogestionArancelesEscolaresImplementacion(): ?string
    {
        $clave = trim((string) config('tenant.autogestion.aranceles_escolares.implementacion', ''));

        return $clave !== '' ? $clave : null;
    }
}

if (! function_exists('tenantAutogestionArancelesEscolaresLivewireComponent')) {
    /**
     * Componente Livewire del listado según la variante del tenant.
     *
     * @return class-string<Component>
     */
    function tenantAutogestionArancelesEscolaresLivewireComponent(): string
    {
        return match (tenantAutogestionArancelesEscolaresImplementacion()) {
            'gestion_aranceles' => ArancelesEscolaresGestionIndex::class,
            default => ArancelesEscolaresIndex::class,
        };
    }
}

if (! function_exists('tenantArancelesEscolaresBotonPagosUrl')) {
    /**
     * URL del botón de pagos SIRO (variante `gestion_aranceles`).
     * Personalizar en `config/tenants/{slug}.php` → `autogestion.aranceles_escolares.boton_pagos.url`.
     */
    function tenantArancelesEscolaresBotonPagosUrl(): string
    {
        if (tenantAutogestionArancelesEscolaresImplementacion() !== 'gestion_aranceles') {
            return '';
        }

        $cfg = config('tenant.autogestion.aranceles_escolares.boton_pagos');
        if (! is_array($cfg)) {
            return 'https://siropagos.bancoroela.com.ar';
        }

        $url = trim((string) ($cfg['url'] ?? ''));

        return $url !== '' ? $url : 'https://siropagos.bancoroela.com.ar';
    }
}

if (! function_exists('tenantArancelesEscolaresDebitoAutomatico')) {
    /**
     * Banner y enlace al formulario PDF de débito automático (si el tenant lo define).
     *
     * @return array{banner_url: string, pdf_url: string}|null
     */
    function tenantArancelesEscolaresDebitoAutomatico(): ?array
    {
        if (! tenantAutogestionArancelesEscolaresHabilitada()) {
            return null;
        }

        $cfg = config('tenant.autogestion.aranceles_escolares.debito_automatico');
        if (! is_array($cfg)) {
            return null;
        }

        $banner = trim((string) ($cfg['banner'] ?? ''));
        $pdf = trim((string) ($cfg['formulario_pdf'] ?? ''));
        if ($banner === '' || $pdf === '') {
            return null;
        }

        return [
            'banner_url' => asset($banner),
            'pdf_url' => se_route_url('alumnos.aranceles-escolares.formulario-debito-automatico'),
        ];
    }
}

if (! function_exists('tenantArancelesEscolaresMediosPago')) {
    /**
     * Banner clicable de medios de pago debajo del listado de cuotas (si el tenant lo define).
     *
     * @return array{banner_url: string, url: string}|null
     */
    function tenantArancelesEscolaresMediosPago(): ?array
    {
        if (! tenantAutogestionArancelesEscolaresHabilitada()) {
            return null;
        }

        $cfg = config('tenant.autogestion.aranceles_escolares.medios_pago');
        if (! is_array($cfg)) {
            return null;
        }

        $banner = trim((string) ($cfg['banner'] ?? ''));
        $url = trim((string) ($cfg['url'] ?? ''));
        if ($banner === '' || $url === '') {
            return null;
        }

        return [
            'banner_url' => asset($banner),
            'url' => $url,
        ];
    }
}

if (! function_exists('rrdRol')) {
    /**
     * Rol efectivo del usuario en el módulo Reserva de Material Didáctico.
     *
     * Devuelve 'admin' | 'profesor' | 'lectura' | null (sin acceso).
     * El bit más alto gana: admin > profesor > lectura.
     */
    function rrdRol(): ?string
    {
        if (tienePermiso(PermisosIaCatalog::RESERVA_MATERIAL_ADMIN)) {
            return 'admin';
        }
        if (tienePermiso(PermisosIaCatalog::RESERVA_MATERIAL_PROFESOR)) {
            return 'profesor';
        }
        if (tienePermiso(PermisosIaCatalog::RESERVA_MATERIAL_LECTURA)) {
            return 'lectura';
        }

        return null;
    }
}

if (! function_exists('notificaciones_push_enviar')) {
    /**
     * Enviar notificación push a uno o más legajos (user_key).
     *
     * @param  list<string|int>|null  $userKeys  null = no enviar (requiere lista explícita)
     * @return array{ok:bool,sent:int,failed:int,errors:list<string>,sent_user_keys?:list<string>,failed_user_keys?:array<string,string>}
     */
    function notificaciones_push_enviar(string $title, string $body, ?string $url = null, ?array $userKeys = null, ?string $nombreColegio = null): array
    {
        $url = $url ?? url('/');

        if ($userKeys === null) {
            return ['ok' => false, 'sent' => 0, 'failed' => 0, 'errors' => ['Faltan destinatarios']];
        }

        $keys = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $userKeys), fn ($v) => $v !== ''));
        $result = WebPushService::sendToUsers($keys, $title, $body, $url, $nombreColegio);

        return [
            'ok' => true,
            'sent' => $result['ok'],
            'failed' => $result['fail'],
            'errors' => $result['errors'],
            'sent_user_keys' => $result['sent_user_keys'] ?? [],
            'failed_user_keys' => $result['failed_user_keys'] ?? [],
        ];
    }
}
