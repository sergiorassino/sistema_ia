# Módulo: Actualización de datos personales (portal familia)

## Propósito

Formulario del Menú de Alumnos para que la familia actualice datos del legajo (contacto de padres/tutor, etc.) en el ciclo de autogestión.

## Modalidades / variantes

Config: `config/tenant.php` / `config/tenants/{slug}.php` → `autogestion.actualizacion_datos`
(`habilitado`, `implementacion`, `foto_carnet`, `requiere_documentos`).

**Visibilidad por nivel (Menú de Alumnos):** Parametrización del sistema → solapa **Parámetros** →
flag `ento.verDatosFicha` (mismo control que Imprimir Ficha de Matrícula). Helper:
`entoAutogestionVerDatosYFichaHabilitada()` / `tenantAutogestionActualizacionDatosHabilitada()`.

| `implementacion` | Componente Livewire |
|------------------|---------------------|
| `estandar` (default) | `ActualizacionDatosPersonalesEstandarForm` |
| `sanfranciscoasis` | `ActualizacionDatosPersonalesSanFranciscoAsisForm` |

Helpers: `tenantAutogestionActualizacionDatosHabilitada()`, `…Implementacion()`, `…LivewireComponent()`, `…FotoCarnetHabilitada()`, `…RequiereDocumentos()`.

## Documentos institucionales (variante SFA)

El bloque de los cuatro PDF (compromiso, AEC, normas, traslado) es **independiente** de la implementación:

| Clave | Efecto |
|-------|--------|
| `implementacion` = `sanfranciscoasis` | Formulario completo SFA (adulto responsable, etc.). |
| `requiere_documentos` = `true` (default) | Muestra y exige esas aceptaciones antes de guardar. |
| `requiere_documentos` = `false` | Mismo formulario SFA **sin** el bloque. La familia puede guardar sin aceptar. Las rutas de lectura/aceptación responden 404. |

Helper: `tenantAutogestionActualizacionDatosRequiereDocumentos()` (exige ambas: implementación SFA **y** el flag).

Tenants:

- **San Francisco de Asís / SFQ:** formulario SFA con documentos (default).
- **EPQ:** formulario SFA sin documentos (`requiere_documentos => false`).

```php
// config/tenants/{slug}.php
'autogestion' => [
    'actualizacion_datos' => [
        'implementacion' => 'sanfranciscoasis',
        'requiere_documentos' => false,
    ],
],
```

## Actores y permisos

- Auth guard `alumno`; matrícula del ciclo `ento.idTerlecVerNotas`.
- Bloqueo pedagógico (`matricula.bloqmatr`) y/o administrativo (`matricula.bloqadmi`):
  impide **entrar** a este módulo y a Imprimir Ficha de Matrícula.
  El mensaje es el de `ento.mensajeBloqPeda` / `ento.mensajeBloqAdmi` del nivel del alumno
  (`MatriculaBloqueos::impideFichaYDatosAutogestion()`). Si hay ambos bloqueos, se muestran los dos textos.
  En el menú: SweetAlert al clic (no navega). Si se abre la URL: solo el aviso, sin formulario.

## Foto carnet

La solapa del legajo y la carga en autogestión son **independientes**:

| Dónde | Qué lo habilita |
|-------|-----------------|
| **Menú de Secretaría** (ABM de legajos) | Columna `legajos.fotoCarnet` + campo asignado a una solapa (`campos_legajo.solapa_legajo_id` no nulo). `FotoCarnetLegajo::habilitadaEnSolapasLegajo()`. |
| **Menú de Secretaría / Docentes** (Carga de calificaciones, secundario) | Lo mismo: si hay solapa, el apellido y nombre de la planilla abre un modal con la foto (Alpine, sin round-trip Livewire). Secretaría: `calificacionesSecundario.fotoCarnet`. Docentes: `portalDocente.fotoCarnet`. |
| **Menú de Alumnos** (Actualización de datos personales) | Solapa **y** `autogestion.actualizacion_datos.foto_carnet => true` en `config/tenants/{slug}.php`. Default **off**. `FotoCarnetLegajo::habilitadaEnAutogestion()`. |

Así se puede cargar la foto desde Secretaría sin mostrarla a la familia.

Persistencia y compresión: `FotoCarnetLegajo` (disco `privado`). Helper: `tenantAutogestionActualizacionDatosFotoCarnetHabilitada()`.

En celular, `accept="image/jpeg,image/png"` abre solo Fotos/Galería. El control usa dos acciones: **Tomar foto** (`capture` + `image/*`, cámara) y **Galería**. Máx. 8 MB al subir; se comprime al guardar. La foto no queda en el legajo hasta pulsar Guardar.

Para la solapa (ABM):

- **IESS:** `php artisan migrate` con `TENANT_SLUG=iess` (migración
  `2026_09_01_120000_seed_solapa_foto_carnet_iess`). En otros tenants esa
  migración no hace nada.
- **Montecristo:** `php artisan migrate` con `TENANT_SLUG=montecristo` (migración
  `2026_09_03_120000_seed_solapa_foto_carnet_montecristo`). En otros tenants esa
  migración no hace nada. Equivalente SQL:
  `database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql`.
- **Otros colegios (p. ej. Caixal SF):** ejecutar
  `database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql` en la BD del tenant
  (o crear la solapa y asignar el campo en Parametrización → Solapas del legajo /
  Campos del listado de alumnos).

Tenants:

- **Caixal SF:** Secretaría (solapa) + autogestión (`foto_carnet => true`) + modal de foto en carga de calificaciones (Secretaría y Menú de Docentes, secundario).
- **Montecristo:** igual que Caixal SF (solapa + autogestión + modal en carga + modelo Fotos de listados con formato). Activar `foto_carnet` en `config/tenants/montecristo.php`.
- **IESS:** Secretaría + mismo modal en carga de calificaciones si la solapa está activa. No activar `foto_carnet` en `config/tenants/iess.php`.

Para que la familia también pueda subirla, además:

```php
// config/tenants/{slug}.php
'autogestion' => [
    'actualizacion_datos' => [
        'foto_carnet' => true,
    ],
],
```

## Tablas y campos críticos

- `legajos` (datos editables según variante; `fechActDatos`; opcional `fotoCarnet`)
- **Destinatario de facturación ARCA** (ambas variantes): `legajos.respAdmiNom`, `legajos.respAdmiDni` — obligatorios (nombre real + DNI de 7 a 11 dígitos; no admite guión)
- **Variante `sanfranciscoasis` (además):** `reglamApenom`, `reglamDni`, `reglamEmail`, `ec_padres`, `contacto1`, `contacto2`, `contacto3`, `retira1`, `obs_web`. Si faltan, el guardado se aborta (sin falso éxito). SQL: `database/sql/legajos_sfa_autogestion_columnas_idempotente.sql`. Tenants que usan esta variante: `sanfranciscoasis`, `epq`, `sfq`.
- `matricula` (bloqueos / aceptaciones SFA)
- `ento` (`mensajeBloqPeda`, `mensajeBloqAdmi` del nivel)
- `campos_legajo` / `solapas_legajo` (visibilidad de foto carnet)
- Documentos estudiante (estándar): tipos configurados por matrícula web

## Archivos clave

- `app/Livewire/Alumnos/ActualizacionDatosPersonalesEstandarForm.php`
- `app/Livewire/Alumnos/ActualizacionDatosPersonalesSanFranciscoAsisForm.php`
- `app/Livewire/Alumnos/Concerns/ConFotoCarnetActualizacionDatos.php`
- `app/Support/Alumnos/ActualizacionDatosPersonales*.php`
- `resources/views/livewire/alumnos/partials/destinatario-facturacion-afip.blade.php`
- `app/Support/MatriculaBloqueos.php`
- `resources/views/livewire/alumnos/partials/foto-carnet-actualizacion.blade.php`
- `app/Http/Controllers/PortalDocente/PortalDocenteFotoCarnetController.php`
- `app/Http/Controllers/CalificacionesSecundario/CalifSecundarioFotoCarnetController.php`
- `resources/views/livewire/calificaciones-secundario/carga-calificaciones-secundario.blade.php`

## Qué no hacer / trampas

- No mostrar documentos institucionales en un tenant SFA con `requiere_documentos => false` (EPQ). No cambiar a `estandar` solo para ocultarlos: se pierde el resto del formulario.
- No mostrar foto carnet en autogestión solo porque está en solapas: hace falta `foto_carnet` del tenant.
- No mostrar foto carnet en Secretaría (ABM ni modal de carga) ni en el portal docente si no está en solapas (aunque la columna exista).
- No poner IDs de legajo en URLs de la foto en carga de calificaciones; Secretaría y docentes usan `OpaqueRouteToken`.
- No calcular promedios ni tocar calificaciones desde este módulo.
- No dejar entrar al formulario si hay `bloqmatr` o `bloqadmi`; usar el mensaje de `ento` del nivel, no un texto genérico.
- **Guión (-) de “no corresponde”:** es un valor válido. Se acepta uno o más guiones (`-`, `--`, `---`) y rayas tipográficas; se normalizan a un solo `-`. Tras un intento de guardado fallido, hay que `resetValidation` en **todos** los campos al editarlos (no solo e-mail); si no, el error de obligatorio queda pegado aunque el usuario ya haya escrito el guión. Normalizar con `ActualizacionDatosPersonalesComun::normalizarTextoInput()`. Si `dnitut`/`dnipad`/`dnimad` es columna numérica, el guión se guarda como `0` y al recargar el formulario se vuelve a mostrar `-`. El `UPDATE` de `legajos` usa `PersistenciaColumnas` (sin falso éxito).
- **Esquema incompleto (EPQ y otros):** el guión cuenta como valor. Si el formulario SFA pide un campo cuya columna no existe en `legajos`, el error es “faltan columnas…”. No omitir el campo: agregar las columnas (migración o SQL de esta ficha).
