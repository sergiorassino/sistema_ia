<?php

use App\Models\Ento;
use App\Push\WebPushService;
use App\Support\SchoolContext;
use App\Support\StudentContext;
use Illuminate\Support\Facades\Storage;

if (! function_exists('se_route_url')) {
    /**
     * URL absoluta con el prefijo de APP_URL (subcarpeta en producción).
     * Evita enlaces a /alumnos/... o /dashboard en la raíz del dominio.
     */
    function se_route_url(string $name, mixed $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $parameters, false);
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

if (! function_exists('profesorEsSecretario')) {
    function profesorEsSecretario(?\App\Models\Profesor $profesor = null): bool
    {
        return \App\Support\ProfesorMenuPortal::esSecretario($profesor);
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
        return \App\Support\NivelSistema::esSecundario((int) (schoolCtx()->idNivel ?? 0));
    }
}

if (! function_exists('layoutMenuStaff')) {
    /** Layout del portal staff: Administración o Secretaría pedagógica. */
    function layoutMenuStaff(): string
    {
        return \App\Support\ProfesorMenuPortal::layoutStaff();
    }
}

if (! function_exists('schoolIdNivelPedagogico')) {
    /**
     * Nivel único de filtro (login 1–4). En Administración devuelve 0: usar
     * {@see \App\Support\SchoolAlcancePedagogico::aplicarFiltroColumnaNivel()}.
     */
    function schoolIdNivelPedagogico(): int
    {
        return (int) (\App\Support\SchoolAlcancePedagogico::idNivelFiltroUnico() ?? 0);
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
            return tienePermiso(\App\Support\PermisosIaCatalog::LEGAJOS_MODIFICAR_ADMIN);
        }

        return tienePermiso(\App\Support\PermisosIaCatalog::LEGAJOS_ESTUDIANTES);
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
        return \App\Support\PermisosConfiguracion::tiene($orden);
    }
}

if (! function_exists('tieneAlgunPermisoConfiguracion')) {
    function tieneAlgunPermisoConfiguracion(): bool
    {
        return \App\Support\PermisosConfiguracion::tieneAlgunAccesoMenu();
    }
}

if (! function_exists('schoolLogoStoragePath')) {
    function schoolLogoStoragePath(bool $refresh = false): ?string
    {
        static $memo = null;
        static $done = false;

        if ($refresh) {
            $done = false;
            $memo = null;
        }

        if ($done) {
            return $memo;
        }

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return null;
        }

        $done = true;

        $path = Ento::query()
            ->where('idNivel', $idNivel)
            ->value('logo_path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $memo = $path;

        return $memo;
    }
}

if (! function_exists('schoolLogoUrl')) {
    function schoolLogoUrl(bool $refresh = false): ?string
    {
        $path = schoolLogoStoragePath($refresh);

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }
}

if (! function_exists('studentLogoStoragePath')) {
    function studentLogoStoragePath(): ?string
    {
        static $memo = null;
        static $done = false;

        if ($done) {
            return $memo;
        }

        $idNivel = (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            return null;
        }

        $done = true;

        $path = Ento::query()
            ->where('idNivel', $idNivel)
            ->value('logo_path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $memo = $path;

        return $memo;
    }
}

if (! function_exists('studentLogoUrl')) {
    function studentLogoUrl(): ?string
    {
        $path = studentLogoStoragePath();

        return $path !== null ? Storage::disk('public')->url($path) : null;
    }
}

if (! function_exists('entoInstitutionalLogoStoragePath')) {
    /**
     * Primer `logo_path` definido en `ento` (cualquier nivel).
     */
    function entoInstitutionalLogoStoragePath(): ?string
    {
        static $memo = null;
        static $done = false;

        if ($done) {
            return $memo;
        }
        $done = true;

        $path = Ento::query()
            ->whereNotNull('logo_path')
            ->where('logo_path', '<>', '')
            ->orderBy('idNivel')
            ->value('logo_path');

        if (! is_string($path) || trim($path) === '') {
            $memo = null;

            return null;
        }

        $path = trim($path);
        if (! Storage::disk('public')->exists($path)) {
            $memo = null;

            return null;
        }

        $memo = $path;

        return $memo;
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

if (! function_exists('matriculaWebDocumentoUrl')) {
    /**
     * URL para ver/descargar un PDF de aceptación de matrícula web del nivel indicado (o el activo en secretaría).
     *
     * @param  string  $tipo  compromiso|aec|normas|traslado
     */
    function matriculaWebDocumentoUrl(string $tipo, ?int $idNivel = null): ?string
    {
        if (! \App\Support\MatriculaWeb\MatriculaWebDocumentos::claveValida($tipo)) {
            return null;
        }

        if (\App\Support\MatriculaWeb\MatriculaWebDocumentos::nombreRegistrado($tipo, $idNivel) === null) {
            return null;
        }

        if (\App\Support\MatriculaWeb\MatriculaWebDocumentos::pathAlmacenado($tipo, $idNivel) === null) {
            return null;
        }

        if (auth('alumno')->check()) {
            return route('alumnos.documentos-aceptacion.archivo', ['tipo' => $tipo]);
        }

        return route('matricula-web.documentos.archivo', ['tipo' => $tipo]);
    }
}

if (! function_exists('seMonogramFaviconUrls')) {
    /**
     * Favicon de pestaña: `1.png` (tema claro del navegador), `2.png` (tema oscuro).
     *
     * @return array{light: string, dark: string}
     */
    function seMonogramFaviconUrls(): array
    {
        $version = '14';

        return [
            'light' => asset('img/1.png').'?v='.$version,
            'dark' => asset('img/2.png').'?v='.$version,
        ];
    }
}

if (! function_exists('institutionalFaviconUrl')) {
    /**
     * URL del favicon institucional (variante para tema claro del navegador).
     *
     * @deprecated Preferir seMonogramFaviconUrls() en vistas; se mantiene por compatibilidad.
     */
    function institutionalFaviconUrl(?callable $contextLogo = null): string
    {
        return seMonogramFaviconUrls()['light'];
    }
}

if (! function_exists('schoolPdfHeaderData')) {
    /**
     * Datos institucionales para encabezados de PDFs (Dompdf).
     *
     * @return array{insti:string,direccion:string,localidad:string,cue:string,ee:string,logo_file:?string}
     */
    function schoolPdfHeaderData(): array
    {
        static $memo = null;
        static $done = false;

        if ($done) {
            /** @var array $memo */
            return $memo;
        }
        $done = true;

        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            $memo = [
                'insti' => '',
                'direccion' => '',
                'localidad' => '',
                'cue' => '',
                'ee' => '',
                'logo_file' => null,
            ];

            return $memo;
        }

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['insti', 'direccion', 'localidad', 'cue', 'ee', 'logo_path']);

        $insti = trim((string) ($ento?->insti ?? ''));
        $direccion = trim((string) ($ento?->direccion ?? ''));
        $localidad = trim((string) ($ento?->localidad ?? ''));
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

        $memo = [
            'insti' => $insti,
            'direccion' => $direccion,
            'localidad' => $localidad,
            'cue' => $cue,
            'ee' => $ee,
            'logo_file' => $logoFile,
        ];

        return $memo;
    }
}

if (! function_exists('studentPdfHeaderData')) {
    /**
     * Encabezado institucional para PDFs del portal alumno (Dompdf), según `studentCtx()->idNivel`.
     *
     * @return array{insti:string,direccion:string,localidad:string,cue:string,ee:string,logo_file:?string}
     */
    function studentPdfHeaderData(): array
    {
        static $memo = null;
        static $done = false;

        if ($done) {
            /** @var array $memo */
            return $memo;
        }
        $done = true;

        $idNivel = (int) (studentCtx()->idNivel ?? 0);
        if ($idNivel <= 0) {
            $memo = [
                'insti' => '',
                'direccion' => '',
                'localidad' => '',
                'cue' => '',
                'ee' => '',
                'logo_file' => null,
            ];

            return $memo;
        }

        $ento = Ento::query()
            ->where('idNivel', $idNivel)
            ->first(['insti', 'direccion', 'localidad', 'cue', 'ee', 'logo_path']);

        $insti = trim((string) ($ento?->insti ?? ''));
        $direccion = trim((string) ($ento?->direccion ?? ''));
        $localidad = trim((string) ($ento?->localidad ?? ''));
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

        $memo = [
            'insti' => $insti,
            'direccion' => $direccion,
            'localidad' => $localidad,
            'cue' => $cue,
            'ee' => $ee,
            'logo_file' => $logoFile,
        ];

        return $memo;
    }
}

if (! function_exists('schoolNombre')) {
    /**
     * Nombre institucional del colegio para el nivel activo en sesión.
     * Lee `ento.insti` filtrado por `schoolCtx()->idNivel`.
     * Fallback: `config('tenant.nombre')` y luego 'Colegio'.
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
        return \App\Support\Cuotas\CuotasImportesCatalog::valoresInicialesRegistro();
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

if (! function_exists('tenantPortalDocenteCuadernoSeguimientoAulico')) {
    /**
     * Si el Menú de Docentes incluye el Cuaderno de Seguimiento Áulico (secundario).
     * Default false; activar en `config/tenants/{slug}.php`.
     */
    function tenantPortalDocenteCuadernoSeguimientoAulico(): bool
    {
        return (bool) config('tenant.portal_docente.cuaderno_seguimiento_aulico', false);
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
     * Default habilitado (`config/tenant.php`); desactivar en `config/tenants/{slug}.php`.
     */
    function tenantAutogestionActualizacionDatosHabilitada(): bool
    {
        return (bool) config('tenant.autogestion.actualizacion_datos.habilitado', true);
    }
}

if (! function_exists('tenantAutogestionActualizacionDatosRequiereDocumentos')) {
    /**
     * Si el formulario exige aceptación de documentos institucionales antes de guardar.
     */
    function tenantAutogestionActualizacionDatosRequiereDocumentos(): bool
    {
        return tenantAutogestionActualizacionDatosImplementacion() === 'sanfranciscoasis';
    }
}

if (! function_exists('tenantAutogestionActualizacionDatosLivewireComponent')) {
    /**
     * Componente Livewire del formulario según la variante del tenant.
     *
     * @return class-string<\Livewire\Component>
     */
    function tenantAutogestionActualizacionDatosLivewireComponent(): string
    {
        return match (tenantAutogestionActualizacionDatosImplementacion()) {
            'sanfranciscoasis' => \App\Livewire\Alumnos\ActualizacionDatosPersonalesSanFranciscoAsisForm::class,
            default => \App\Livewire\Alumnos\ActualizacionDatosPersonalesEstandarForm::class,
        };
    }
}

if (! function_exists('tenantAutogestionFichaMatriculaHabilitada')) {
    /**
     * Si el portal familia incluye impresión de ficha de matrícula en PDF.
     * Default false; activar en `config/tenants/{slug}.php` con `implementacion` definida.
     */
    function tenantAutogestionFichaMatriculaHabilitada(): bool
    {
        if (! (bool) config('tenant.autogestion.ficha_matricula.habilitado', false)) {
            return false;
        }

        return filled(config('tenant.autogestion.ficha_matricula.implementacion'));
    }
}

if (! function_exists('tenantSecretariaFichaMatriculaImplementacion')) {
    /**
     * Variante de ficha de matrícula para secretaría (`sanfranciscoasis` | `montecristo`).
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
     */
    function tenantSecretariaFichaMatriculaHabilitada(): bool
    {
        if (! (bool) config('tenant.secretaria.ficha_matricula.habilitado', false)) {
            return false;
        }

        return filled(tenantSecretariaFichaMatriculaImplementacion());
    }
}

if (! function_exists('tenantSecretariaFichaMatriculaEtiqueta')) {
    /**
     * Título del ítem de menú / pantalla según la variante del tenant.
     */
    function tenantSecretariaFichaMatriculaEtiqueta(): string
    {
        return match (tenantSecretariaFichaMatriculaImplementacion()) {
            'montecristo' => 'Ficha de Solicitud de Matrícula',
            'sanfranciscoasis' => 'Ficha de Matrícula',
            default => 'Ficha de Matrícula',
        };
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
