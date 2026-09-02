# Menús de navegación (terminología oficial)

Este documento fija los **nombres** que usamos en el equipo para los portales con sidebar.
Evita confusiones entre “docentes” como usuarios de `profesores`, el grupo **DOCENTES** del menú de secretaría
y el **Menú de Docentes** para profesores en el aula.

---

## Resumen

| Nombre oficial | Qué es | Layout Blade | Login / guard |
|----------------|--------|--------------|---------------|
| **Menú de Secretaría** | Gestión pedagógica (Inicial / Primario / Secundario): calificaciones, exámenes, legajos del nivel, etc. | `resources/views/layouts/app.blade.php` | `/loginUsuario` · `menu.portal:staff` + pedagógico `menu.portal:secretaria` |
| **Menú de Administración** | Aranceles, becas, mora y módulos financieros; legajos/comunicación/config transversales | `resources/views/layouts/administracion.blade.php` | Mismo login · `school.idNivel = 5` · `menu.portal:administracion` en `/cuotas` y `/mora` |
| **Menú de Alumnos** | Autogestión familia / estudiante | `resources/views/layouts/alumno.blade.php` | `/loginEstudiante` · guard `alumno` · tabla `legajos` |
| **Menú de Docentes** | Portal reducido: pocas tareas para el profesor en el aula | `resources/views/layouts/docente.blade.php` | Mismo login que secretaría (`profesores`); rutas bajo prefijo `/portal-docente` |

**Cantidad de sidebars implementados:** 4 layouts. Secretaría y Administración comparten módulos vía `menu.portal:staff` (legajos, comunicación, configuración); las rutas sensibles de cuotas/mora solo admiten Administración.

---

## 1. Menú de Secretaría

- **Audiencia:** secretaría, preceptores, directivos y personal con sesión en niveles pedagógicos 1–4 (`profesores.nivel` distinto de 5).
- **Antes se decía:** “gestión”, “staff”, “layout app”, “sistema grande”.
- **Rutas:** prefijo raíz (`/dashboard`, `/abm/…`, `/calificacionesSecundario/…`, etc.) con middleware `auth` + `school.context`.
- **Contexto de sesión:** `schoolCtx()` (nivel + ciclo lectivo elegidos en el login o en el context-switcher del sidebar).
- **Sidebar:** ~13 grupos desplegables + enlace “Manual del sistema”. Detalle de grupos: ver historial de `layouts/app.blade.php` o el manual PDF.

**Importante:** el grupo del sidebar llamado **“DOCENTES”** (legajos del docente, asignación por materia, inasistencias docentes desde secretaría) **pertenece al menú de Secretaría**, no al menú de Docentes.

### Grupos CALIFICACIONES por nivel (`schoolCtx()->idNivel`)

Cada nivel pedagógico tiene **su propio** grupo desplegable en el sidebar (solo uno visible según el nivel elegido en login / context-switcher):

| Grupo en sidebar | `niveles.id` | Ítems actuales |
|------------------|--------------|----------------|
| **CALIFICACIONES (Inicial)** | 1 | Descargar Calificaciones desde GE (`calificacionesInicial.sincroGe`, permiso 9) · Descargar Desempeños desde GE (`calificacionesInicial.sincroDesempenos`, permiso 9) · Editar indicadores (`calificacionesInicial.indicadores`, permiso 71) · Carga de observaciones (`calificacionesInicial.observaciones`, permiso 71) · Informe de progreso escolar (`calificacionesInicial.informeProgreso`, permiso 71) |
| **CALIFICACIONES (Primario)** | 2 | Descargar Calificaciones desde GE (`calificacionesPrimario.sincroGe`, permiso 9) · Descargar Desempeños desde GE (`calificacionesPrimario.sincroDesempenos`, permiso 9) · Carga de calificaciones por estudiante (`calificacionesPrimario.carga`, permiso 71) · Carga de calificaciones por materia (`calificacionesPrimario.cargaMateria`, permiso 71) · IPE (Informe de Progreso Escolar) (`calificacionesPrimario.boletinIpe`, permiso 71) · Planilla de calificaciones (`calificacionesPrimario.planilla`, permiso 71) |
| **CALIFICACIONES (Secundario)** | 3 | Descargar calificaciones desde CIDI (`calificacionesSecundario.sincroGe`, permiso 9) · Carga de calificaciones (`calificacionesSecundario.carga`, permiso 71) · Recalcular promedios (`calificacionesSecundario.recalculoPromedios`, permiso 94; no EPQ; encima de Gestión de solicitudes de evaluación) · resto de módulos (`consulta`, `planilla`, `boletinesSecundario.*`, etc.) |

Implementación: `MenuSecretariaPerfil::muestraCalificaciones*()`, partials en `resources/views/layouts/partials/sidebar-grupo-calificaciones-*.blade.php`, constantes `NivelSistema::INICIAL|PRIMARIO|SECUNDARIO`.

---

## 2. Menú de Alumnos

- **Audiencia:** familia / estudiante (`legajos`).
- **Antes se decía:** “autogestión”, “portal alumno”, “familia”, `layouts/alumno`.
- **Rutas:** prefijo `/alumnos/…` · middleware `auth:alumno` + `student.context`.
- **Contexto:** `studentCtx()`; ciclo desde `ento.idTerlecVerNotas`.
- **Enlaces típicos:** consulta de calificaciones, informe de inasistencias, cuaderno de comunicados, push; aranceles externos si el tenant lo configura; C.U.S. e I.S.A. si el tenant los habilita (`autogestion.cus` / `autogestion.isa`); informes pedagógicos inicial SFQ si el tenant los habilita (`autogestion.boletin_inicial_sfq`).

Orientación UI: **mobile-first** (ver [01-descripcion-general.md](01-descripcion-general.md)).

---

## 3. Menú de Docentes

- **Audiencia:** profesores que solo necesitan **pocas acciones** (carga/consulta acotada, comunicados propios, etc.) sin el menú completo de secretaría.
- **Estado:** layout operativo; ítems pedagógicos según config del tenant y nivel de sesión; comunicación institucional siempre visible.
- **Layout:** `resources/views/layouts/docente.blade.php`.
- **Rutas (convención):** prefijo URL `/portal-docente` · nombres de ruta `portalDocente.*`  
  (no usar solo `/docentes` porque ya existe el módulo de secretaría `docentes.inasistencias.*`).

**Login:** mismo que Secretaría (`/loginUsuario`, tabla `profesores`). Tras autenticarse:

| `profesores.IdTipoProf` | Destino |
|-------------------------|---------|
| **6** (rol «Profesor/a» en `profesortipo`) | `portalDocente.home` — Menú de Docentes |
| Cualquier otro (Directivo, Secretario, Preceptor, Administrador, Gabinete de orientación, etc.) | `dashboard` — Menú de Secretaría |

Implementación: `App\Support\ProfesorMenuPortal` y middleware `menu.portal:secretaria` / `menu.portal:docente`.
Un profesor (`IdTipoProf = 6`) no puede abrir rutas de secretaría (redirección al portal); el resto no puede abrir `/portal-docente`.

**Pantalla inicial placeholder:** `portalDocente.home` → vista `resources/views/portal-docente/home.blade.php`. El escritorio incluye el widget de **calendario escolar** (próximas actividades extracurriculares aprobadas).

### Sidebar dinámico (tenant × nivel × implementación)

El sidebar no lista ítems fijos por colegio. Se resuelve en runtime con:

| Capa | Responsable | Qué define |
|------|-------------|------------|
| Catálogo | `App\Support\Navegacion\PortalDocenteMenuCatalog` | Ítems posibles, nivel (`niveles.id`), ruta `portalDocente.*`, icono |
| Visibilidad menú | `config('tenant.portal_docente.menu.{nivel}.{item}')` | Si **este colegio** muestra el ítem al docente (sin `permiso_ia`) |
| Implementación módulo | `config('tenant.calificaciones_primario.{modulo}.implementacion')` | Qué **variante de código** corre (`montecristo`, futuras claves) |
| Registro variantes | `App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos` | Mapa `implementacion` → Livewire, rutas portal/staff |
| Render sidebar | `PortalDocenteMenu::itemsParaSesionActual()` + partial `sidebar-portal-docente-item` | Filtra por nivel de sesión, flags de menú e implementación activa |

**Comunicación institucional** (bandeja, nuevo comunicado, push) sigue fija en el layout; no pasa por el catálogo.

**Reglas:**

1. Un ítem de calificaciones primario en el menú docente exige **menú = true** e **implementacion ≠ null** registrada en código.
2. La clave `implementacion` (p. ej. `montecristo`) nombra la variante en código, **no** el tenant: otro colegio puede reutilizarla en su `config/tenants/{slug}.php`.
3. En portal docente, calificaciones primario filtran cursos/materias por asignación `ppc` (`CalificacionesPrimarioPortalDocente`).
4. En Menú de Secretaría, los mismos módulos primario usan `calificacionesPrimario.*` + permisos; la visibilidad de carga/planilla también exige `CalificacionesPrimarioModulos::moduloActivo()`.
5. En portal docente **no** se comprueba `permisos_ia` para entrar ni para guardar (Livewire/PDF). El alcance es `menu.portal:docente`, config del tenant y `ppc`. En componentes compartidos staff/portal usar `PortalDocenteContext::abortSiStaffSinPermisoIa()`.

Detalle de config y matriz de variantes: [07-versionado-de-modulos-por-tenant.md](07-versionado-de-modulos-por-tenant.md) §3.4.

### Autogestión Docente (rol mixto: Preceptor + Profesor)

Algunos usuarios tienen **dos roles** en el sistema (p. ej. Preceptor y Profesor/a). Como `profesores.IdTipoProf` es único, el login carga el rol "mayor" (Preceptor) y lleva al Menú de Secretaría; quedan sin acceso a sus materias del Menú de Docentes.

Para cubrir ese caso, el Menú de Secretaría incluye un ítem **"Autogestión Docente"** al final del sidebar, **antes** del "Manual del sistema". Se muestra **solo cuando** el usuario actual tiene `IdTipoProf ≠ 6` **y** existe al menos un registro en `ppc` para algún legajo con el **mismo DNI** en el **nivel y ciclo lectivo activos** (`schoolCtx()->idNivel`, `schoolCtx()->idTerlec`).

Al activarse (POST `autogestion.docente.activar` → `AutogestionDocenteController`):

1. Se busca el legajo en `profesores` con el mismo DNI y nivel del contexto que tenga PPC para el ciclo (prioridad: `IdTipoProf = 6`).
2. Si ese legajo es **distinto** al usuario autenticado, se cambia la identidad de Auth (`Auth::loginUsingId`) y se reescribe `schoolCtx()->idProfesor` para que el Menú de Docentes encuentre sus materias y permisos.
3. Se setea el override de sesión `school.menu_portal_override = 'docente'` (constantes en `ProfesorMenuPortal::SESSION_OVERRIDE_KEY` / `OVERRIDE_DOCENTE`).
4. Se redirige a `portalDocente.home`.

Con el override activo, `ProfesorMenuPortal::usaMenuDocentes()` devuelve `true` aunque `IdTipoProf ≠ 6`; el middleware `menu.portal:secretaria` redirige a `portalDocente.home` y el de `menu.portal:docente` deja pasar. **Para volver al Menú de Secretaría hay que cerrar sesión y reingresar** (el logout invalida la sesión y limpia el override).

---

## Glosario — qué decir y qué evitar

| Decir | Evitar (ambiguo) |
|-------|------------------|
| Menú de Secretaría | “menú app”, “sidebar staff”, “gestión” a secas |
| Menú de Alumnos | “menú alumno/familia”, “autogestión” sin aclarar portal |
| Menú de Docentes | “menú profesor” mezclado con grupo DOCENTES de secretaría |
| Grupo **DOCENTES** (secretaría) | “menú docentes” — es solo una sección del menú de Secretaría |

En código y PRs, preferir comentarios del tipo:

```blade
{{-- Menú de Secretaría: grupo CALIFICACIONES (Secundario) --}}
```

```blade
{{-- Menú de Docentes: ítem pendiente de definir --}}
```

---

## Referencias en el repo

| Tema | Archivo |
|------|---------|
| Logins y permisos | [03-autenticacion-y-permisos.md](03-autenticacion-y-permisos.md) |
| Tooltips y grupos del sidebar de Secretaría | [05-preferencias-y-convenciones.md](05-preferencias-y-convenciones.md) §6 |
| Identidad visual (sidebar) | [04-identidad-visual.md](04-identidad-visual.md) · regla `.cursor/rules/ui-front-se.mdc` |
| Rutas portal docente | `routes/web.php` (bloque `portal-docente`) |
| Menú docente (catálogo/resolver) | `App\Support\Navegacion\PortalDocenteMenuCatalog`, `PortalDocenteMenu` |
| Calificaciones primario (variantes) | `App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos` |
| Config tenant | `config/tenant.php`, `config/tenants/{slug}.php` |

---

## 4. Menú de Administración (`niveles.id = 5`)

Usuario distinto en tabla `profesores` (`profesores.nivel = 5`) respecto de Inicial, Primario o Secundario. **Layout propio:** `layouts/administracion.blade.php` (sidebar en `layouts/partials/sidebar-nav-administracion.blade.php`).

- **Post-login:** mismo `dashboard` que staff; el layout lo elige `ProfesorMenuPortal::layoutStaff()`.
- **Rutas exclusivas:** prefijos `/cuotas` y `/mora` con middleware `menu.portal:administracion` + `administracion.nivel`.
- **Rutas compartidas con secretaría:** legajos, listados, comunicación, configuración, etc. bajo `menu.portal:staff` (accesibles desde ambos menús, con el sidebar que corresponda).

| Bloque visible (según permisos) | Oculto en este perfil |
|--------------------------------|------------------------|
| Estudiantes | Calificaciones, asistencia, exámenes, certificados, horarios, aspirantes, matrícula web, viajes/salidas |
| Comunicación institucional | |
| **Gestión de cuotas** (solo nivel 5) | |
| **Becas** (solo nivel 5; p. ej. Tipos de Beca) | |
| **DOCENTES / USUARIOS** (mismo bloque que Secretaría: legajos docente, ppc, inasistencias docentes, certificación de servicios, capacitación, libro de temas si el tenant lo activa; según permisos 11, 48, 23, 87, 93 y **101**) | |
| Configuración (incl. Permisos del sistema; sin planes/curplan ni cursos/materias del año) | **Gestión de planes y cursos modelo**; **Gestión de cursos y materias del año** |

**Legajos:** consulta y listados para **todos** los usuarios del menú. En sesión Administración se ven alumnos de **Inicial, Primario y Secundario** del ciclo activo (sin selector de nivel en el sidebar).

| Sesión | Modificar legajos / matrículas |
|--------|------------------------------|
| Niveles 1–4 | Permiso orden **2** (solo ese nivel) |
| Administración (5) | Permiso orden **47** — cualquier alumno de cualquier nivel pedagógico; el nivel de la matrícula se toma del curso elegido |

En cada colegio se activa o no el orden **47** en el usuario de Administración según si ese tenant permite que administración modifique legajos. Sin el 47: solo consulta (todos los niveles).

**Gestión de aranceles / masiva / resúmenes / becas / mora:** cada ítem del sidebar tiene su propio orden en `permisos_ia` (49–64 y **98** estado de deuda por estudiante). El grupo del menú solo se muestra si el usuario tiene al menos un ítem habilitado de ese bloque; no hay un permiso único por grupo.

**Viajes / salidas educativas (Excel):** solo en el **Menú de Secretaría** (`layouts/app`) para usuarios con portal secretaría en nivel pedagógico (Inicial, Primario o Secundario). No en Administración, Menú de Docentes ni Menú de Alumnos (`MenuSecretariaPerfil::muestraViajesSalidasEducativas()` + rutas `menu.portal:secretaria`).

Implementación: `App\Support\NivelSistema`, `App\Support\SchoolAlcancePedagogico`, `App\Support\Navegacion\MenuSecretariaPerfil`, `App\Support\ProfesorMenuPortal`, `App\Http\Middleware\EnsureMenuPortal`.

---

## Historial

- **2026-05-22:** Definición de los tres nombres oficiales; scaffold del menú de Docentes (`layouts/docente.blade.php`, `portalDocente.home`).
- **2026-05-22:** Redirección post-login y separación de rutas por `IdTipoProf` (6 → Docentes; demás → Secretaría).
- **2026-05-23:** Menú de Docentes: sección Comunicación institucional (`portalDocente.comunicaciones.*`, mismos componentes que secretaría).
- **2026-05-26:** "Autogestión Docente" en el Menú de Secretaría (antes del Manual) para usuarios con rol mixto (Preceptor + Profesor): override de sesión `school.menu_portal_override` y cambio de identidad si hay legajo paralelo con PPC. Logout para volver a Secretaría.
- **2026-06-01:** Perfil Administración (nivel 5): menú acotado, selector de nivel de trabajo, bloque Gestión de cuotas (acceso por nivel, sin permiso_ia); consulta de legajos para todos, edición con orden 2.
- **2026-06-01:** Viajes / salidas educativas: solo Menú de Secretaría en niveles 1–4; no Administración, Docentes ni Alumnos.
- **2026-06-04:** Menú de Administración como layout y portal propios; rutas `/cuotas` y `/mora` aisladas con `menu.portal:administracion`.
- **2026-06-04:** Tres grupos CALIFICACIONES por nivel (Inicial / Primario / Secundario) en el Menú de Secretaría; el bloque secundario renombrado y acotado a `niveles.id = 3`.
- **2026-07-23:** Certificación de servicios en grupo DOCENTES / USUARIOS (permiso IA orden 87): carga de `certificacion`/`licencias` e impresión PDF.
- **2026-07-31:** Menú de Docentes: el ítem `listado_estudiantes` pasa de «Listados por curso» a **Listados de Estudiantes con Formato** (`portalDocente.listados.estudiantesFormato`, mismo Livewire/PDF que secretaría).
- **2026-08-18:** Comunicación institucional: **Mis grupos** de destinatarios por usuario y nivel (`com_grupos` / `com_grupos_miembros`; rutas `comunicaciones.grupos` y `portalDocente.comunicaciones.grupos`).
- **2026-08-25:** Menú de Docentes: dos listados de estudiantes (mismo alcance de cursos del nivel de sesión que secretaría pedagógica): **Listados de Estudiantes por Curso** (`listado_estudiantes` → `portalDocente.listados.porCurso` + PDF/Excel) y **Listados de Estudiantes con Formato** (`listado_estudiantes_formato` → `portalDocente.listados.estudiantesFormato`).
- **2026-08-27:** CERTIFICADOS: **Certificado Jardín** (inicial, sala de 5) y **Certificado Sexto Grado** (primario); permiso IA orden 97.
- **2026-08-27:** Administración → Gestión de mora: **Estado de Deuda por Estudiante** (permiso IA orden 98), además de Estado de Deuda Familiar (63).
- **2026-08-31:** Menú de Docentes (Caixal SF, nivel inicial): Carga de observaciones, Carga por Espacio Curricular e Informe de progreso escolar (`portal_docente.menu.inicial.*` en `config/tenants/caixalsf.php`).
- **2026-09-02:** Menú de Secretaría / Administración → Estudiantes: **Listado de familias** (`listados.familias` + PDF/Excel; permiso IA orden **102**, grupo LEGAJOS ESTUDIANTES). Familias con alumnos del ciclo activo en **todos** los niveles pedagógicos (no se recorta al nivel de sesión). La edición en grilla sigue pidiendo orden 46.
- **2026-09-02:** Menú de Secretaría / Administración → DOCENTES / USUARIOS: **Libro de temas** (permiso IA 101, tabla `librodetemas`). Menú de Docentes por tenant (`modulos.libro_de_temas` + `portal_docente.menu.*.libro_de_temas`; iess).
- **2026-09-02:** Menú de Docentes (secundario): **Cuaderno de seguimiento áulico** también en **alfonsina** y **nocturna** (`portal_docente.menu.secundario.cuaderno_seguimiento_aulico`; ya estaba en iess).
