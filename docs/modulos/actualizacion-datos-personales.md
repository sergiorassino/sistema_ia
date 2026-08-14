# Módulo: Actualización de datos personales (portal familia)

## Propósito

Formulario del Menú de Alumnos para que la familia actualice datos del legajo (contacto de padres/tutor, etc.) en el ciclo de autogestión.

## Modalidades / variantes

Config: `config/tenant.php` / `config/tenants/{slug}.php` → `autogestion.actualizacion_datos`
(`habilitado`, `implementacion`, `foto_carnet`).

**Visibilidad por nivel (Menú de Alumnos):** Parametrización del sistema → solapa **Parámetros** →
flag `ento.verDatosFicha` (mismo control que Imprimir Ficha de Matrícula). Helper:
`entoAutogestionVerDatosYFichaHabilitada()` / `tenantAutogestionActualizacionDatosHabilitada()`.

| `implementacion` | Componente Livewire |
|------------------|---------------------|
| `estandar` (default) | `ActualizacionDatosPersonalesEstandarForm` |
| `sanfranciscoasis` | `ActualizacionDatosPersonalesSanFranciscoAsisForm` |

Helpers: `tenantAutogestionActualizacionDatosHabilitada()`, `…Implementacion()`, `…LivewireComponent()`, `…FotoCarnetHabilitada()`.

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
| **Menú de Alumnos** (Actualización de datos personales) | Lo anterior **y** `autogestion.actualizacion_datos.foto_carnet => true` en `config/tenants/{slug}.php`. Default **off**. `FotoCarnetLegajo::habilitadaEnAutogestion()`. |

Así se puede cargar la foto desde Secretaría sin mostrarla a la familia.

Persistencia y compresión: `FotoCarnetLegajo` (disco `privado`). Helper: `tenantAutogestionActualizacionDatosFotoCarnetHabilitada()`.

En celular, `accept="image/jpeg,image/png"` abre solo Fotos/Galería. El control usa dos acciones: **Tomar foto** (`capture` + `image/*`, cámara) y **Galería**. Máx. 8 MB al subir; se comprime al guardar. La foto no queda en el legajo hasta pulsar Guardar.

Para la solapa (ABM), como en Caixal SF: ejecutar
`database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql` en la BD del tenant
(o crear la solapa y asignar el campo en Parametrización → Solapas del legajo /
Campos del listado de alumnos). Para que la familia también pueda subirla, además:

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
- `matricula` (bloqueos / aceptaciones SFA)
- `ento` (`mensajeBloqPeda`, `mensajeBloqAdmi` del nivel)
- `campos_legajo` / `solapas_legajo` (visibilidad de foto carnet)
- Documentos estudiante (estándar): tipos configurados por matrícula web

## Archivos clave

- `app/Livewire/Alumnos/ActualizacionDatosPersonalesEstandarForm.php`
- `app/Livewire/Alumnos/ActualizacionDatosPersonalesSanFranciscoAsisForm.php`
- `app/Livewire/Alumnos/Concerns/ConFotoCarnetActualizacionDatos.php`
- `app/Support/Alumnos/ActualizacionDatosPersonales*.php`
- `app/Support/MatriculaBloqueos.php`
- `resources/views/livewire/alumnos/partials/foto-carnet-actualizacion.blade.php`

## Qué no hacer / trampas

- No mostrar foto carnet en autogestión solo porque está en solapas: hace falta `foto_carnet` del tenant.
- No mostrar foto carnet en Secretaría si no está en solapas (aunque la columna exista).
- No poner IDs de legajo en URLs del portal; la vista previa usa data-URL embebida.
- No calcular promedios ni tocar calificaciones desde este módulo.
- No dejar entrar al formulario si hay `bloqmatr` o `bloqadmi`; usar el mensaje de `ento` del nivel, no un texto genérico.
- **Guión (-) de “no corresponde”:** es un valor válido. Se acepta uno o más guiones (`-`, `--`, `---`) y rayas tipográficas; se normalizan a un solo `-`. Tras un intento de guardado fallido, hay que `resetValidation` en **todos** los campos al editarlos (no solo e-mail); si no, el error de obligatorio queda pegado aunque el usuario ya haya escrito el guión. Normalizar con `ActualizacionDatosPersonalesComun::normalizarTextoInput()`. Si `dnitut`/`dnipad`/`dnimad` es columna numérica, el guión se guarda como `0` y al recargar el formulario se vuelve a mostrar `-`. El `UPDATE` de `legajos` usa `PersistenciaColumnas` (sin falso éxito).
