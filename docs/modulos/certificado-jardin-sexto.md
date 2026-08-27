# Módulo: Certificado Jardín / Certificado Sexto Grado

## Propósito

Impresión en PDF del certificado oficial de finalización de **sala de 5** (nivel inicial) y de **sexto grado** (nivel primario), con el formato provincial de Córdoba (layout legacy FPDF pasado a TCPDF).

## Modalidades / variantes

Un mismo flujo, dos entradas de menú según el nivel de sesión:

| Nivel (`niveles.id`) | Ítem de menú | Cursos listados | Tabla de datos comunes |
|----------------------|--------------|-----------------|------------------------|
| Inicial (1) | Certificado Jardín | `cursos.c` = 5 o `cursec` con «SALA DE 5» | `certificadojardin` |
| Primario (2) | Certificado Sexto Grado | `cursos.c` = 6 o `cursec` con «SEXTO» | `certificadosextogrado` |

En secundario y demás niveles el ítem no aparece.

## Actores y permisos

- **Menú de Secretaría**, grupo CERTIFICADOS.
- Permiso IA **orden 97** (`PermisosIaCatalog::CERT_JARDIN_SEXTO_GRADO`).
- Filtrado por `schoolCtx()` (nivel + ciclo lectivo). Solo matrículas regulares (`idCondiciones` = 1) sin `fechaBaja`.

## Tablas y campos críticos

Un registro por tabla (`id` = 1) con datos comunes a todos los alumnos del lote:

- `serie`, `mesApro` (o `mesAprobacion`), `anoApro` (o `anoAprobacion`), `diaEmision`, `mesEmision`, `anoEmision`, `ppi` (observaciones).

Datos personales por alumno: `legajos` (nombre, DNI, lugar y fecha de nacimiento). Institución: `ento` del nivel (nombre, CUE, domicilio).

## Flujo principal

1. Listado de cursos implicados del ciclo activo.
2. Estudiantes del curso elegido: uno, varios o todos (máx. 80).
3. Formulario de datos comunes (se precarga desde la tabla). **Guardar** persiste; **Imprimir** guarda y abre el PDF (una hoja por alumno).

## Fuente de verdad

- Cursos y matrículas del ciclo/nivel de sesión.
- Datos comunes: tabla `certificadojardin` o `certificadosextogrado`.
- El PDF no calcula calificaciones; solo certifica la aprobación de sala de 5 / sexto con el texto oficial.
- Sexto grado: textos, pie y escudos según plantilla Word provincial (Monte Cristo): `CERTIFICADO DE ESTUDIOS` / `EDUCACIÓN PRIMARIA`, párrafos justificados, `CERTIFICA que…`, validez nacional y firmas Inspector / Director. Escudos extraídos del .docx: `public/img/certificados/escudo-nacion.png` (izquierda) y `public/img/certificados/escudo-cordoba.png` (derecha).

## Archivos clave

- Livewire: `app/Livewire/Certificados/CertificadoFinalizacionNivelIndex.php`
- PDF: `CertificadoJardinTcpdf`, `CertificadoSextoGradoTcpdf`
- Controlador: `CertificadoFinalizacionNivelPdfController`
- Servicio: `app/Support/Certificados/CertificadoFinalizacionNivel.php`

## Qué no hacer / reglas de negocio

- No mostrar el ítem de jardín en primario ni el de sexto en inicial.
- No listar quintos ni otras salas: solo los cursos de cierre de nivel.
- Guardado con `PersistenciaColumnas`: si el tenant no tiene la tabla o una columna con valor, error visible (no éxito falso).
- PDF nuevo: TCPDF + Arial; no DomPDF.

## Checklist al modificar

- [ ] El layout del PDF sigue el FPDF legacy (escudos, textos legales, pie DGIPE).
- [ ] El lote revalida curso y matrículas contra el contexto.
- [ ] Jardín y sexto no se cruzan de nivel.
- [ ] Tras cambiar Blade: `php artisan view:cache` y `view:clear`.
