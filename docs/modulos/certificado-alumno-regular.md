# Módulo: Constancia de Alumno Regular

## Propósito

Emisión en PDF de la constancia de alumno/a regular para estudiantes matriculados en el ciclo y nivel del contexto. Un mismo ítem de menú, dos modelos de documento.

## Modalidades / variantes

Al entrar al módulo se elige el tipo. Hasta que no hay elección no se lista ningún alumno. **Cambiar tipo** vuelve a esa pregunta.

| Tipo | Uso | PDF | Formulario al emitir |
|------|-----|-----|----------------------|
| **Laboral** | Modelo histórico (inicio/fin de año, presentado por y ante) | `CertificadoAlumnoRegularTcpdf` | `iniFin`, `fechIniFin`, `prePor`, `prePorDni`, `preAnte`, `fechaEmision` |
| **Escolar** | Constancia del ciclo en curso, para presentar ante un organismo | `CertificadoAlumnoRegularEscolarTcpdf` | `fechaEmision`, `preAnte` |

Datos de alumno, curso, DNI, localidad y año lectivo salen del sistema (matrícula + `ento` + `terlec`), igual en ambos modelos.

Texto del modelo **escolar**:

> CONSTANCIA DE ALUMNO/A REGULAR
>
> Se hace constar que el/la estudiante {apellido nombre}, DNI Nº {dni}, es alumno/a regular de {curso/división} en este establecimiento, en el presente ciclo lectivo normal, correspondiente al año {año del ciclo}.
>
> Este certificado se extiende en {localidad}, a los {día} días del mes de {mes} del año {año de emisión}, para ser presentado ante {preAnte}.

## Actores y permisos

- **Menú de Secretaría**, grupo CERTIFICADOS, ítem *Constancia de Alumno Regular*.
- Permiso IA **orden 17** (`PermisosIaCatalog::CERT_ALUMNO_REGULAR`).
- Filtrado por `schoolCtx()` (nivel + ciclo lectivo). Solo matrículas regulares (`CierreAnualSecundario::idsCondicionesMatricula()`) sin `fechaBaja`.

## Tablas y campos críticos

- Listado: `matricula` + `legajos` + `cursos` / `curplan` / `turnos_clase`.
- Persistencia opcional: `certalureg` (`idLegajos`, `iniFin`, `fechIniFin`, `prePor`, `prePorDni`, `preAnte`, `fechaEmision`). En el modelo escolar se guardan `preAnte` y `fechaEmision`; el resto se completa con valores neutros para no romper columnas legacy.
- Institución: `ento` del nivel (`insti`, `cue`, `localidad`, logo).

## Flujo principal

1. Pregunta de tipo (laboral / escolar).
2. Listado paginado de matriculados del ciclo activo; búsqueda por apellido, nombre o DNI.
3. **Emitir cert.** abre el formulario del modelo elegido (último `certalureg` del legajo como precarga).
4. **Solo guardar** persiste en `certalureg`. **Generar PDF** opcionalmente guarda y abre el PDF por POST (sin IDs en la URL).

## Fuente de verdad

Matrícula activa del contexto. El PDF no calcula calificaciones. Año del cuerpo escolar: `terlec.ano` del ciclo de sesión. Fecha del pie: `fechaEmision` del formulario (`d/m/Y` en partes en español).

## Archivos clave

- Livewire: `app/Livewire/Certificados/CertificadoAlumnoRegularIndex.php`
- Vista: `resources/views/livewire/certificados/certificado-alumno-regular-index.blade.php`
- Servicio: `app/Support/Certificados/CertificadoAlumnoRegular.php`
- Datos PDF: `CertificadoAlumnoRegularDatos`
- PDF laboral: `CertificadoAlumnoRegularTcpdf`
- PDF escolar: `CertificadoAlumnoRegularEscolarTcpdf`
- Controlador: `CertificadoAlumnoRegularPdfController`

## Qué no hacer / reglas de negocio

- No mezclar textos: el laboral no usa el párrafo escolar y viceversa.
- No listar alumnos hasta que el usuario eligió el tipo.
- PDF escolar nuevo: TCPDF + Arial (`TcpdfFuenteArial`) y párrafos con `TcpdfMultiCellJustificado`. El laboral se mantiene como está (legacy).
- No poner IDs en la URL del PDF (POST + `PdfPost`).

## Checklist al modificar

- [ ] Al entrar se pregunta laboral vs escolar.
- [ ] El PDF laboral no cambia su redacción.
- [ ] El PDF escolar completa nombre, DNI, curso, año, localidad, fecha y “presentado ante” con datos del sistema / formulario.
- [ ] El listado sigue filtrado por contexto y matrícula activa.
- [ ] Tras cambiar Blade: `php artisan view:cache` y `view:clear`.
