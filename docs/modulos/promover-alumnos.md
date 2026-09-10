# Módulo: Promover a todos los alumnos

## Propósito

Crear en el **año de destino** la **matrícula** y las filas de **calificaciones** de los alumnos regulares de los cursos marcados en el año de origen.

Equivale al proceso ScriptCase de pasaje de alumnos (el que confirmaba *PROMOVER A LOS ALUMNOS*). No copia cursos ni materias: el destino debe existir (típicamente después de [Copiar cursos, materias y horarios](copiar-cursos-materias-anio.md)).

## Modalidades / variantes

- Último año de secundario que **no** se promociona: `config('tenant.promocion.ultimo_curso_secundario')` (default **6**; **EPQ = 5**).
- Último año de Adultos (`niveles.id` = 6): `ultimo_curso_adultos` (default **3**). Esos cursos tampoco se listan.

## Actores y permisos

- Menú de Administración y Menú de Secretaría → **Configuración**, después de Copiar asignación de profesores y preceptores.
- Permiso `permisos_ia` orden **106** (`PermisosIaCatalog::PROMOVER_ALUMNOS_ANIO`).
- Descripción del catálogo con aviso **NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR**.
- No hay ítem en Menú de Docentes ni Menú de Alumnos.

## Tablas y campos críticos

| Tabla | Qué crea | Empareja destino por | Unicidad (omite si ya existe) |
|-------|----------|----------------------|-------------------------------|
| `matricula` | `idTerlec` destino, nivel/curso nuevos, mismo `idLegajos` e `idCondiciones`; `idCuotasbecas` = 1 | `cursos.c` + `cursos.s` + nivel de destino + año destino | cualquier matrícula del legajo en el año destino |
| `calificaciones` | una fila por materia del **curso destino** (`ord`, `idMaterias`, `idMatPlan`) | materias de `idCursos` destino, orden `ord` | no se inserta si el alumno ya tenía matrícula en destino |

Solo se procesan regulares (`matricula.idCondiciones = 1`).

## Flujo principal

1. Elegir año de origen, año de destino (distintos) y niveles (todos, uno o varios).
2. Se listan los cursos/secciones del origen **excepto** el último año de secundario (y el de Adultos).
3. Marcar los cursos a promover. La grilla muestra regulares, curso destino y cuántos se crearían.
4. Confirmar (SweetAlert). En transacción: inserta matrículas y calificaciones que falten; no pisa lo existente.
5. Informe: creados, ya existían, sin curso destino, y totales por nivel de origen.

## Pases de nivel (mismo criterio que ScriptCase)

| Origen | Destino |
|--------|---------|
| Inicial sala 5 | 1.º grado Primario |
| 6.º grado Primario | 1.er año Secundario |
| Último año de Secundario | no se promociona |
| Último año de Adultos | no se promociona |
| Resto | mismo nivel, `cursos.c` + 1, misma sección |

Si no existe en el destino el curso con la misma sección, ese alumno se omite (`sin_curso`: el plan cambió o faltó copiar la estructura).

## Fuente de verdad

- Matrícula del ciclo: tabla `matricula`.
- Filas de notas del ciclo: tabla `calificaciones` (mismas que al dar de alta una matrícula en el legajo).

## Archivos clave

- `app/Support/Configuracion/PromoverAlumnosAnio.php`
- `app/Livewire/Administracion/Configuracion/PromoverAlumnosIndex.php`
- `resources/views/livewire/administracion/configuracion/promover-alumnos-index.blade.php`
- Ruta `config.promover-alumnos`
- Permiso: `database/sql/permiso_ia_orden_106_promover_alumnos.sql`
- EPQ (5 años de medio): `config/tenants/epq.php` → `promocion.ultimo_curso_secundario`

## Qué no hacer / reglas de negocio

- No promover si origen = destino.
- No incluir el nivel Administración (5).
- No listar ni procesar el último año de secundario (egresados).
- No duplicar una matrícula que ya está en el año destino (aunque sea de otro curso).
- No crear cursos ni materias: si falta el par en destino, se omite.
- No calcular ni copiar notas: solo filas vacías según las materias del curso destino.
- Si falta una columna con valor al insertar, error visible y rollback (sin falso éxito).

## Checklist al modificar

- [ ] Permiso 106 en catálogo, `ordenesReservadosAdministrador()`, ruta, Livewire y ambos sidebars.
- [ ] Confirmación con `seSwalConfirmar`; sin `wire:confirm`.
- [ ] Inserts con `PersistenciaColumnas` (sin falso éxito si falta columna con valor).
- [ ] EPQ: quinto año de medio no se lista (`ultimo_curso_secundario` = 5).
- [ ] Solo regulares (`idCondiciones` = 1).
