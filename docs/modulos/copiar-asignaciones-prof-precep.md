# Módulo: Copiar asignación de profesores y preceptores

## Propósito

Copiar las **asignaciones de docentes por materia** (`ppc`) y las **asignaciones de preceptores por curso** (`preceptoresporcurso`) desde un año lectivo de origen hacia un año de destino, eligiendo uno, varios o todos los niveles pedagógicos.

Equivale al proceso ScriptCase de pasaje de asignaciones (el que confirmaba *pasar las ASIGNACIONES DE PROFESORES Y DE PRECEPTORES*). No crea cursos ni materias: el destino debe existir (típicamente después de [Copiar cursos, materias y horarios](copiar-cursos-materias-anio.md)).

## Modalidades / variantes

Ninguna. Mismo proceso en todos los tenants. El esquema de `preceptoresporcurso` puede variar (`idProfesores` / `idProfesor`, `idNivel` / `idNiveles`); el código detecta columnas.

## Actores y permisos

- Menú de Administración y Menú de Secretaría → **Configuración**, después de Copiar cursos, materias y horarios.
- Permiso `permisos_ia` orden **105** (`PermisosIaCatalog::COPIAR_ASIGNACIONES_PROF_PRECEP`).
- Descripción del catálogo con aviso **NO OTORGAR: RESERVADO PARA EL ADMINISTRADOR**.
- No hay ítem en Menú de Docentes ni Menú de Alumnos.

## Tablas y campos críticos

| Tabla | Qué copia | Empareja destino por | Unicidad (omite si ya existe) |
|-------|-----------|----------------------|-------------------------------|
| `ppc` | `idProfesor`, `idSituRevis` (si existe; `NULL` → 1) sobre la materia del año destino | `materias.idMatPlan` + `cursos.c` + `cursos.s` + `materias.idNivel` + año destino | `idMateria` (destino) + `idProfesor` |
| `preceptoresporcurso` | preceptor(es) del curso origen, con ciclo/nivel del destino | `cursos.c` + `cursos.s` + `cursos.idNivel` + año destino | `idCursos` (destino) + preceptor |

A diferencia de ScriptCase (que omitía **todo** el curso destino si ya tenía algún preceptor), acá se copian **todos** los preceptores del origen y solo se salta el par curso+preceptor que ya está.

## Flujo principal

1. Elegir año de origen, año de destino (distintos) y niveles.
2. La pantalla muestra una previsualización (origen / ya existen / a crear; y sin materia o sin curso destino).
3. Confirmar (SweetAlert). En transacción: inserta lo que falte; no pisa lo existente.
4. Informe: totales y cantidad creada por nivel (ppc y preceptores).

## Fuente de verdad

- Docente por materia: tabla `ppc` (misma que Asignación de profesores por curso).
- Preceptor por curso: tabla `preceptoresporcurso` (misma que Preceptores por curso).

## Archivos clave

- `app/Support/Configuracion/CopiarAsignacionesProfPrecepAnio.php`
- `app/Livewire/Administracion/Configuracion/CopiarAsignacionesProfPrecepIndex.php`
- `resources/views/livewire/administracion/configuracion/copiar-asignaciones-prof-precep-index.blade.php`
- Ruta `config.copiar-asignaciones-profesores-preceptores`
- Permiso: `database/sql/permiso_ia_orden_105_copiar_asignaciones_prof_precep.sql`

## Qué no hacer / reglas de negocio

- No copiar si origen = destino.
- No incluir el nivel Administración (5).
- No crear materias ni cursos: si no hay par en destino, la fila se cuenta como `sin_materia` / `sin_curso` y se omite.
- No duplicar una asignación que ya está en el destino.
- No tocar calificaciones, matrículas ni horarios.
- Si falta una columna con valor al insertar, error visible y rollback (sin falso éxito).

## Checklist al modificar

- [ ] Permiso 105 en catálogo, `ordenesReservadosAdministrador()`, ruta, Livewire y ambos sidebars.
- [ ] Confirmación con `seSwalConfirmar`; sin `wire:confirm`.
- [ ] Inserts con `PersistenciaColumnas` (sin falso éxito si falta columna con valor).
- [ ] Previsualización alineada con la ejecución (mismas claves de unicidad).
- [ ] Detección de columnas `idProfesores` / `idProfesor` y `idNivel` / `idNiveles` en preceptores.
