# Módulo: Actualización de datos personales (portal familia)

## Propósito

Formulario del Menú de Alumnos para que la familia actualice datos del legajo (contacto de padres/tutor, etc.) en el ciclo de autogestión.

## Modalidades / variantes

Config: `config/tenant.php` / `config/tenants/{slug}.php` → `autogestion.actualizacion_datos`
(`habilitado`, `implementacion`).

**Visibilidad por nivel (Menú de Alumnos):** Parametrización del sistema → solapa **Parámetros** →
flag `ento.verDatosFicha` (mismo control que Imprimir Ficha de Matrícula). Helper:
`entoAutogestionVerDatosYFichaHabilitada()` / `tenantAutogestionActualizacionDatosHabilitada()`.

| `implementacion` | Componente Livewire |
|------------------|---------------------|
| `estandar` (default) | `ActualizacionDatosPersonalesEstandarForm` |
| `sanfranciscoasis` | `ActualizacionDatosPersonalesSanFranciscoAsisForm` |

Helpers: `tenantAutogestionActualizacionDatosHabilitada()`, `…Implementacion()`, `…LivewireComponent()`.

## Actores y permisos

- Auth guard `alumno`; matrícula del ciclo `ento.idTerlecVerNotas`.
- Bloqueo pedagógico (`matricula.bloqmatr`) y/o administrativo (`matricula.bloqadmi`):
  impide **entrar** a este módulo y a Imprimir Ficha de Matrícula.
  El mensaje es el de `ento.mensajeBloqPeda` / `ento.mensajeBloqAdmi` del nivel del alumno
  (`MatriculaBloqueos::impideFichaYDatosAutogestion()`). Si hay ambos bloqueos, se muestran los dos textos.
  En el menú: SweetAlert al clic (no navega). Si se abre la URL: solo el aviso, sin formulario.

## Foto carnet

Solo se muestra el upload de `legajos.fotoCarnet` si:

1. Existe la columna en la BD del tenant, y
2. El campo está **asignado a alguna solapa** en parametrización de legajos (`campos_legajo.solapa_legajo_id` no nulo).

Misma detección: `FotoCarnetLegajo::habilitadaEnSolapasLegajo()`. Persistencia y compresión: `FotoCarnetLegajo` (disco `privado`).

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

## Qué no hacer

- No mostrar foto carnet si no está en solapas (aunque la columna exista).
- No poner IDs de legajo en URLs del portal; la vista previa usa data-URL embebida.
- No calcular promedios ni tocar calificaciones desde este módulo.
- No dejar entrar al formulario si hay `bloqmatr` o `bloqadmi`; usar el mensaje de `ento` del nivel, no un texto genérico.
