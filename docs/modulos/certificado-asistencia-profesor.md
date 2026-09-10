# Módulo: Certificado de Asistencia del Profesor

## Propósito

Emitir en PDF un certificado de asistencia laboral del personal docente/no docente del **nivel de sesión**, con texto libre (cargo, materias, etc.) y destino (ante quién se presenta).

## Modalidades / variantes

Un solo modelo de documento. No hay variante por nivel pedagógico: cambia el personal listado y los datos de `ento` (institución, CUE, logo).

## Actores y permisos

- **Menú de Secretaría**, grupo CERTIFICADOS, ítem *Certificado de Asistencia del Profesor*.
- Permiso IA **orden 20** (`PermisosIaCatalog::CERT_ASISTENCIA_PROF`).

## Tablas y campos críticos

- Listado: `profesores` + `profesortipo`. Solo filas con `profesores.nivel` = nivel de sesión (`SchoolAlcancePedagogico::idNivelLegajosDocente()`). Excluye «Sin Rol» (`IdTipoProf = 1`).
- Persistencia opcional: `certasistprof` (`idProfesores`, `fecha`, `texto`, `parapre`).
- Institución en el PDF: `ento` del nivel de sesión.

Un mismo DNI puede tener un legajo por nivel; el certificado se emite sobre el registro del contexto activo, no sobre todos los niveles.

## Flujo principal

1. Listado paginado del personal del nivel; búsqueda por apellido, nombre o DNI.
2. **Emitir** abre el formulario (última fila de `certasistprof` del profesor como precarga).
3. **Solo guardar** persiste en `certasistprof`. **Generar PDF** opcionalmente guarda y abre el PDF por POST (sin IDs en la URL).

## Fuente de verdad

Legajo en `profesores` del nivel de sesión. El PDF no calcula ni consulta asistencia diaria: el texto lo carga quien emite.

## Archivos clave

- Livewire: `app/Livewire/Certificados/CertificadoAsistenciaProfesorIndex.php`
- Vista: `resources/views/livewire/certificados/certificado-asistencia-profesor-index.blade.php`
- Servicio: `app/Support/Certificados/CertificadoAsistenciaProfesor.php`
- Datos PDF: `CertificadoAsistenciaProfesorDatos`
- PDF: `CertificadoAsistenciaProfesorTcpdf`
- Controlador: `CertificadoAsistenciaProfesorPdfController`

## Qué no hacer / reglas de negocio

- No listar ni emitir certificados de personal de otro nivel (filtrar también en `profesorElegible`, no solo en el listado).
- No poner IDs en la URL del PDF (POST + `PdfPost`).
- PDF: TCPDF + Arial (`TcpdfFuenteArial`).

## Checklist al modificar

- [ ] El listado filtra por `profesores.nivel` del contexto.
- [ ] Abrir modal / guardar / PDF rechazan un `idProfesores` de otro nivel.
- [ ] Tras cambiar Blade: `php artisan view:cache` y `view:clear`.
