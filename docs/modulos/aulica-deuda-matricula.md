# Módulo: Cliente Áulica (deuda)

## Propósito

Consumir la External API de Áulica para saber si un estudiante o su grupo familiar tienen deuda facturada. Lo usa el módulo **Libre Deuda** del Menú de Alumnos ([libre-deuda.md](libre-deuda.md)).

No usa las cuotas internas (`cuotasgeneradas`).

## Modalidades / variantes

Default off en `config/tenant.php` → `aulica_deuda.habilitado`. Montecristo lo activa.

Credenciales **solo** en `.env` (`AULICA_USERNAME`, `AULICA_PASSWORD`, `AULICA_CODIGO`). El `codigo` es el de la institución que entrega Áulica.

En Windows, si aparece `cURL error 60`, bajar [cacert.pem](https://curl.se/ca/cacert.pem) a `storage/certs/cacert.pem` (gitignored) y/o definir `AULICA_CA_BUNDLE` con la ruta absoluta. El cliente también usa ese archivo si existe, sin variable extra.

Ambientes (OpenAPI):

| | Auth | API |
|--|------|-----|
| Test | `https://pau-develop-authserver.aulicatest.com.ar` | `https://pau-develop-externalapi.aulicatest.com.ar` |
| Producción | `https://authserver.aulica.com.ar` | `https://externalapi.aulica.com.ar` |

## Flujo de la API

1. `POST /externalauth/authenticate` `{ username, password, codigo }` → `accessToken` (header `x-access-token`).
2. `POST /alumnos/ctacte/saldos` `{ TipoDoc: "DNI", NroDoc }` → array de `{ idPersona, saldo, nroDoc, nombre, apellido }`.
3. Si el DNI es de un **tutor**, Áulica también devuelve el saldo de los alumnos a cargo (hermanos).
4. 404 = persona no encontrada = sin deuda.

DNI del responsable familiar (en este orden): `dnitut`, `respAdmiDni`, `dnipad`, `dnimad`.

## Archivos clave

- `app/Support/Aulica/*`
- `config/services.php` (`aulica`)
- `config/tenant.php` / `config/tenants/montecristo.php` (`aulica_deuda`)
- Comando: `php artisan aulica:consultar-deuda {dni} --familia={dniTutor}`

## Qué no hacer

- No commitear usuario, clave ni código de institución.
- No consultar Áulica en cada render del sidebar.
- 404 no es error: el DNI no está en Áulica.
- No mezclar con Estado de Deuda Familiar/Estudiante (cuotas internas).
- No usar este cliente para bloquear ficha de matrícula ni actualización de datos.

## Prueba manual

1. Cargar `AULICA_*` en el `.env` de Montecristo (`TENANT_SLUG=montecristo`).
2. `php artisan config:clear`
3. `php artisan aulica:consultar-deuda {dniAlumno} --familia={dniTutor}`
4. En el portal familia: **Libre Deuda**.
