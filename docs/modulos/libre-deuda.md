# Módulo: Libre Deuda (portal familia)

## Propósito

Constancia PDF de que el estudiante **no registra deuda** en Áulica, desde el Menú de Alumnos. Replica el FPDF legacy de Montecristo (`CONSTANCIA DE LIBRE DEUDA`).

## Modalidades / variantes

Default off: `config/tenant.php` → `autogestion.libre_deuda`. Montecristo lo activa.

El ítem **solo aparece** si además hay credenciales Áulica (`tenantAulicaDeudaHabilitada()`).

| Clave | Efecto |
|-------|--------|
| `habilitado` | Muestra el ítem (sidebar y escritorio). |
| `lugar` | Ciudad del pie (`Monte Cristo, dd/mm/aaaa`). Vacío = `ento.localidad`. |
| `firma` / `sello` | Rutas relativas a `public/` (PNG/JPG). Si el archivo no existe, no se dibujan. |

## Actores y permisos

Guard `alumno`, `student.context`. Sin permiso IA. Datos de `studentCtx()` (sin IDs en la URL).

## Flujo principal

1. Familia abre **Libre Deuda** (formulario en el portal).
2. Pulsa **Consultar deuda**. Se llama a Áulica (DNI del estudiante y del responsable familiar).
3. Un modal muestra lo **enviado** (`TipoDoc` / `NroDoc`) y lo **recibido** (saldos por persona).
4. Si no hay deuda: el modal ofrece **Abrir constancia PDF**. Si hay deuda o la API falla: no se emite la constancia.

## Fuente de verdad

Deuda: `AulicaDeudaConsulta` (External API). Datos del certificado: matrícula del ciclo de autogestión + `studentPdfHeaderData()`.

## Archivos clave

- `app/Livewire/Alumnos/LibreDeudaForm.php`
- `app/Http/Controllers/Alumnos/LibreDeudaPdfController.php`
- `app/Support/Alumnos/LibreDeudaDatos.php`
- `app/Support/Alumnos/LibreDeudaTcpdf.php`
- `config/tenants/montecristo.php` → `autogestion.libre_deuda`
- Helper: `tenantAutogestionLibreDeudaHabilitada()`
- Cliente Áulica: [aulica-deuda-matricula.md](aulica-deuda-matricula.md)

## Qué no hacer / reglas de negocio

- No emitir la constancia si Áulica informa saldo > 0 (alumno o hermanos del tutor).
- No emitirla si Áulica no responde, o si el legajo no tiene DNI (fail-closed).
- No consultar Áulica en ficha de matrícula ni en actualización de datos.
- No poner IDs en la URL.
- PDF nuevo: TCPDF + Arial (`TcpdfFuenteArial`), no DomPDF.

## Checklist al modificar

- [ ] Flag del tenant + Áulica configurada.
- [ ] Rate-limit en el controlador.
- [ ] Fecha `d/m/Y`.
- [ ] Otro colegio no ve el ítem sin `habilitado => true`.
