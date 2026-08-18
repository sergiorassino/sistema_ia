# Módulo: Descarga de rendición SIRO

## Propósito

Cargar el archivo de cobranza SIRO (rendición Integrado, `.txt` de ancho fijo), identificar cada pago contra `cupones_a_pagar` / `cuotasgeneradas`, persistir filas en `rendicionesroela` e **impactarlas** en las cuotas de los alumnos (`cuotaspagos` + `cuotasgeneradas.faltapa` / `pagado`).

No sube deuda a SIRO (eso es **Subida base de deuda** / **Cupones vencidos**). Este módulo solo **baja** lo cobrado.

Nombre en el sidebar: **Descarga rendición** (grupo Medios de pago → SIRO).

## Modalidades / variantes

- Tenant: `tenantCuotasSiroHabilitado()` (`config/tenants/{slug}.php` → `cuotas.siro.habilitado`). Sin eso no hay ítem ni permiso efectivo.
- Alta de planilla: `cuotas.siro.descarga_rendicion.canales_planilla` (abrevs o nombres de `cuotastipopago`). Vacío = todos los medios con abrev en BD.
- Discriminación del pago por familia de barcode (SIRO Integrado v5.2):
  - **0449 / 0444 / 0447:** electrónico (`idComprobante` → `id_factura`).
  - **0448:** cupón impreso (Pago Fácil, Rapipago, ventanilla). Formato **nuevo** (ID cliente extendido en la cola) o **anterior** (barcode legacy; `ultUpload` se toma de `cuotasgeneradas`).
- Dos **provisorios de puesta en marcha** (interruptores `HABILITADO`, independientes). Ver sección correspondiente.

## Actores y permisos

- **Menú de Administración** (`layouts/administracion`), no Secretaría pedagógica.
- Auth + `school.context`. Rutas con middleware `permiso:` orden **49** (`PermisosIaCatalog::ADMIN_ARANCELES_ESTUDIANTE`).
- Gate de negocio: `PermisosCuotas::puedeSiroDescargaRendicion()` = nivel Administración + permiso 49 + SIRO habilitado en el tenant.
- Ciclo lectivo: `schoolCtx()->idTerlec`. Toda cuota generada se revalida contra ese ciclo.
- Rate-limit: alta de planilla, proceso de archivo, impacto, PDF.

## Tablas y campos críticos

| Tabla | Campos | Rol |
|-------|--------|-----|
| `planillasdescargacuotas` | `nroPlanilla` (único), `fecha`, `desde`/`hasta` (rango de acreditación del archivo), `canalPago`, `nombreArchivo` (máx. 50), `impactado` | Cabecera. `impactado=1` cuando no queda ninguna rendición pendiente. |
| `rendicionesroela` | `fechaPago`, `fechaAcreditacion`, `idCuotastipopago`, `idLegajos`, `nroPlanilla`, `idCuotas`, `fechVenc1`, `importe` (capital), `pagado` (cobrado SIRO), `interes`, `bonificacion`, `nombreArchivo`, `cadenaPago` (línea completa), `idCuotasbecas`, `idCuotasgeneradas`, `impactado`, `idCursos`, `obs` | Un pago descargado. `obs` = avisos de duplicado (no provisorios). |
| `cupones_a_pagar` | `id_factura` (20 dígitos), `saldo_pagar`, `importe1/2/3venc`, `fecha1/2/3venc`, `id_cuotas_generadas` | Snapshot del cupón al emitirse / subirse a SIRO. |
| `cuotasgeneradas` | `id`, `idLegajos`, `idCuotas`, `idTerlec`, `faltapa`, `pagado`, `interes`, `bonificacion`, `ultUpload`, `fechaPago`, `avisoPago` | Cuota del alumno. El impacto escribe acá. |
| `cuotaspagos` | `idCuotasGeneradas`, `idCuotastipopago`, `fechhora`, `importe`, `bonificacion`, `interes`, `nombreArchivo`, `cadenaPago` | Movimiento de caja al impactar. |
| `cuotastipopago` | `id`, `abrev`, `tipoPago` | Canal SIRO (BPD, PF, RP, FSF, LK, TQR, …). Si el canal del archivo **no** está acá → rechazo SIRO (BPR, DDR, etc.), no se persiste. |

`id_factura` SIRO (modelo 26, 20 dígitos): `legajo(8) + idCuotas(7) + ultUpload(2) + últimos 3 de idCuotas`. Clase: `App\Support\Cuotas\Siro\SiroIdFactura`.

## Pantallas y rutas

| Ruta | Componente | Qué hace |
|------|------------|----------|
| `GET /cuotas/siro-descarga` (`cuotas.siro-descarga`) | `SiroDescargaRendicionPlanillasIndex` | Listado de planillas + alta (nº, fecha, canal). |
| `GET /cuotas/siro-descarga/{nroPlanilla}` (`cuotas.siro-descarga.detalle`) | `SiroDescargaRendicionDetalle` | Subir `.txt`, procesar, grilla de pagos, impactar, borrar, PDF. |
| `GET /cuotas/siro-descarga/pdf/{ref}` (`cuotas.siro-descarga.pdf`) | `SiroDescargaRendicionPlanillaPdfController` | PDF TCPDF de la planilla. `{ref}` = `OpaqueRouteToken` (sin nº en la URL). |

Paginación del listado de planillas: 20 por página. La grilla de rendiciones **no** pagina: muestra todas las filas de esa planilla.

## Formato del archivo SIRO

Rendición **Integrado v5.2**, una línea por cobro, ancho fijo. Parser: `SiroDescargaRendicionLinea`.

Posiciones **0-based** en código (el manual SIRO las documenta en base 1):

| Campo | Offset / largo | Notas |
|-------|----------------|-------|
| Fecha pago | 0, 8 | `YYYYMMDD` |
| Fecha acreditación | 8, 8 | |
| 1.er vencimiento | 16, 8 | |
| Importe pagado | 24, 11 | **centavos** enteros |
| Id usuario | 35, 8 | |
| Concepto | 43, 1 | |
| Código de barras | 44, 59 (o regex `0448`/`0449`…) | Familia 448 vs 449/444/447 |
| Id comprobante | 103, 20 | Electrónicos: suele ser el `id_factura` |
| Canal | 123, 3 | BPD, PF, BPR, … |
| Texto tras canal | 126+ | Motivo de rechazo (BPR 402 FONDOS INSUFICIENTES, …) |
| Id pago SIRO | 226, 10 | Líneas ≥ 236 caracteres |
| ID cliente extendido | cola (offset 272 / 373) | Solo 448 formato nuevo |

Largo mínimo: **126**. Línea más corta → omitida (formato inválido), no bloquea el resto.

El importe cobrado que se persiste es siempre el del archivo (`pagado` = centavos / 100).

## Flujo principal

### 1. Alta de planilla

Secretaría/Administración crea cabecera (`nroPlanilla` único, sugerido = último + 1). Aún no hay pagos. Se redirige al detalle.

### 2. Procesar archivo (`SiroDescargaRendicionArchivo::procesar`)

Por cada línea no vacía, en orden:

1. Parsear. Inválida → omitida (no bloquea).
2. Armar `id_factura` buscado + etiqueta de modalidad (electrónico / 448 nuevo / 448 legacy).
3. Canal desconocido → **rechazo SIRO**: se informa en el modal, **no** va a `rendicionesroela`, **no** bloquea el resto.
4. ¿La misma cadena o el mismo id SIRO **ya está persistido en esta planilla** (carga anterior)? → omitir esa línea (no crear otra fila). **No** aplica a un duplicado *dentro del mismo archivo en esta corrida*.
5. Resolver cupón/cuota (`SiroDescargaRendicionCupon::resolver`).
6. Si no hay cuota, o hay cuota pero no cupón y no es provisorio 2 → **bloqueo**: esa línea es “No encontrado”.
7. Detectar duplicados (ver más abajo) y desglosar (`SiroDescargaRendicionCalculo`). Desglose inválido → también bloqueo.
8. Acumular en `$pendientes` (aún no hay INSERT).

**Todo o nada de cupones:** si hubo ≥1 bloqueo (cupón no resoluble o desglose inválido), **no se persiste ningún pago** de ese archivo. Las filas que sí habían matcheado aparecen en el modal como **Omitido** (*«No descargado: hay cupones sin resolver en el archivo.»*). Los rechazos de canal y los formatos inválidos no disparan este veto.

Si no hubo bloqueos: transacción INSERT en `rendicionesroela` (`impactado=0`), actualiza `nombreArchivo`, `desde`/`hasta` de la planilla.

Reprocesar el **mismo** archivo sobre una planilla que ya tiene esas filas: se omiten (ya cargadas). Si hace falta rehacer la carga: **Borrar todos** (solo si la planilla no está impactada) y volver a procesar.

### 3. Impactar (`SiroDescargaRendicionImpacto::impactarPlanilla`)

Recorre las rendiciones de la planilla (`orderBy id`):

- Ya `impactado=1` → no toca.
- Inserta `cuotaspagos` y actualiza `cuotasgeneradas`: `pagado += (importe + interes - bonificacion)`, `faltapa -= importe` (capital).
- Puede dejar `faltapa` **negativo** (pago duplicado). Avisa; no aborta.
- Misma cadena SIRO ya en `cuotaspagos`: **igual imputa** (segundo movimiento). Avisa; no omite.
- Marca la rendición `impactado=1`. Si no queda ninguna en 0, marca la planilla.

No se puede borrar la planilla ni sus pagos si `planilla.impactado=1`.

## Identificación del cupón / cuota (cadena de match)

Orden en `SiroDescargaRendicionCupon::resolver`:

1. **Exacto.** `cupones_a_pagar.id_factura` = id armado desde la línea. `matchTipo = exacto`. La cuota es `cupones_a_pagar.id_cuotas_generadas` filtrada por `idTerlec`.
2. **Provisorio 1 — upload cercano** (solo 449/444/447, `HABILITADO`). Misma cadena de 20 dígitos salvo posiciones 16–17 (`ultUpload`); se elige el upload más cercano. `matchTipo = upload_cercano`. **Los 448 no entran** (si lo hicieran, podrían tomar un cupón de subida SIRO y saltarse el provisorio 2).
3. **Provisorio 2 — 448 sin cupón** (solo 448, `HABILITADO`). Si no hubo cupón: identifica `cuotasgeneradas` por `idLegajos` + `idCuotas` del barcode / ID extendido + ciclo. `matchTipo = sin_cupon_448`. Cupón nulo, cuota sí.
4. Si nada resolvió: error, bloqueo de todo el archivo.

La cuota del cupón **debe** pertenecer al ciclo de sesión. Si el cupón existe pero la cuota no es de ese `idTerlec` → no descargable.

## Desglose de importes

Clase: `SiroDescargaRendicionCalculo`. Tolerancia ±0,02.

- **`pagado`:** importe SIRO (archivo).
- **`importe`:** capital (de `cupones_a_pagar.saldo_pagar`, o `faltapa` / importe de la cuota en provisorio 2).
- **`bonificacion`:** si pagó de menos respecto del capital (pago en 1.er vencimiento).
- **`interes`:** si pagó de más.

Tramo del cupón: por fecha de pago vs `fecha1/2/3venc` **y** coincidencia con `importe1/2/3venc`. Si la fecha no cierra pero algún vencimiento tiene el mismo importe, se acepta ese tramo.

Si el importe del archivo **no** coincide con ningún vencimiento:

- 449/444/447 con provisorio 1, o 448 con provisorio 2 → se usa el importe del archivo y se desglosa contra el capital (`provisorioImporteArchivo = true`).
- Si no hay provisorio aplicable → no descargable (bloqueo de archivo).

## Puesta en marcha (provisorios a quitar)

Avisos ámbar en listado de planillas y en el detalle. Textos de `SiroDescargaRendicionProvisorios::mensajesAvisoFormulario()`.

Desactivar poniendo `HABILITADO = false` en la clase (o borrarla) cuando el circuito de cupones esté alineado.

| | Provisorio 1 | Provisorio 2 |
|--|----------------|--------------|
| Clase | `SiroDescargaRendicionMatchUploadCercano` | `SiroDescargaRendicionMatchCuotaSinCupon448` |
| Familia | 0449 / 0444 / 0447 | 0448 |
| Qué cubre | `id_factura` no exacto (upload distinto); o cupón hallado pero importe ≠ 1v/2v/3v | No hay fila en `cupones_a_pagar`; o hay fila pero el importe no cierra (barcode legacy con `relleno=000000`, `ultUpload` de la cuota) |
| Qué no cubre | 448 | 449. Si el 448 **está** en la tabla y el importe **cierra**, se usa el snapshot (no es P2) |

Los avisos de match provisorio van al **modal de carga**, no a `rendicionesroela.obs` (`esAdvertenciaMatchProvisorio`: textos con «Match provisorio», «Provisorio upload cercano», «Provisorio 448», «PROVISORIO»).

Columna **Detalle** del modal cuando aplicó un provisorio:

```
PROVISORIO: id_factura archivo: … - Importe archivo: $… - id_factura cupones_a_pagar: … - importes cupones_a_pagar: 1v …  2v …  3v …. RESOLVIENDO POR: …
```

`RESOLVIENDO POR` posibles:

- `provisorio 1 — upload cercano (449)`
- `provisorio 1 — upload cercano (449); importe archivo distinto a vencimientos del cupón`
- `provisorio 2 — 448 sin cupón en cupones_a_pagar`
- `importe archivo distinto a vencimientos del cupón`

Si no hay cupón: id e importes del cupón se muestran como `—`.

## Pagos duplicados

Se **registran igual** (salvo que la línea ya existiera en esta planilla de una carga previa). Se avisa; no se bloquea el archivo.

### Cuándo se considera duplicado

| Situación | Dónde se detecta | Qué se hace |
|-----------|------------------|-------------|
| Misma cadena o mismo id SIRO **ya persistido en esta planilla** | `motivoDuplicadoEnPlanilla` (índice leído de BD al empezar) | No crea otra fila. Si al reprocesar se detecta duplicado vs **otra** planilla, actualiza `obs` de la fila existente. |
| Mismo id SIRO **dos veces en este archivo** | `$idsPagoVistos` | Segunda+ se registra. Aviso: registro de la primera. |
| Misma `idCuotasgeneradas` **dos veces en este archivo** | `$cuotasVistasEnArchivo` | Igual: se registran las dos. Ejemplo: líneas 6 y 52 idénticas (`CobranzasSiro_Cta. 1105_20260811txtrepetidomismo.txt`). |
| Mismo id SIRO ya descargado en **otra** planilla (impactada o no) | `nroPlanillaPreviaPorIdPagoSiro` (LIKE anclado en pos. 227–236, no un LIKE libre sobre el barcode) | Se registra. Obs: «Pago repetido: pagado por primera vez en planilla N (SIRO …)». |
| Misma cuota ya descargada en **otra** planilla | `nroPlanillaPreviaPorCuota` | Se registra. |
| Cuota con `faltapa ≈ 0` o `pagado > 0` al descargar | `SiroDescargaRendicionCalculo` | Se registra. |

**No** usar el índice en memoria del archivo actual para *omitir*: eso era lo que tiraba la segunda línea del mismo `.txt`. El índice de BD solo evita reprocesar una carga ya grabada.

### Cómo se muestra

- Modal **Resultado:** estado `encontrado_duplicado` → etiqueta **Duplicado** (rojo). Encabezado: `Pagos duplicados (se registran igual): N.`
- **Detalle** del modal: empieza con `PAGO DUPLICADO: …`. Si también hubo provisorio: `PAGO DUPLICADO: … | PROVISORIO: …`.
- Grilla de la planilla: columna **Obs.** siempre visible. Leyenda corta **PAGO DUPLICADO** en rojo (`leyendaCortaObs`); el texto completo va en `title`. Si `obs` en BD está vacío, `completarLeyendaDuplicados` infiere duplicado (misma cuota repetida en la planilla u otra planilla) solo para mostrar; no escribe en BD hasta el próximo proceso.
- `rendicionesroela.obs` (máx. 500): prefijo `PAGO DUPLICADO:` + avisos, **sin** textos de match provisorio.

## Impacto en la cuota

Tras **Impactar los pagos en las cuotas de los alumnos**:

1. Primer pago de la cuota: `faltapa` baja; `pagado` sube; fila en `cuotaspagos`.
2. Segundo pago (duplicado en la misma planilla, misma cadena SIRO): **también** inserta `cuotaspagos` y vuelve a restar capital. `faltapa` puede quedar negativo. Avisos: *«Pago doble: la cuota ya estaba saldada…»*, *«Pago duplicado: misma cadena SIRO ya imputada; se impacta igual…»*, *«Saldo negativo tras el impacto ($…)»*.
3. Si un impacto anterior había dejado en `obs` *«Pago ya registrado en cuotaspagos (misma cadena).»*, al impactar de nuevo se limpia esa frase (`limpiarObsTrasImpacto`) y se imputa.

La guarda `impactado=1` en la rendición evita imputar dos veces **la misma fila** si se pulsa Impactar otra vez. El duplicado es **otra fila** (`impactado=0` hasta que corre).

## UI

- Identidad SE: `se-page`, `se-hero`, `se-card`, `se-toolbar`, paleta institucional. Grilla ancha tipo planilla (`.gf-siro-descarga-rendiciones`) con scroll horizontal y `justify-start` (no centrar: muchas columnas).
- Modal de resultado: `@teleport('body')`, patrón Livewire de modales. Botón Imprimir del resumen (HTML, no TCPDF).
- PDF de planilla: `SiroDescargaRendicionPlanillaTcpdf` + Arial (`TcpdfFuenteArial`). Fechas `d/m/Y`.
- Diálogos: SweetAlert2 (`seSwal*`), no `wire:confirm`.

Estados del modal por registro: `encontrado`, `encontrado_duplicado`, `no_encontrado` (rojo, bloquea persistencia), `omitido` (ámbar: formato inválido, ya cargado, o arrastre del todo-o-nada), `rechazo` (canal SIRO no configurado).

## Fuente de verdad

- Cobrado: archivo SIRO (`pagado`).
- Desglose capital / interés / bonificación: snapshot `cupones_a_pagar`, **no** el `faltapa` actual, salvo provisorio 2 sin cupón (ahí el capital es `saldo_pagar` o `faltapa` / importe de la cuota).
- No recalcular promedios ni tocar calificaciones.

## Archivos clave

**Livewire / HTTP**

- `app/Livewire/Cuotas/SiroDescargaRendicionPlanillasIndex.php`
- `app/Livewire/Cuotas/SiroDescargaRendicionDetalle.php`
- `resources/views/livewire/cuotas/siro-descarga-rendicion-planillas.blade.php`
- `resources/views/livewire/cuotas/siro-descarga-rendicion-detalle.blade.php`
- `app/Http/Controllers/Cuotas/SiroDescargaRendicionPlanillaPdfController.php`
- `routes/web.php` (grupo `/cuotas`, permiso 49)

**Dominio** (`app/Support/Cuotas/Siro/Descarga/`)

- `SiroDescargaRendicionArchivo.php` — orquestación de la carga, duplicados, `obs`, Detalle
- `SiroDescargaRendicionCupon.php` — cadena exacto → P1 → P2
- `SiroDescargaRendicionCalculo.php` — desglose
- `SiroDescargaRendicionImpacto.php` — imputación
- `SiroDescargaRendicionLinea.php` / `Canal.php` / `IdFactura.php` / `BarcodeFamilia.php`
- `SiroDescargaRendicionResolucion448.php` + `IdentUsuario448Nuevo` / `IdClienteExtendido` / `BarcodeComprobante448` / `Barcode448UltUpload`
- `SiroDescargaRendicionBarcodeElectronico.php` / `IdComprobante.php`
- `SiroDescargaRendicionMatchUploadCercano.php` / `MatchCuotaSinCupon448.php` / `Provisorios.php`
- `SiroDescargaRendicionResumen.php` / `Consulta.php` / `PlanillaDatos.php` / `PlanillaTcpdf.php`

**Modelos:** `PlanillaDescargaCuota`, `RendicionRoela`, `CuponAPagar`, `CuotaGenerada`, `CuotaPago`.

**Permisos / menú:** `PermisosCuotas`, `PermisosMediosPago`, `resources/views/layouts/partials/sidebar-nav-administracion.blade.php`.

**CSS grilla:** `resources/css/app.css` (`.gf-siro-descarga-rendiciones`, `.gf-td-obs`).

## Tests

Unitarios en `tests/Unit/SiroDescargaRendicion*Test.php`: parseo de línea, familias 448/449, id comprobante, ID cliente extendido, barcode 448, canal, cálculo/desglose, ambos provisorios, archivo (obs, duplicados, LIKE del id SIRO), resumen del modal, impacto (avisos de cadena, limpieza de obs).

Al tocar match, desglose o duplicados, correr:

```text
php artisan test --filter=SiroDescargaRendicion
```

## Qué no hacer / trampas

- No persistir una carga **parcial** cuando falló un cupón (los rechazos de canal sí pueden convivir con una carga exitosa del resto).
- No meter 448 en el provisorio 1 (upload cercano).
- No poner avisos de provisorio en `rendicionesroela.obs`.
- No buscar el id SIRO con `LIKE '%id%'` suelto: el id también puede aparecer en el código de barras. Usar `patronLikeIdPagoSiro` (226 `_` + id + `%`).
- No omitir la segunda fila del **mismo** archivo: se registra con `PAGO DUPLICADO`. Omitir solo si esa cadena/id **ya estaba en BD** en esta planilla.
- No abortar el impacto porque la cadena SIRO ya esté en `cuotaspagos`: el duplicado de la misma planilla debe imputarse (saldo negativo).
- No calcular el desglose con el estado actual de la cuota si hay cupón con vencimientos que cierran.
- No poner `nroPlanilla` en la URL del PDF; usar `OpaqueRouteToken`.
- No usar DomPDF para el PDF nuevo de esta planilla (TCPDF + Arial).
- `nombreArchivo` se recorta a 50 caracteres (columna legacy).
- Tras cambiar CSS de la grilla: `npm run build`. Tras Blade: `php artisan view:clear`.

## Checklist al modificar

- [ ] ¿El cambio es permanente o otro provisorio? Si es provisorio: `HABILITADO`, aviso ámbar, filtrar con `esAdvertenciaMatchProvisorio`.
- [ ] ¿Sigue el todo-o-nada cuando un cupón no se resuelve?
- [ ] ¿Un duplicado en el mismo archivo se registra (modal Duplicado + `obs`) y al impactar deja `faltapa` negativo?
- [ ] ¿Reprocesar el mismo archivo sobre filas ya grabadas no duplica INSERT?
- [ ] Tests `SiroDescargaRendicion*` en verde.
- [ ] Modal: Detalle `PROVISORIO:` / `PAGO DUPLICADO:` según corresponda; grilla Obs. en rojo.
- [ ] Permiso 49 + tenant SIRO + ciclo `schoolCtx()->idTerlec`.
- [ ] Esta ficha actualizada.
