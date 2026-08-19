# Autenticación y Permisos

---

## 1. Portales y logins

Hay **tres menús de navegación** (ver [08-menus-de-navegacion.md](08-menus-de-navegacion.md)) y **dos logins** independientes hoy:

| Menú | Login |
|------|--------|
| **Menú de Secretaría** | `/loginUsuario` → `profesores` |
| **Menú de Docentes** | Mismo login que Secretaría (redirección por rol: pendiente) |
| **Menú de Alumnos** | `/loginEstudiante` → `legajos` |

---

### 1.1 Login de Secretaría (tabla `profesores`)

Aplica a: secretarios, administración, preceptores y también profesores que entren por este login.
Alcance habitual: **Menú de Secretaría** (`layouts/app.blade.php`). Los profesores con rol acotado irán al **Menú de Docentes** cuando esté definida la redirección.

| Campo          | Origen                           |
|----------------|----------------------------------|
| Usuario        | `profesores.dni`                 |
| Contraseña     | `profesores.pwrd`                |
| Nivel          | Selección en formulario de login |
| Ciclo lectivo  | Selección en formulario de login |

**Implementación actual:**
- Componente Livewire: `App\Livewire\Auth\Login`
- Auth provider custom: `App\Auth\ProfesorUserProvider`
- Al hacer login, se establece el `SchoolContext` (idProfesor, idNivel, idTerlec) en sesión.
- Middleware `EnsureSchoolContext` protege todas las rutas autenticadas.

**Menú según rol (`IdTipoProf` en `profesortipo`):**

| Rol en legajo (ejemplos) | `IdTipoProf` típico | Menú tras login |
|--------------------------|---------------------|-----------------|
| Profesor/a | **6** | **Menú de Docentes** (`/portal-docente`, `portalDocente.*`) |
| Directivo, Secretario, Preceptor, Administrador, Gabinete de orientación | distinto de 6 | **Menú de Secretaría** (`/dashboard`, rutas con `menu.portal:secretaria`) |

Lógica centralizada en `App\Support\ProfesorMenuPortal::ID_TIPO_PROFESOR_AULA` (valor **6**).
Middleware `menu.portal` impide cruzar portales (un profesor no navega ABM de secretaría por URL directa).
Permisos `profesores.permisos` siguen aplicando **dentro** del menú de Secretaría.

---

### 1.2 Login del Menú de Alumnos (tabla `legajos`)

Portal completamente separado del login de Secretaría.

| Campo          | Origen                           |
|----------------|----------------------------------|
| Usuario        | `legajos.dni`                    |
| Contraseña     | `legajos.pwrd`                   |
| Nivel          | No se selecciona                 |
| Ciclo lectivo  | Se toma de `ento.idTerlecVerNotas` |

**Estado:** Implementado (guard `alumno`, layout `alumno.blade.php`).

**Diferencias clave con el login de Secretaría:**
- Sin selección de nivel ni ciclo lectivo en el formulario.
- El ciclo lectivo se determina automáticamente desde `ento.idTerlecVerNotas`.
- Requiere su propio auth provider, guard y rutas separadas.

---

## 2. Manejo de Contraseñas — Texto plano

Las contraseñas se almacenan en **texto plano** en las tablas legacy (`profesores.pwrd`,
`legajos.pwrd`), alineado con el sistema ScriptCase original y la operativa del colegio.

```
┌─────────────────────┐     ┌──────────────────────────────┐
│ Alta / edición /    │────►│ Texto plano en `pwrd`        │
│ blanqueo de clave   │     │ (sin bcrypt ni otro hash)    │
└─────────────────────┘     └──────────────────────────────┘
                                       │
                                       ▼
                             ┌──────────────────────────────┐
                             │ Login: hash_equals()         │
                             │ (ProfesorUserProvider /      │
                             │  AlumnoUserProvider)         │
                             └──────────────────────────────┘
```

**Lógica de validación** (en `ProfesorUserProvider` y `AlumnoUserProvider`):

- Comparar la contraseña ingresada con el valor almacenado usando `hash_equals()`.

**Regla para código nuevo:**

- Al crear usuario, blanquear o cambiar contraseña → guardar **siempre en texto plano**.
- **No** usar bcrypt ni migración automática a hash en el login.

**Motivo operativo:** secretaría informa la clave al docente/alumno; la contraseña debe poder
consultarse en ABM y enviarse por correo en recuperación olvidada cuando corresponda.

### 2.1 Alta de legajo docente (Menú de Secretaría)

Al **crear** un legajo nuevo en **Legajos del docente** (`LegajoProfesorForm`), el sistema asigna automáticamente:

| Campo   | Valor |
|---------|--------|
| `nivel` | Nivel activo en sesión (`schoolCtx()->idNivel`) |
| `pwrd`  | `1234` en **texto plano** |

**Motivo:** alineado con el esquema legacy de `profesores.pwrd` y con la operativa del colegio (secretaría informa la clave inicial al docente; el usuario ingresa con DNI + `1234`).

**Implementación:** `App\Livewire\Abm\LegajosProfesor\LegajoProfesorForm::save()` — los campos `nivel` y `pwrd` están en `$guarded` del modelo `Profesor`, por lo que se asignan con asignación directa y un segundo `save()` tras el `create()`.

**Login:** `ProfesorUserProvider` compara con `hash_equals()`.

**Edición:** al modificar un legajo existente **no** se altera `pwrd`; solo se establece en el alta.

**Legajos docentes (orden 11):** con `LEGAJOS_DOCENTES` (`puedeModificarLegajosDocentes()`) se pueden crear, editar y eliminar legajos con todos los campos, e imprimir/exportar columnas completas en listados. Sin ese permiso, la consulta (`puedeConsultarLegajosDocentes()`) y los listados PDF/Excel quedan limitados a apellido, nombre y DNI en solo lectura.

---

## 3. Ciclo Lectivo — Comportamiento en Sesión

```
┌──────────┐   selecciona    ┌──────────────────────┐
│  Login   │───────────────►│  Sesión: idTerlec     │
│          │  nivel+terlec   │  (persiste toda la    │
└──────────┘                 │   navegación)         │
                             └──────────────────────┘
                                       │
                                       ▼
                             ┌──────────────────────┐
                             │  Toda consulta se     │
                             │  filtra por idTerlec   │
                             │  + idNivel de sesión   │
                             └──────────────────────┘
```

- En el login, el usuario selecciona el ciclo lectivo (por defecto: el actual).
- Toda la navegación y operación queda **acotada a ese ciclo lectivo**.
- Para cambiar de ciclo lectivo: desde el login o desde un control
  visible en el dashboard/página de inicio.
- El ciclo lectivo activo debe **persistir en sesión** durante toda la navegación.

**Implementación actual:** `App\Support\SchoolContext`
- Almacena `idProfesor`, `idNivel`, `idTerlec` en la sesión.
- Helper global `schoolCtx()` retorna la instancia.

---

## 4. Modelo de Permisos (portal de gestión / secretaría)

### Tablas involucradas

- `permisos_ia` — catálogo de permisos del sistema nuevo (`id`, `orden`, `tema`, `descripcion`).
- `profesores.permisos_ia` — cadena de `0` y `1` (un carácter por cada `orden` del catálogo).
- `profesores.permisos` + `permisosusuarios` — legado; **no** usar en módulos nuevos.

Catálogo de referencia en código: `App\Support\PermisosIaCatalog`.
SQL de sincronización: `database/sql/permisos_ia_catalogo_completo.sql`.

### Mecánica

Cada posición de la cadena en `profesores.permisos_ia` corresponde al campo `orden`
de un registro en `permisos_ia`:

```
permisos_ia = "111111111111111..."
                 │││
                 ││└─ orden=2 → tiene permiso
                 │└── orden=1 → tiene permiso
                 └─── orden=0 → tiene permiso
```

- `'1'` en posición N = tiene permiso del ítem con `orden = N`
- `'0'` en posición N = sin permiso

### Verificación obligatoria

- Helper global: `tienePermiso(int $orden)` (lee `profesores.permisos_ia`).
- Configuración granular (órdenes 25–36): `tienePermisoConfig($orden)` → alias de `tienePermiso`.
- Seguimiento disciplinario: orden **37** (`PermisosIaCatalog::SEGUIMIENTO_DISCIPLINARIO`).
- Gestión de inasistencias del estudiante: orden **38** (`PermisosIaCatalog::INASISTENCIAS_ESTUDIANTES_GESTION`).
- Gestión de TEA por inasistencias: orden **85** (`PermisosIaCatalog::TEA_ESTUDIANTES_GESTION`).
- Recalcular promedios (secundario): orden **94** (`PermisosIaCatalog::CALIF_RECALCULO_PROMEDIOS`).
- Legajos docentes (ABM + listado PDF/Excel): orden **11** (`PermisosIaCatalog::LEGAJOS_DOCENTES`, `puedeModificarLegajosDocentes()`). Con permiso: alta/edición/baja y datos completos. Sin permiso: solo consulta e impresión de apellido, nombre y DNI (`puedeConsultarLegajosDocentes()`).
- Rutas: middleware `permiso:N` o `permiso-config:N`.
- Livewire / controladores: `abort_unless(tienePermiso(N), 403)` en `mount()` y acciones sensibles.
- Menú: `@if (tienePermiso(N))` por ítem o grupo.

### Ejemplo

```php
abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_ESTUDIANTES), 403);
```
