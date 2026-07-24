# Descripción General del Sistema

## 1. Resumen

Sistema de información para la gestión pedagógica y de cuotas de escuelas
de nivel inicial, primario y secundario. Existe una versión en producción
con base de datos MySQL que se mantiene. Se construye una versión nueva
sobre esa misma base de datos.

El desarrollo es por etapas. La **Etapa 1 (núcleo)** tiene una parte ya
desarrollada. Se continúa desde ahí.

---

## 2. Stack Técnico

| Capa        | Tecnología                                                  |
|-------------|-------------------------------------------------------------|
| Backend     | PHP 8.2+ · Laravel 11 · Livewire 4                         |
| Frontend    | Blade + Tailwind CSS 4 · Vite 5                             |
| Base de datos | MySQL existente (legacy) — NO modificar tablas existentes |
| Servidor local | WAMP 64-bit                                              |
| Auth provider | Custom (`ProfesorUserProvider`) sobre tabla `profesores`  |

### Reglas de base de datos

- Crear **migraciones para instalación limpia** y **migraciones aditivas** a MySQL existente.
- **NO modificar tablas existentes** de la base legacy.
- Usar Eloquent con modelos que apunten a las tablas existentes (sin convenciones de timestamps/pluralización).

---

## 3. Interfaz de usuario

Tres **menús de navegación** (sidebar). Nomenclatura oficial: [08-menus-de-navegacion.md](08-menus-de-navegacion.md).

| Menú | Layout | Orientación típica |
|------|--------|-------------------|
| **Secretaría** (gestión completa) | `layouts/app.blade.php` | 80% desktop |
| **Alumnos** (familia / estudiante) | `layouts/alumno.blade.php` | 90% mobile |
| **Docentes** (portal reducido) | `layouts/docente.blade.php` | 90% mobile |

- Sidebar responsivo en los tres portales.
- Design system basado en la paleta de colores de `/SE/`.

---

## 4. Archivos de referencia en la raíz del proyecto

| Archivo / Carpeta  | Descripción                                                     |
|--------------------|-----------------------------------------------------------------|
| `schema.sql`       | Estructura completa de todas las tablas de la BD                |
| `bd_con_datos.sql` | Datos de planes, cursos, materias y 2 alumnos de ejemplo        |
| `/SE/`             | Logos numerados (1 al 5) + paleta de colores de la empresa      |

---

## 5. Estructura del proyecto Laravel

```
sistemaPrueba/
├── SE/                          # Identidad visual (logos + paleta)
├── schema.sql                   # Esquema completo de BD
├── bd_con_datos.sql             # Dump con datos de ejemplo
└── sistema/                     # Proyecto Laravel 11
    ├── app/
    │   ├── Auth/                # ProfesorUserProvider (auth custom)
    │   ├── Http/Middleware/     # EnsureSchoolContext
    │   ├── Livewire/
    │   │   ├── Auth/Login.php   # Login de gestión
    │   │   └── Abm/            # Módulos ABM (Terlec, Niveles, Legajos)
    │   ├── Models/              # Eloquent models (legacy tables)
    │   └── Support/             # SchoolContext, helpers
    ├── docs/                    # ← Documentación del proyecto (+ docs/modulos/ por módulo)
    ├── resources/views/
    │   ├── layouts/             # app (Secretaría), alumno, docente, guest
    │   └── livewire/            # Vistas Livewire
    └── routes/web.php           # Rutas (guest + auth + school.context)
```

### Varios colegios (tenants)

Mismo código en `sistema/`, **una BD por colegio**, identificador `TENANT_SLUG` en `.env` y overrides en `config/tenants/{slug}.php`. La diferenciación de módulos y pantallas se hace sobre todo con **permisos y parametrización en BD**; no con paquetes Composer opcionales. Detalle: [07-versionado-de-modulos-por-tenant.md](07-versionado-de-modulos-por-tenant.md).

---

## 6. Etapas de desarrollo

### Etapa 1 — Núcleo (en curso)

Módulos de parametrización y gestión básica de alumnos.
Ver [02-modelo-de-datos.md](02-modelo-de-datos.md) para detalle de tablas.

**Ya implementado:**
- Login de Secretaría (tabla `profesores`, auth custom; contraseñas en texto plano)
- SchoolContext (nivel + ciclo lectivo en sesión)
- Middleware `EnsureSchoolContext`
- ABM Ciclos Lectivos (`terlec`)
- ABM Niveles (`niveles`)
- ABM Legajos (`legajos`) con formulario de alta/edición
- Menú de Secretaría con sidebar responsivo; Menú de Alumnos; scaffold Menú de Docentes
- Modelos Eloquent: Calificacion, Condicion, Curso, Familia, Legajo, Matricula, Nivel, Profesor, ProfesorTipo, Terlec

**Pendiente en Etapa 1:**
- Menú de Docentes: ítems y redirección post-login por rol (en curso)
- ABM Planes, Cursos modelo, Materias modelo
- Gestión de matrícula y calificaciones
- Sistema de permisos completo
- Design system con paleta institucional
