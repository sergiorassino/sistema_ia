# Módulo: Gestión de Morosos

## Propósito

Filtrar familias/estudiantes con cuotas vencidas al 2.º vencimiento y generar el PDF de **listado de deuda**, la **notificación de deuda** o **enviar esa misma notificación por mail** al responsable administrativo. Menú de Administración → Gestión de mora.

## Modalidades / variantes

Ninguna por tenant. El alcance de nivel sigue `SchoolAlcancePedagogico` (el módulo solo se habilita en Administración, que ve todos los niveles pedagógicos).

## Actores y permisos

Menú de Administración (`layouts/administracion`). Permiso `permisos_ia` orden **64** (`PermisosIaCatalog::ADMIN_MORA_GESTION_MOROSOS`). Gate: `PermisosMora::puedeGestionMorosos()`.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|-------|
| `cuotasgeneradas` | `faltapa`, `importe`, `venc1`, `venc2`, `idTerlec`, `idCursos`, `idCuotas`, `idCuotasbecas`, `idLegajos` | Solo saldo > 0 e importe > 0. Mora = `venc2` anterior a la fecha de cálculo. |
| `cursos` | `idNivel` | El filtro **Nivel** usa el nivel del curso de la cuota (cualquier ciclo). También define la cuenta SMTP del envío por mail. |
| `legajos` / `familias` / `matricula` | familia, estudiante, fuera de colegio | «Fuera de colegio» mira matrícula del ciclo activo. |
| `familias` | `apellido`, `responsable`, `email` | Destinatario del mail: responsable administrativo. `responsable` se muestra como **Nombre**. |
| `ento` | `ctaEnvioMail`, `passEnvioMail`, `insti` | Cuenta de envío institucional del **nivel pedagógico** de la cuota (no el nivel Administración). |
| `cuotas` / `cuotasbecas` / `terlec` | plantilla, beca, año de la cuota | |
| `datosvarios` | `textoInicNotDeuda`, `textoFinalNotDeuda`, `textoFinalNotDeudaBec` | Textos de la carta (PDF y mail). |

## Flujo principal

1. Fecha de cálculo (intereses y total a pagar en el PDF y en el mail).
2. Activar cada filtro opcional con su casilla (**nivel**, familia, estudiante, vencimientos, excluir cuotas, cursos, cantidad de cuotas, fuera de colegio, año lectivo, becados).
3. Generar **Listado de Deuda** o **Notificación de Deuda**. Si no hay registros: aviso SweetAlert, no se abre el PDF.
   La notificación termina con el texto parametrizado (`textoFinalNotDeuda` / `textoFinalNotDeudaBec`); no incluye bloque ni leyenda de firma.
4. **Enviar notificación por mail:** arma la lista de destinatarios (una fila por familia, igual que el PDF) y abre un modal de previsualización. Ahí se ven apellido, nombre (`familias.responsable`) y `familias.email`; las filas con datos faltantes o email inválido quedan destacadas. El envío usa la cuenta institucional del nivel (`ento.ctaEnvioMail`). Solo se envía a quienes tienen email válido y SMTP configurado en ese nivel. Estudiantes sin familia asignada no tienen `familias.email`: aparecen en la lista y no se envían.

Sin filtros opcionales: familias con cuotas vencidas al 2.º vencimiento y saldo > 0.

## Filtro por nivel

- Casilla **Nivel** + selector (Inicial, Primario, Secundario, …).
- Si está activo, hay que elegir un nivel válido del alcance.
- Aplica a `cursos.idNivel` de la cuota generada (no solo al ciclo activo).
- Con nivel activo, el selector de **Cursos (ciclo activo)** se acota a ese nivel; si cambia el nivel, se quitan cursos incompatibles.
- En el mail: si el filtro nivel está activo, todos los envíos salen de esa cuenta; si no, cada familia usa el `idNivel` del curso de sus cuotas.

## Fuente de verdad

Consulta: `GestionMorososConsulta` + `GestionMorososFiltros::aplicarAConsulta`. PDFs: `ListadoMorososDatos` / `NotificacionDeudaDatos` (TCPDF). Pedido en caché: `GestionMorososPdfPedido` (token opaco en la URL). Mail: `NotificacionDeudaCorreo` + `NotificacionDeudaMail` (mismo agrupado y mismos textos que el PDF). SMTP: `MailInstitucionalConfig` por `idNivel`. En `APP_ENV=local` el correo queda en el log (`MailDesarrollo`).

## Archivos clave

- `app/Livewire/Mora/GestionMorososIndex.php`
- `app/Support/Mora/GestionMorososFiltros.php`
- `app/Support/Mora/GestionMorososConsulta.php`
- `resources/views/livewire/mora/gestion-morosos-index.blade.php`
- PDF listado: `ListadoMorososPdfController` + `ListadoMorososTcpdf`
- PDF notificación: `NotificacionDeudaPdfController` + `NotificacionDeudaTcpdf`
- Mail: `app/Support/Mora/NotificacionDeudaCorreo.php`, `app/Mail/NotificacionDeudaMail.php`, `resources/views/mail/mora/notificacion-deuda.blade.php`

## Qué no hacer / reglas de negocio

- No filtrar mora por `venc2` como si fuera el rango «1º venc.» (ese rango es `venc1`).
- No calcular promedios (módulo de cuotas).
- URLs de PDF con `{ref}` opaco, no IDs de familia/legajo.
- No dibujar bloque ni leyenda de firma (p. ej. «Representante Legal») al pie de la notificación.
- No enviar el mail desde `ento` del nivel Administración (5): siempre la cuenta del nivel pedagógico.
- No usar `legajos.emailmad` / `emailpad` / `emailtut` en este envío: el destinatario es `familias.email`.
- No mostrar éxito si no se envió ningún correo.

## Checklist al modificar

- [ ] Casilla marcada exige valor válido (nivel, cursos, fechas, etc.).
- [ ] Filtro nivel valida IDs contra `nivelesParaSelector()` y aplica `whereHas('curso', idNivel)`.
- [ ] Cursos del selector respetan el nivel elegido.
- [ ] Listado, notificación PDF y mail usan la misma consulta de filtros.
- [ ] Previsualización de mail marca faltantes de apellido, nombre o email.
- [ ] Envío SMTP con `MailInstitucionalConfig::aplicarParaNivel` del nivel de la cuota.
