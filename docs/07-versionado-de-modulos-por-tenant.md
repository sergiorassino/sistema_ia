# Personalización por colegio (tenant)

> Cómo diferenciar funcionalidades entre escuelas sin afectar a las demás.
> Antes de tocar un módulo compartido, leer este documento.

---

## 1. Modelo de despliegue

Cada colegio es un **tenant** identificado por `TENANT_SLUG` en `.env`:

- **Base de datos propia** (`DB_DATABASE`, habitualmente `ia_{slug}` salvo excepciones en `SwitchTenantCommand`).
- **Mismo código** Laravel en `sistema/` (monorepo compartido).
- **Overrides livianos** versionados en `config/tenants/{slug}.php`.

No usamos multi-tenant en una sola BD con `tenant_id` en cada fila: el aislamiento fuerte es **instalación (o entorno) + BD separada**.

En desarrollo local: `php artisan se:switch {slug}` cambia `TENANT_SLUG` y `DB_DATABASE` en el `.env` activo.

---

## 2. Qué **no** usamos (histórico)

Se evaluó empaquetar variantes de módulos con **Composer path** (`packages/modulo-*`, dependencias `se/modulo-*`, un `composer.json` distinto por colegio). Ese enfoque **se descartó**: complejidad operativa, despliegues difíciles de mantener y poco valor frente a las alternativas actuales.

**No documentar ni implementar** paquetes `se/modulo-*`, `TenantOverridesServiceProvider`, vistas en `resources/views/custom/{slug}/`, ni helpers `tenantConfig()` para elegir versiones de módulo. La carpeta `packages/` queda vacía a propósito.

---

## 3. Capas de personalización (de menor a mayor impacto)

### 3.1 Configuración en archivos (`config/tenant.php` + `config/tenants/{slug}.php`)

`TenantConfigMergeServiceProvider` hace merge recursivo del archivo del slug sobre los defaults.

- Defaults: `config/tenant.php`.
- Solo diferencias del colegio: `config/tenants/{slug}.php` (versionado en git).
- Leer valores con `config('tenant.clave')` o `config('tenant.bloque.clave')`.

Ejemplo real (Montecristo — enlace externo en portal alumno):

```php
// config/tenants/montecristo.php
return [
    'autogestion' => [
        'aranceles_aulica_url' => 'https://familia.aulica.com.ar/login?idCompany=953',
    ],
];
```

**Regla:** en `config/tenants/{slug}.php` declarar **solo** lo que difiere del default. Si coincide con `config/tenant.php`, no repetirlo.

Usos típicos: URLs de terceros, flags de comportamiento, textos o límites que no convenga guardar en BD.

Ejemplo (tercer materia en boletín / consulta de calificaciones — solo colegios que lo usan):

```php
// config/tenants/{slug}.php
return [
    'boletin' => [
        'mostrar_tercer_materia' => true,
    ],
];
```

Default en `config/tenant.php`: `false`. Consumir con `tenantBoletinMuestraTercerMateria()` o `config('tenant.boletin.mostrar_tercer_materia')`.

Modalidad de actas volantes de previos (secundario):

- Default en `config/tenant.php`: `curso_seccion` (una acta por `idMatPlan` + condición + sección estructural; nunca por el texto del nombre de materia).
- Alternativa `curso`: reúne alumnos de distintas secciones del mismo `idMatPlan`.

```php
// config/tenants/{slug}.php — solo si difiere del default
return [
    'examenes' => [
        'acta_volante_previos_modalidad' => 'curso',
    ],
];
```

Consumir con `tenantExamenesActaVolantePreviosModalidad()`.

Registro de asistencia (PDF mensual) — modelo por nivel:

- Default implícito: **`con_datos`** en todos los niveles.
- Override en `config/tenants/{slug}.php` solo si difiere. Ejemplo (Montecristo):

```php
return [
    'registro_asistencia' => [
        'por_nivel' => [
            1 => 'sin_datos', // inicial
            2 => 'sin_datos', // primario
            // 3 queda en con_datos por default
        ],
    ],
];
```

Consumir con `tenantRegistroAsistenciaImplementacion()`. Detalle: [modulos/registro-asistencia.md](modulos/registro-asistencia.md).

Parte diario del preceptor — modelo de PDF:

- Default: **`estandar`** (DomPDF A4 / media hoja).
- Alternativa **`sanfranciscoasis`**: TCPDF Legal con listado de regulares y firmas por hora.

```php
// config/tenants/sanfranciscoasis.php
return [
    'parte_diario' => [
        'implementacion' => 'sanfranciscoasis',
    ],
];
```

Consumir con `tenantParteDiarioImplementacion()`. Detalle: [modulos/parte-diario-preceptor.md](modulos/parte-diario-preceptor.md).

Fórmulas al crear plantilla de cuota (bonificación/interés por vencimiento; default +0 % en los cuatro tramos):

```php
// config/tenants/{slug}.php — solo lo que difiere
return [
    'cuotas' => [
        'formulas_iniciales_plantilla' => [
            'signo1v' => '-',
            'valor1v' => 15.0,
        ],
    ],
];
```

Consumir con `tenantCuotasFormulasInicialesPlantilla()` o `CuotasImportesCatalog::valoresInicialesRegistro()`.

Modo de interpretación del % de mora en tramos 2–4 (`config/tenant.php` → `cuotas.interes_mora_modo`):

- `diario` (default): el % configurado es por día de mora (se multiplica por los días del tramo).
- `total`: el % es fijo sobre el saldo en ese tramo, sin multiplicar por días.

```php
// config/tenants/institutoramallo.php
return [
    'cuotas' => [
        'interes_mora_modo' => 'total',
    ],
];
```

Consumir con `tenantCuotasInteresMoraEsDiario()` o `tenantCuotasInteresMoraModo()`. Afecta imputación, PDF morosos y cupón de pago.

Facturación AFIP en modo `devengamiento` — importe a emitir (todos los colegios): neto con beca (`cuotasgeneradas.importe`); si la fórmula del 1.er vencimiento es bonificación (`cuotasimportes.signo1v = '-'` con valor > 0), neto − bonificación. Lógica en `FacturacionAfipComun::importeAFacturarDevengamiento()`. La leyenda de beca del PDF usa el neto **sin** beca (o ese neto − bonificación): `importeOriginalLeyendaBeca()`.

Correo de recibos cooperadora (origen estudiantes), distinto del cuaderno de comunicados:

- Credenciales SMTP del despliegue: `COOP_MAIL_*` en `.env` (mailer `cooperadora` en `config/mail.php`).
- Flags por colegio: `config/tenant.php` → `cooperadora.recibo_email`; override en `config/tenants/{slug}.php` (`simulado`, `from_name`, `asunto`).
- Helpers: `tenantCooperadoraReciboEmailSimulado()`, `tenantCooperadoraReciboEmailFrom()`.

```php
// config/tenants/{slug}.php — activar envío real en producción
return [
    'cooperadora' => [
        'recibo_email' => [
            'simulado' => false,
            'from_name' => 'Cooperadora Escolar',
        ],
    ],
];
```

El `slug` también se usa en rutas de almacenamiento (ej. logos en `ento/logos/{slug}/…`).

### 3.2 Parametrización en base de datos (principal)

Cada colegio tiene **su propia BD**; la parametrización vive en tablas y pantallas de administración. Es el mecanismo habitual para “este colegio ve otro legajo / otro listado / no usa X”.

| Área | Tablas / pantallas | Efecto |
|------|-------------------|--------|
| **Permisos** | `permisosusuarios`, `profesores.permisos` | Qué entradas del menú y acciones existen (`tienePermiso(orden)`, middleware `permiso:N`). Ver [03-autenticacion-y-permisos.md](03-autenticacion-y-permisos.md). |
| **Legajo — solapas y campos** | `solapas_legajo`, `campos_legajo` | Pestañas y campos visibles en ABM; parametrización en `param.solapas-legajo` y `param.campos-listado-alumnos`. |
| **Listado por curso / PDF** | `campos_legajo.visible_listado` | Columnas del listado y del PDF por curso. |
| **Institucional** | `ento` | Nombre, logo, CUE, etc. por nivel (`param.parametros-sistema`). |
| **Comunicaciones** | `com_*`, preferencias | Canales, reglas y datos del cuaderno de comunicados. |

Apellido, nombre y DNI del legajo son **siempre** obligatorios; no se desactivan por parametrización.

### 3.3 Código compartido en `app/`

Todo el código de módulos vive en el árbol estándar de Laravel:

- PHP: `app/Livewire/`, `app/Http/`, `app/Models/`, `app/Support/`
- Rutas: `routes/web.php`
- Vistas: `resources/views/` (namespaces de vistas vía providers, ej. `listados::` desde `ListadosServiceProvider`)

**No** hay dependencias internas `se/*` en `composer.json`. Los módulos se registran con service providers en `bootstrap/providers.php` (`ListadosServiceProvider`, `ComunicacionesServiceProvider`, etc.).

El menú lateral y el dashboard enlazan **rutas fijas** y muestran u ocultan ítems según `tienePermiso()`. Los tooltips “v1.0” son solo referencia visual; no hay conmutación de versiones por config.

### 3.4 Menú de Docentes y calificaciones primario (implementación vs tenant)

Tres ejes **independientes**:

| Eje | Ejemplo | Dónde |
|-----|---------|--------|
| Tenant (despliegue) | `TENANT_SLUG=montecristo` | `.env` |
| Menú docente (mostrar ítem) | `tenant.portal_docente.menu.primario.carga_estudiante => true` | `config/tenants/{slug}.php` |
| Implementación del módulo | `calificaciones_primario.carga_estudiante.implementacion => montecristo` | `config/tenants/{slug}.php` |

La clave **`montecristo`** en `implementacion` identifica la variante en código (grilla ic01–ic03, carga por materia con parciales, planilla TCPDF actual). **No** significa “solo Montecristo”: otro colegio puede apuntar a la misma variante sin cambiar PHP.

**Defaults** (`config/tenant.php`): menú docente primario desactivado; implementaciones `null`. **Montecristo** (`config/tenants/montecristo.php`): activa menú + implementación `montecristo` en los tres módulos de carga/planilla.

**Registro en código** — `App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos`:

| Módulo (`modulo`) | Clave config | Variante `montecristo` (Livewire) |
|-------------------|--------------|-----------------------------------|
| `carga_estudiante` | `calificaciones_primario.carga_estudiante` | `CargaCalificacionesPrimarioIndex`, `CargaCalificacionesPrimarioForm` |
| `carga_materia` | `calificaciones_primario.carga_materia` | `CargaCalificacionesPrimarioMateria` |
| `planilla` | `calificaciones_primario.planilla` | `PlanillaCalificacionesPrimario`, PDF vía `PlanillaCalificacionesPrimarioPdfController` |

Helpers: `tenantCalificacionesPrimarioCargaEstudianteImplementacion()`, `tenantCalificacionesPrimarioCargaMateriaImplementacion()`, `tenantCalificacionesPrimarioPlanillaImplementacion()`.

**Menú docente** — catálogo en `PortalDocenteMenuCatalog`, filtro en `PortalDocenteMenu::itemsParaSesionActual()`. Ítems secundario usan `portal_docente.menu.secundario.*`; solicitud de evaluación exige además `modulos.solicitud_evaluacion`.

**Al agregar una variante nueva** (otro colegio con UI distinta):

1. Implementar Livewire/controladores bajo `app/Livewire/CalificacionesPrimario/`.
2. Registrar la clave en `CalificacionesPrimarioModulos::registro()` (p. ej. `'legacy_xyz' => [ … ]`).
3. Activar en `config/tenants/{slug}.php`: `implementacion` + flags de menú docente si aplica.
4. Actualizar esta tabla en el PR.

**Prohibido:** `if (tenantSlug() === 'montecristo')` en Blade o Livewire para elegir pantalla; usar siempre `implementacion` desde config.

Ver también [08-menus-de-navegacion.md](08-menus-de-navegacion.md) §3 (sidebar dinámico).

---

## 4. Módulos de referencia (ubicación actual)

| Módulo | Ubicación principal | Rutas típicas |
|--------|---------------------|---------------|
| **Comunicaciones / cuaderno** | `App\Livewire\Comunicaciones\*`, `App\Comunicaciones\*`, modelos `Com*`, vistas `resources/views/comunicaciones/` | `comunicaciones.*`, `alumnos.comunicaciones.*`, `param.com-canales` |
| **Listados por curso** | `App\Livewire\Listados\ListadoPorCurso`, `ListadoCursoPdfController`, `App\Support\Listados\*`, vistas `resources/views/listados/` | `listados.por-curso`, `listados.por-curso.pdf` |
| **Listados con formato** | `App\Livewire\Listados\ListadoEstudiantesFormato`, `ListadoEstudiantesFormatoPdfController`, `App\Support\Listados\ListadoEstudiantesFormato*` | `listados.estudiantes-formato`, `portalDocente.listados.estudiantesFormato` |
| **Legajos** | `App\Livewire\Abm\Legajos\*`, `LegajoForm` + `solapas_legajo` / `campos_legajo` | `abm.legajos`, `param.campos-listado-alumnos`, `param.solapas-legajo` |
| **Seguimiento disciplinario** | `App\Livewire\Seguimiento\Disciplinario\*` | `seguimiento.disciplinario` |
| **Calificaciones secundario** | `App\Livewire\Calificaciones\*` | `calificacionesSecundario.*` |
| **Boletines secundario** | `App\Livewire\BoletinesSecundario\*`, `BoletinSecundarioPdfController` | `boletinesSecundario.index`, `boletinesSecundario.pdf` |

Los boletines de **primario** e **inicial** serán módulos aparte (rutas, menú y tooltips con nivel explícito). Ver sección 6 de [05-preferencias-y-convenciones.md](05-preferencias-y-convenciones.md).

---

## 5. Cuándo un colegio necesita algo “muy distinto”

Orden recomendado:

1. **¿Se resuelve con permisos o parametrización en BD?** → Usar eso primero.
2. **¿Es un dato o URL fija?** → `config/tenants/{slug}.php`.
3. **¿Requiere lógica o UI incompatible con el resto?** → Implementar en `app/` con ramas explícitas y seguras (`config('tenant.slug')`, feature flag en `config/tenants`, o consulta a tabla de parámetros), **documentando** el caso en el PR. Evitar `if ($colegio === 'x')` dispersos sin registro en config.
4. **¿El cambio es tan grande que no puede convivir en main?** → Rama o despliegue dedicado temporalmente; no reintroducir paquetes Composer por módulo.

Antes de agregar comportamiento solo para un colegio en código compartido, confirmar que no rompe el flujo por defecto de los demás (misma ruta, mismos permisos por defecto, migraciones aditivas).

---

## 6. Flujo de trabajo (ejemplo)

**Montecristo necesita enlace a aranceles en autogestión:**

1. Agregar clave en `config/tenants/montecristo.php`.
2. Consumir con `config('tenant.autogestion.aranceles_aulica_url')` en el **Menú de Alumnos** (`layouts/alumno.blade.php`).
3. En el servidor de Montecristo: `TENANT_SLUG=montecristo` y BD correspondiente.

**Colegio nuevo con legajo distinto:**

1. Clonar `config/tenants/{colegio-parecido}.php` si aplica; ajustar solo diferencias.
2. Cargar datos en su BD: solapas, campos, catálogo de permisos y usuarios.
3. No tocar `composer.json` ni crear carpetas en `packages/`.

**Montecristo — calificaciones primario en Menú de Docentes:**

1. En `config/tenants/montecristo.php`: `calificaciones_primario.*.implementacion = montecristo` y `portal_docente.menu.primario.* = true`.
2. El código de la variante vive en `CalificacionesPrimarioModulos` (registro) y los Livewire actuales.
3. Otro colegio con la misma lógica: copiar solo el bloque de config, sin renombrar clases.

---

## 7. Checklist para PRs que afectan varios colegios

- [ ] ¿El cambio es seguro con parametrización por defecto (sin config de tenant)?
- [ ] Si hay rama por `tenant.slug` o config, ¿está documentada la clave en `config/tenant.php` o en el tenant file?
- [ ] ¿Migraciones aditivas y compatibles con BDs ya en producción?
- [ ] ¿Menú y rutas respetan `tienePermiso()` y `schoolCtx()`?
- [ ] ¿No se reintroducen dependencias `se/modulo-*` ni documentación del patrón Composer descartado?
