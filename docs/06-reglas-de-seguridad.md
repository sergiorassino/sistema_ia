# Reglas de Seguridad (Baseline Obligatorio)

> Estas reglas aplican a **todo módulo nuevo** y a cambios en módulos existentes.
> Son el estándar mínimo de seguridad del sistema.

---

## 1. Autenticación y Sesión

- Usar `auth` middleware para toda ruta interna.
- Cookies/sesión seguras (config Laravel): `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `same_site`.
- Regenerar sesión/token en login/logout (ya implementado).
- Dos guards separados: gestión (`profesores`) y autogestión (`legajos`).

---

## 2. Autorización (evitar acceso indebido)

- Todo ABM debe tener **chequeo de alcance por contexto** y/o permisos:
  - Si el módulo depende de `schoolCtx()`, **filtrar queries** por 
    `schoolCtx()->idNivel` / `idTerlec` según corresponda.
  - En operaciones por ID (editar/eliminar), **volver a consultar** el registro 
    con el mismo filtro (no confiar en IDs del cliente).
- Verificar permisos usando el modelo de cadena `0/1` de `profesores.permisos`
  contra `permisosusuarios.orden` (ver [03-autenticacion-y-permisos.md](03-autenticacion-y-permisos.md)).
- Si más adelante se implementan Policies/Gates, centralizar allí y llamar 
  desde Livewire (ej: `authorize()`).

---

## 3. Validación, Normalización y Seguridad de Datos

- Validar **siempre server-side** (`$this->validate()` o FormRequest).
- Normalizar entradas antes de guardar:
  - `trim()` en strings.
  - `strtoupper()` cuando corresponda (abreviaturas/códigos).
- Evitar mass-assignment peligroso:
  - Preferir `$fillable` explícito en modelos (no usar `$guarded = []` en modelos nuevos).
  - En updates/creates, pasar arrays con claves explícitas (no `->update($this->all())`).

---

## 4. Protección contra XSS

- En Blade, usar `{{ }}` (escape) siempre.
- Evitar `{!! !!}`; si fuese indispensable, sanitizar en backend primero.
- No interpolar HTML/JS con datos de usuario en atributos JS. Si se requiere, 
  castear/escapar.

---

## 5. SQL Injection

- Usar Eloquent/Query Builder con parámetros (bindings).
- Evitar `DB::raw()` con entrada de usuario.
- Si hay que usar `DB::raw()`, que sea **constante** y revisada.

---

## 6. Rate Limiting y Abuso

- Rate-limit en acciones sensibles (crear/editar/eliminar) en Livewire/Controllers.
- Límites por usuario con ventanas cortas:
  - Save/update: máx 30/min
  - Delete: máx 10/min

---

## 7. Errores y Logging

- No exponer trazas/SQL en producción (`APP_DEBUG=false`).
- Loggear eventos de ABM importantes (crear/editar/eliminar) sin datos sensibles.

---

## 8. Checklist por Módulo / PR

Antes de considerar completo un módulo o PR:

- [ ] ¿La consulta está filtrada por contexto (`schoolCtx`) cuando corresponde?
- [ ] ¿Acciones por ID revalidan alcance del registro?
- [ ] ¿Hay validación y normalización server-side?
- [ ] ¿No hay `DB::raw()` con input?
- [ ] ¿Blade escapa correctamente (sin `{!! !!}`)?
- [ ] ¿Rate limiting configurado en acciones sensibles?
- [ ] ¿Permisos verificados según modelo de cadena `0/1`?
- [ ] ¿Se evitó cualquier operación destructiva o recreación masiva de BD sin backup y aprobación explícita?
- [ ] Si hubo cambios de esquema o datos vía SQL o migraciones, ¿se ejecutaron solo bajo revisión humana (no automatizada por herramientas)?
- [ ] ¿Quedó documentado al cierre el **SQL equivalente** (o comando Artisan explícito) para reproducir el cambio en BD, según §9.1?
- [ ] ¿Las URLs visibles (rutas GET, PDF, descargas) **no** exponen IDs de BD, DNI, legajo ni otros identificadores enumerables (ver §10)?
- [ ] ¿Los guardados validan columnas existentes, capturan `QueryException` y no muestran éxito si la BD no persistió los datos (ver `docs/05-preferencias-y-convenciones.md` §14)?

---

## 10. URLs sin identificadores reveladores

Las URLs que el usuario ve (barra de direcciones, historial, referrers, logs de proxy) **no deben** incluir:

- IDs numéricos de tablas (`/recurso/44205`, `?id=123`).
- DNI, número de legajo, CUIL u otros documentos.
- Códigos internos predecibles o secuenciales.

### Qué hacer en su lugar

| Caso | Patrón |
|------|--------|
| Enlace GET a PDF/descarga con sesión (portal familia, docentes) | Parámetro de ruta **opaco** con `App\Support\Security\OpaqueRouteToken` (cifrado con `APP_KEY`). Ejemplo: comprobante de pago de aranceles. |
| Formulario público sin sesión previa | Token aleatorio persistido en BD (ej. `AspirantesTokenService` en `aspiento.token`). |
| Pantallas internas de ABM (secretaría) | Rutas con `{id}` solo tras `auth` + permisos; no usar ese patrón en autogestión ni enlaces que se comparten/abren en pestaña nueva. |

### Implementación obligatoria

1. **Generar** el token al armar el enlace (`OpaqueRouteToken::for…` o servicio equivalente), incluyendo el **alcance** (ej. `idLegajo` del contexto de sesión).
2. **Decodificar** en el controlador y responder **404** si el token es inválido (no 403 detallado).
3. **Revalidar** el registro con los mismos filtros que sin token (`cuotaPendienteParaAutogestion`, `schoolCtx`, permisos).
4. **Nombre de archivo** de descarga sin IDs internos.
5. Registrar un **propósito** distinto (`PURPOSE_*`) por cada tipo de enlace.

Referencia de código: `app/Support/Security/OpaqueRouteToken.php`, `ComprobantePagoPdfController`, regla Cursor `urls-sin-identificadores.mdc`.

---

## 9. Base de datos: operaciones destructivas y control de ejecución

**Prohibido** salvo entorno 100% desechable, **backup verificado** y **aprobación explícita por escrito** de quien opera el sistema: `php artisan migrate:fresh`, `migrate:refresh`, `db:wipe`, recreación total del esquema, importar dumps o scripts SQL que **reemplacen** la base o tablas enteras, `DROP DATABASE`, `DROP TABLE` / `TRUNCATE` masivos sin plan de contingencia, y borrar volúmenes o directorios de datos del motor sin backup previo. Estas acciones pueden borrar datos de producción o dejar el esquema sin integridad referencial (por ejemplo, perdiendo claves foráneas si el artefacto aplicado no las incluye).

**Política de desarrollo asistido (por el momento):** ningún agente de IA ni automatización debe **ejecutar** nada que altere esquema o datos en la base configurada del proyecto, **aunque quien desarrolle pida explícitamente borrar, vaciar o actualizar tablas**. Eso incluye no solo SQL y `php artisan migrate*`, `migrate:rollback`, `db:*`, `db:seed`, cliente `mysql` e imports, sino también **cualquier vía indirecta**: `php artisan tinker` (o ejecución interactiva), `php -r` / scripts PHP de una sola corrida que llamen a Eloquent, `DB::`, `Model::query()->delete()`, factories en ejecución, rutas o comandos Artisan invocados desde terminal con efecto inmediato sobre la BD, etc. Si hace falta vaciar o borrar filas (por ejemplo cuaderno de comunicados), el agente entrega **únicamente** las sentencias `DELETE`/`UPDATE`/`INSERT` (o `TRUNCATE` si aplica) en el chat, con **advertencia de alcance** y orden sugerido por FKs, para que el humano las revise y ejecute en su cliente SQL o consola; si corresponde además archivos de migración o de código de aplicación, van como **archivos guardados en el repo**, sin ejecutarlos desde la herramienta para producir el cambio en la BD. Si la única vía es un comando Artisan concreto, debe entregarse como **texto para copiar**, no invocarse desde la herramienta. Las migraciones del proyecto siguen siendo **solo aditivas** respecto del modelo legacy salvo decisión documentada fuera de este flujo.

**Nota:** las reglas del proyecto orientan al modelo, pero **no lo garantizan al 100%**; conviene revisar en el chat si el agente propone ejecutar algo contra la BD antes de aceptar herramientas de terminal.

**Colaboradores:** no hace falta replicar políticas en “User rules” de Cursor. Con clonar el repo alcanza para tener en el contexto del agente: **`AGENTS.md`** en la raíz del repositorio, **`sistema/AGENTS.md`**, esta sección y los archivos en **`sistema/.cursor/rules/`** (versionados). Quien use otra herramienta debe igualmente respetar lo documentado aquí en revisiones de PR.

### 9.1 Entregable al cerrar cambios que tocan la base de datos

Cuando un cambio en el repositorio suponga **alteración de esquema** o **corrección / carga de datos** (migración nueva, script documentado, instrucciones de backfill, etc.), quien implemente (incluidas herramientas de IA) debe dejar **al final** de la respuesta o descripción del PR:

- El **SQL ejecutable** equivalente a lo que hace la migración en `up()` (o el script), con comentarios mínimos de alcance; y  
- Si el cambio **no es reversible** por fila, indicarlo (no basta con un `down()` vacío en código).

Objetivo: el operador humano puede revisar en el cliente SQL, ejecutar en el orden correcto y archivar el texto sin depender solo del diff de PHP.

**Alternativa aceptable:** indicar explícitamente que basta con `php artisan migrate` (u otro comando Artisan **concreto**) en un entorno donde ya estén versionadas las migraciones, siempre que el efecto sea el mismo que el SQL entregado. El asistente **no** debe invocar ese comando contra la BD del proyecto desde su terminal.
