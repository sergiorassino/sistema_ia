# Módulo: Seguimiento disciplinario

## Propósito

Registrar, editar e imprimir sanciones del Cuaderno de Seguimiento; notificar a la familia; consultar antecedentes. El **acta** (`sanciones.acta`) es un texto enriquecido opcional asociado a cada sanción.

## Modalidades / variantes

- Listado por curso/alumno del contexto (`idNivel` / `idTerlec`).
- Comunicado PDF (DomPDF legacy) con dos bloques en la misma hoja; si hay acta, se agrega en **hoja aparte**.
- Notificación a padres (push y, si el tipo lo pide, email): el cuerpo actual se mantiene; el texto plano del acta se **anexa al final** solo si hay contenido.

## Actores y permisos

- Menú de Secretaría / Administración (staff): permiso orden **37** (`permiso:37` / `SEGUIMIENTO_DISCIPLINARIO`).
- Tipos de sanción y remitente de notificación: parametrización aparte (`SANCION_TIPOS_CONFIG`).

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `sanciones` | `idMatricula`, `idTipoSancion`, `fecha`, `cantidad`, `motivo`, **`acta`**, `solipor`, `comunicadaPadres`, … | `acta` = `MEDIUMTEXT` NULL (HTML sanitizado). Sin texto = comportamiento anterior. |
| `sanciontipo` | `tipo`, `textoNotifPadres`, `idProfesorNotif`, `refuerzoMail`, `permiteNotifPadres` | Condicionan Notif. Padres. |
| `matricula` / `legajos` / `cursos` | Alcance por contexto | Selector de alumno: solo `idCondiciones` 1, 2, 3 o 4. Seguridad de listado, PDF y acta. |

Migración: `database/migrations/2026_08_10_120000_add_acta_to_sanciones.php`.  
SQL idempotente: `database/sql/sanciones_acta_idempotente.sql`.

## Flujo principal

1. Elegir curso y alumno → listado de sanciones.
2. Nueva / editar sanción (formulario de campos básicos).
3. Botón **Acta** → editor enriquecido (`x-se-html-editor`); guardar con `PersistenciaColumnas`.
4. Icono impresora → PDF comunicado; si `acta` no está vacío, tercera sección en página nueva.
5. **Notif. Padres** → `NotificarFamiliaSancion::despachar`; si hay acta, se agrega bloque «Acta:» + texto plano al final del contenido.

## Fuente de verdad

- Datos de sanción: fila en `sanciones`.
- HTML del acta: columna `acta` (sanitizado con `SancionActaHtmlSanitizer`, mismo criterio de tags que viajes educativos).
- No recalcular totales de apercibimientos/amonestaciones fuera del PDF (consulta sobre la matrícula al imprimir).

## Archivos clave

- `app/Livewire/Seguimiento/Disciplinario/DisciplinarioIndex.php`
- `app/Livewire/Seguimiento/Disciplinario/SancionForm.php`
- `app/Livewire/Seguimiento/Disciplinario/SancionActaForm.php`
- `app/Http/Controllers/SancionComunicadoPdfController.php`
- `resources/views/pdf/sancion-comunicado.blade.php`
- `app/Support/Seguimiento/NotificarFamiliaSancion.php`
- `app/Support/Seguimiento/SancionActaHtmlSanitizer.php`

## Qué no hacer / reglas de negocio

- No exigir acta: vacío = PDF y notificación como antes.
- No usar `{!! !!}` en el PDF sin pasar por `SancionActaHtmlSanitizer::paraPdf()`.
- No omitir en silencio el guardado de `acta` si el usuario cargó texto y falta la columna (usar `PersistenciaColumnas`).
- No migrar este PDF a TCPDF salvo pedido explícito (es DomPDF legacy).

## Checklist al modificar

- [ ] ¿Queries filtradas por `schoolCtx()` vía matrícula?
- [ ] ¿Selector de alumnos solo condiciones 1–4 (`ListadoCursoCondicionFiltro::TODOS`)?
- [ ] ¿Acta opcional en PDF y en mail?
- [ ] ¿HTML sanitizado al guardar y al imprimir?
- [ ] ¿Esquema `acta` aplicado en el tenant (migrate o SQL)?
