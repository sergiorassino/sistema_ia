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
- Con `ento.matriAnioSiguiente` = 1 (Parametrización → Parámetros, por nivel), la aceptación
  de documentos (`matricula.acept1`–`acept4`) y el bloqueo de esta pantalla usan la matrícula
  del año siguiente. Los datos del legajo siguen siendo los mismos. Si no hay esa matrícula,
  el formulario muestra el aviso y no graba la aceptación sobre el ciclo abierto.
  Los PDF se leen del nivel de esa matrícula (en un pase de nivel, los del nivel de destino).
- Bloqueo pedagógico (`matricula.bloqmatr`) y/o administrativo (`matricula.bloqadmi`):
  impide **entrar** a este módulo y a Imprimir Ficha de Matrícula.
  El mensaje es el de `ento.mensajeBloqPeda` / `ento.mensajeBloqAdmi` del nivel del alumno
  (`MatriculaBloqueos::impideFichaYDatosAutogestion()`). Si hay ambos bloqueos, se muestran los dos textos.
  Ese cartel es solo de autogestión; no es el cuerpo de Notif. Bloqueo / Desbloqueo.
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
- **San José:** `php artisan migrate` con `TENANT_SLUG=sanjose` (migración
  `2026_09_16_120000_seed_solapa_foto_carnet_sanjose`). En otros tenants esa
  migración no hace nada. Equivalente SQL:
  `database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql`.
- **Otros colegios (p. ej. Caixal SF):** ejecutar
  `database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql` en la BD del tenant
  (o crear la solapa y asignar el campo en Parametrización → Solapas del legajo /
  Campos del listado de alumnos).

Tenants:

- **Caixal SF:** Secretaría (solapa) + autogestión (`foto_carnet => true`) + modal de foto en carga de calificaciones (Secretaría y Menú de Docentes, secundario).
- **Montecristo:** igual que Caixal SF (solapa + autogestión + modal en carga + modelo Fotos de listados con formato). Activar `foto_carnet` en `config/tenants/montecristo.php`.
- **San José:** igual que Caixal SF (solapa + autogestión). Activar `foto_carnet` en `config/tenants/sanjose.php`. Solapa: migración `2026_09_16_120000_seed_solapa_foto_carnet_sanjose` o el SQL idempotente.
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
- **Destinatario de facturación ARCA** (ambas variantes): `legajos.respAdmiNom`, `legajos.respAdmiDni` — obligatorios (nombre real + DNI de 7 a 11 dígitos; no admite guión). **Solo si el colegio tiene nivel Administración** (`niveles.id = 5` y, con filtro de login, incluido en `tenant.login.niveles_ids`). Sin ese nivel no hay sistema de cuotas: el bloque no se muestra y no se valida ni se pisa al guardar. Helper: `NivelSistema::tieneNivelAdministracion()` / `nivelSistemaTieneAdministracion()`.
- **Variante `sanfranciscoasis` (además):** `reglamApenom`, `reglamDni`, `reglamEmail`, `ec_padres`, `contacto1`, `contacto2`, `contacto3`, `retira1`, `obs_web`. Si faltan, el guardado se aborta (sin falso éxito). SQL: `database/sql/legajos_sfa_autogestion_columnas_idempotente.sql`. Tenants que usan esta variante: `sanfranciscoasis`, `epq`, `sfq`.
- **Contactos unificados (solo San Francisco de Asís):** `config/tenants/sanfranciscoasis.php` → `autogestion.actualizacion_datos.contactos_emergencia_unificados`. Reemplaza `contacto1`–`contacto3` y `retira1` por la tabla `legajo_autorizados_emergencia` (apellido y nombre, DNI, relación, teléfono). La familia carga de 2 a 6 personas. Esos cuatro campos de `legajos` no se pisan al guardar. EPQ y SFQ siguen con los textos libres. SQL: `database/sql/legajo_autorizados_emergencia.sql`. Helper: `tenantAutogestionContactosEmergenciaUnificados()`.
- `matricula` (bloqueos / aceptaciones SFA). Con `ento.matriAnioSiguiente` = 1, la fila del año siguiente.
- `ento.matriAnioSiguiente` (flag por nivel, junto a `idTerlecVerNotas`)
- `ento` (`mensajeBloqPeda`, `mensajeBloqAdmi` del nivel)
- `campos_legajo` / `solapas_legajo` (visibilidad de foto carnet)
- Documentos estudiante (estándar): tipos configurados por matrícula web. Las fotos JPG/JPEG se guardan como JPG (lado largo máx. 1200 px, calidad 25), una por archivo (`{dni}_{clave}.jpg`, `{dni}_{clave}_2.jpg`, …). No se convierten a PDF. Un PDF subido se guarda como PDF. Los PDF ya guardados siguen abriéndose. La familia los ve en un modal de la misma pantalla (la foto va embebida, con zoom y un botón Rotar; el PDF, en un recuadro). Rotar gira la imagen 90° a la derecha, vuelve a comprimirla (lado largo máx. 1200 px, calidad 25) y reemplaza el archivo, así la próxima apertura (familia o Secretaría) ya sale derecha. En Secretaría el botón solo aparece con permiso de modificación del legajo. Un PDF no se rota. Al elegir el archivo se guarda solo. Si ya hay uno, hay que eliminarlo para poder cargar otro. Carpeta: `storage/app/private/ento/doc-estudiante/{tenant}/`.
- **Solapa DOCUMENTACIÓN (Menú de Secretaría, ABM de legajos):** aparece a la derecha de la solapa Foto Carnet solo si hay tipos activos en Documentos a subir (familia). No es una fila de `solapas_legajo`. Muestra los mismos archivos (recibidos o pendientes) y permite subir o eliminar con permiso de modificación del legajo. La consulta puede abrir el archivo. El alta usa el DNI ya guardado: hay que guardar el legajo antes de cargar archivos. El PDF se abre por `abm.legajos.documento-estudiante` (token opaco), no por la ruta del portal de alumnos.
- **Descarga por curso:** Menú de Secretaría / Administración → Estudiantes → Descarga de documentación (permiso IA orden 117). ZIP con una carpeta del curso y una carpeta por alumno regular que tenga archivos. Ficha: [descarga-documentacion-curso.md](descarga-documentacion-curso.md).

## Archivos clave

- `app/Livewire/Alumnos/ActualizacionDatosPersonalesEstandarForm.php`
- `app/Support/Alumnos/DocumentosEstudianteAutogestion.php`
- `app/Http/Controllers/Alumnos/DocumentoEstudianteAutogestionPdfController.php`
- `app/Http/Controllers/Abm/DocumentoEstudianteLegajoController.php`
- `app/Support/Alumnos/SolapaDocumentacionLegajo.php`
- `app/Livewire/Abm/Legajos/Concerns/ConDocumentacionEstudianteLegajo.php`
- `resources/views/livewire/partials/documentos-estudiante-lista.blade.php`
- `resources/views/livewire/partials/documentos-estudiante-modal.blade.php`
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
- No mostrar destinatario ARCA si el colegio no tiene nivel Administración (cuotas). No ramificar por `tenantSlug() === 'sanjose'`: usar `NivelSistema::tieneNivelAdministracion()`. Sin ese nivel, no validar ni pisar `respAdmiNom` / `respAdmiDni` al guardar.
- No mostrar foto carnet en autogestión solo porque está en solapas: hace falta `foto_carnet` del tenant.
- No mostrar foto carnet en Secretaría (ABM ni modal de carga) ni en el portal docente si no está en solapas (aunque la columna exista).
- No crear una solapa `documentacion` en `solapas_legajo` ni mostrarla si no hay tipos activos. No servir el PDF del legajo con la ruta de autogestión (`auth:alumno`). No asociar el archivo al DNI escrito en el formulario si todavía no se guardó.
- No poner IDs de legajo en URLs de la foto en carga de calificaciones; Secretaría y docentes usan `OpaqueRouteToken`.
- No calcular promedios ni tocar calificaciones desde este módulo.
- No dejar entrar al formulario si hay `bloqmatr` o `bloqadmi`; usar el mensaje de `ento` del nivel, no un texto genérico.
- **Guión (-) de “no corresponde”:** es un valor válido. Se acepta uno o más guiones (`-`, `--`, `---`) y rayas tipográficas; se normalizan a un solo `-`. Tras un intento de guardado fallido, hay que `resetValidation` en **todos** los campos al editarlos (no solo e-mail); si no, el error de obligatorio queda pegado aunque el usuario ya haya escrito el guión. Normalizar con `ActualizacionDatosPersonalesComun::normalizarTextoInput()`. Si `dnitut`/`dnipad`/`dnimad` es columna numérica, el guión se guarda como `0` y al recargar el formulario se vuelve a mostrar `-`. El `UPDATE` de `legajos` usa `PersistenciaColumnas` (sin falso éxito).
- **Esquema incompleto (EPQ y otros):** el guión cuenta como valor. Si el formulario SFA pide un campo cuya columna no existe en `legajos`, el error es “faltan columnas…”. No omitir el campo: agregar las columnas (migración o SQL de esta ficha).
- **Contactos unificados:** no activar `contactos_emergencia_unificados` en EPQ ni SFQ. En San Francisco de Asís la lista no admite guión de “no corresponde”: las 2 a 6 filas visibles son obligatorias (DNI de 7 a 11 dígitos). Si falta la tabla, el guardado muestra error y no confirma éxito. La ficha PDF de autogestión y la de Secretaría usan estas filas (aunque todavía no haya ninguna) y no vuelven a imprimir `contacto1`–`contacto3` ni `retira1`.
