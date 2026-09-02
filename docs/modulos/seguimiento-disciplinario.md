# Módulo: Seguimiento disciplinario

## Propósito

Registrar, editar e imprimir sanciones del Cuaderno de Seguimiento; notificar a la familia; consultar antecedentes. El **acta** (`sanciones.acta`) es un texto enriquecido opcional asociado a cada sanción.

## Modalidades / variantes

- Listado por curso/alumno del contexto (`idNivel` / `idTerlec`).
- Comunicado PDF (DomPDF legacy) con dos bloques en la misma hoja; si hay acta, se agrega en **hoja aparte**.
- El recuadro «Hasta la fecha registra un total de» lista solo los tipos con `sanciontipo.enResumenComunicado = 1` (cada colegio marca los propios en Parametrización → Tipos de sanción). Esos mismos tipos muestran el botón **Comunicado** en el listado; con valor 0 el botón no aparece y el PDF no se emite.
- Troquel 1 (solicitud): totales **sin** la sanción que se imprime. Troquel 2 (notificación): totales **con** esa sanción. En ambos, solo sanciones con `fecha` **menor o igual** a la de la que se informa (reimprimir un comunicado viejo no incluye las posteriores). Etiqueta del tipo: singular si la cantidad es 1 (`1 Amonestación`), plural si es 0 o 2+ (`3 Amonestaciones`).
- Notificación a padres (push y, si el tipo lo pide, email): el cuerpo actual se mantiene; el texto plano del acta se **anexa al final** solo si hay contenido.

### Menú de Docentes — Cuaderno de seguimiento áulico (secundario)

Registro de **situación áulica** desde el portal docente (materias `ppc` del profesor). Default **off**. Activo en **iess**, **alfonsina** y **nocturna** (`config/tenants/{slug}.php` → `portal_docente.menu.secundario.cuaderno_seguimiento_aulico`). El alta usa el tipo `sanciontipo.tipo` = `Registro de Situación Áulica` (debe existir en la BD del tenant; `enResumenComunicado = 0`) y avisa al preceptor del curso.

## Actores y permisos

- Menú de Secretaría / Administración (staff): permiso orden **37** (`permiso:37` / `SEGUIMIENTO_DISCIPLINARIO`).
- Tipos de sanción y remitente de notificación: parametrización aparte (`SANCION_TIPOS_CONFIG`).
- Menú de Docentes / Autogestión Docente: flag de tenant, sin `permisos_ia`. Solo secundario y materias asignadas en `ppc`.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `sanciones` | `idMatricula`, `idTipoSancion`, **`idProfesores`**, `fecha`, `cantidad`, `motivo`, **`acta`**, `solipor`, `comunicadaPadres`, … | `idProfesores` = quién registró (`profesores.id`; 0 si falta). `acta` = `MEDIUMTEXT` NULL (HTML sanitizado). Sin texto = comportamiento anterior. |
| `sanciontipo` | `tipo`, `textoNotifPadres`, `idProfesorNotif`, `refuerzoMail`, `permiteNotifPadres`, **`enResumenComunicado`** | Notif. Padres; `enResumenComunicado` 1 = botón «Comunicado» + entra en el resumen del PDF; 0 = ni botón ni PDF. |
| `matricula` / `legajos` / `cursos` | Alcance por contexto | Selector de alumno: solo `idCondiciones` 1, 2, 3 o 4. Seguridad de listado, PDF y acta. |

Migración acta: `database/migrations/2026_08_10_120000_add_acta_to_sanciones.php`.  
SQL acta: `database/sql/sanciones_acta_idempotente.sql`.  
Migración `idProfesores`: `database/migrations/2026_09_02_180000_add_id_profesores_to_sanciones_if_missing.php`.  
SQL `idProfesores`: `database/sql/sanciones_idprofesores_idempotente.sql`.  
Migración resumen PDF: `database/migrations/2026_09_02_170000_add_en_resumen_comunicado_to_sanciontipo.php`.  
SQL resumen PDF: `database/sql/sanciontipo_en_resumen_comunicado_idempotente.sql`.

## Flujo principal

1. Elegir curso y alumno → listado de sanciones.
2. Nueva / editar sanción (formulario de campos básicos).
3. Botón **Acta** → editor enriquecido (`x-se-html-editor`); guardar con `PersistenciaColumnas`.
4. Botón **Comunicado** (solo si `enResumenComunicado = 1`) → PDF; si `acta` no está vacío, tercera sección en página nueva.
5. **Notif. Padres** → `NotificarFamiliaSancion::despachar`; si hay acta, se agrega bloque «Acta:» + texto plano al final del contenido.

## Fuente de verdad

- Datos de sanción: fila en `sanciones`.
- HTML del acta: columna `acta` (sanitizado con `SancionActaHtmlSanitizer`, mismo criterio de tags que viajes educativos).
- Tipos que entran al resumen del comunicado: `sanciontipo.enResumenComunicado`. Totales: `ResumenComunicadoSancion::lineas($idMatricula, $excluirId?, $hastaFecha)` (excluir id en troquel 1; `$hastaFecha` = fecha de la sanción impresa, inclusive). Etiquetas: `etiquetaSegunCantidad()`. No hardcodear nombres ni usar forma con barra (`Firma/s`).

## Archivos clave

- `app/Livewire/Seguimiento/Disciplinario/DisciplinarioIndex.php`
- `app/Livewire/Seguimiento/Disciplinario/SancionForm.php`
- `app/Livewire/Seguimiento/Disciplinario/SancionActaForm.php`
- `app/Http/Controllers/SancionComunicadoPdfController.php`
- `app/Support/Seguimiento/ResumenComunicadoSancion.php`
- `resources/views/pdf/sancion-comunicado.blade.php`
- `app/Livewire/Parametrizacion/SancionTipoIndex.php`
- `app/Support/Seguimiento/NotificarFamiliaSancion.php`
- `app/Support/Seguimiento/SancionActaHtmlSanitizer.php`
- Portal docente: `app/Livewire/PortalDocente/CuadernoSeguimientoIndex.php`, `RegistroSituacionAulicaIndex.php`, `SituacionAulicaAlumnoShow.php`; `App\Support\PortalDocente\CuadernoSeguimientoAulicoDocente`; config `config/tenants/{iess,alfonsina,nocturna}.php`

## Qué no hacer / reglas de negocio

- No exigir acta: vacío = PDF y notificación como antes.
- No usar `{!! !!}` en el PDF sin pasar por `SancionActaHtmlSanitizer::paraPdf()`.
- No omitir en silencio el guardado de `acta` si el usuario cargó texto y falta la columna (usar `PersistenciaColumnas`).
- No migrar este PDF a TCPDF salvo pedido explícito (es DomPDF legacy).
- No listar en el resumen del comunicado todos los tipos ni filtrar por el nombre: solo `enResumenComunicado = 1`.
- No mostrar el botón «Comunicado» ni emitir el PDF si `enResumenComunicado = 0`.
- No usar forma con barra (`Firma/s`, `Amonestación/es`): singular si cantidad = 1, plural si 0 o ≥ 2.
- No sumar en el PDF sanciones con fecha posterior a la que se imprime.

## Checklist al modificar

- [ ] ¿Queries filtradas por `schoolCtx()` vía matrícula?
- [ ] ¿Selector de alumnos solo condiciones 1–4 (`ListadoCursoCondicionFiltro::TODOS`)?
- [ ] ¿Acta opcional en PDF y en mail?
- [ ] ¿HTML sanitizado al guardar y al imprimir?
- [ ] ¿Esquema `acta` aplicado en el tenant (migrate o SQL)?
- [ ] ¿Esquema `idProfesores` aplicado en el tenant (migrate o SQL)? Sin esa columna el alta de sanción falla.
- [ ] ¿Esquema `enResumenComunicado` aplicado y tipos marcados en Parametrización?
- [ ] ¿Botón «Comunicado» solo si `enResumenComunicado = 1` (y PDF 404 si no)?
- [ ] ¿Resumen del comunicado cortado por `fecha <=` la de la sanción impresa?
- [ ] ¿Portal docente: flag `cuaderno_seguimiento_aulico` solo en tenants que lo usan, y tipo `Registro de Situación Áulica` en `sanciontipo`?
