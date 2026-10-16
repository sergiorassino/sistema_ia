# Backup de archivos y VPS de emergencia

Misma rutina que Silavet (`SILAVET/docs/13-backup-y-vps-emergencia.md`), adaptada
a **un colegio = un despliegue**. El VPS de reserva es el mismo DirectAdmin
(`sistemasescolares4.com` / `sistemasescolares4.com.ar`). Archivos y dumps van
a las mismas carpetas S3 que los laboratorios. El dump del colegio empieza
con `e`; el del laboratorio, con `l`.

Cómo dejar un colegio de reserva que se levanta **a demanda** (última hora),
sin que el VPS de emergencia se actualice cada hora.

Piezas:

| Pieza | Dónde | Qué cubre |
|-------|--------|-----------|
| Código | Git (`main`, o `sergio` si así se despliega) | Laravel, vistas, `public/build/` |
| Base de datos | S3 `backupDedicado/backupHora` | Dump MySQL horario |
| Archivos del colegio | S3 espejo incremental | Lo que no está en git |

El VPS de emergencia queda **preparado** (stack + clone + Composer + overlay de
`.env`). **No** hay cron de sync ahí. Cuando cae producción se corre un solo
comando (SSH) o, si está el Paso H, un botón en el celular.

---

## 1. Qué se sube a S3 (archivos)

Prefijo: `s3://sistesco/backupDedicado/archivos/{TENANT_SLUG}/`

| Ruta | Contenido |
|------|-----------|
| `.env` | Claves de la app (el bucket ya es sensible por los dumps) |
| `public/archivos/` | Programas y planificaciones |
| `public/storage/` | Logos y demás del disco `public` |
| `storage/app/public/` | Logos que quedaron en la ruta legacy |
| `storage/app/private/` | Documentos de estudiantes, fotos carnet, comunicaciones, capacitación, adjuntos de mail |
| `storage/fonts/` | Arial y Verdana (PDF TCPDF) |
| `storage/certs/` | `cacert.pem`, si se cargó |
| `afipSE/cert/` | Certificados ARCA/AFIP |
| `public/img/firmaRepLegal.jpg` | Firma del representante legal, si está en el servidor |

No se sube `vendor/`, logs, cache, `livewire-tmp` ni el árbol git. El sync **no**
usa `--delete`: un archivo borrado en disco no se borra en S3.

Dump horario (misma rutina): `e{LAB_ORDEN}_{TENANT_SLUG}_YYYY_MM_DD_HH_MM_SS.sql.gz`
en `s3://sistesco/backupDedicado/backupHora`.
Ejemplo: `e1_montecristo_2026_10_16_21_30_02.sql.gz`. La **e** es de escuela.
`LAB_ORDEN` es el número que sigue (`e1_`, `e2_`, …). La marca de
tiempo del nombre es **hora argentina** (`America/Argentina/Buenos_Aires`), no
la del reloj del servidor (suele ser UTC). Override opcional: `SE_TZ` en el
`.env`. `LAB_ORDEN` y el resto de la emergencia van en el **`.env` del colegio**
(no en `config.env`). La BD que se dumpuea es `DB_*` de producción.
`EMERGENCIA_DB_*` es el MySQL **local del VPS de reserva** (se usa al restaurar).

Los dumps de Silavet están en la misma carpeta. El de un colegio se reconoce
porque el archivo empieza con `e` (`e1_montecristo_…`). El de un laboratorio
empieza con `l`.

---

## 2. Producción — una rutina por colegio (`respaldar.sh`)

Un job por colegio, un `flock` por colegio. Cada colegio se configura y se
respalda por separado.

Requisitos: AWS CLI, `flock`, `mysqldump`, `gzip`, el mismo usuario/IAM que ya
sube los dumps de Silavet. `HABILITAR_RESPALDO=1` y, si hace falta, `AWS_BIN`
van en el **`.env` del colegio** (solo producción; al restaurar en el VPS el
overlay pone `HABILITAR_RESPALDO=0`).

### 2.1 Hosting compartido (`aws: command not found`)

En Hostinger y similares **no** viene AWS CLI. Instalarlo en el home, sin root:

```bash
cd ~
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o awscliv2.zip
unzip -q awscliv2.zip
./aws/install -i "$HOME/.local/aws-cli" -b "$HOME/.local/bin"
"$HOME/.local/bin/aws" --version
```

Si `unzip` o el instalador fallan, alternativa:

```bash
pip3 install --user awscli
~/.local/bin/aws --version
```

Credenciales (las mismas del backup de Silavet, bucket `sistesco`):

```bash
mkdir -p ~/.aws
nano ~/.aws/credentials
nano ~/.aws/config
```

```ini
# ~/.aws/credentials
[default]
aws_access_key_id=...
aws_secret_access_key=...

# ~/.aws/config
[default]
region=sa-east-1
```

Probar:

```bash
export PATH="$HOME/.local/bin:$PATH"
aws sts get-caller-identity
aws s3 ls s3://sistesco/
```

En el **`.env` de ese colegio** (no en `config.env`):

```env
HABILITAR_RESPALDO=1
AWS_BIN=/home/u577894275/.local/bin/aws
```

(ajustá el path; `echo $HOME` + `/.local/bin/aws`). Si `AWS_BIN` está vacío,
los scripts intentan `~/.local/bin/aws` solos. **`AWS_BIN` solo lo usa
`respaldar.sh`**: en el VPS, `restaurar.sh` ignora esa ruta (usa el `aws` del
VPS) para no romper el paso del dump.

---

```bash
cd /ruta/al/colegio
cp scripts/emergencia/config.example.env scripts/emergencia/config.env
chmod 600 scripts/emergencia/config.env
# config.env queda igual en todos los colegios (defaults S3 / PHP / git). No pongas AWS_BIN ahí.
```

En el `.env` Laravel del mismo colegio (ver también `.env.example`):

```env
HABILITAR_RESPALDO=1
AWS_BIN=/home/USUARIO/.local/bin/aws
TENANT_SLUG=montecristo
LAB_ORDEN=1
EMERGENCIA_APP_URL=https://sistemasescolares4.com/ia/montecristo
EMERGENCIA_DB_HOST=127.0.0.1
EMERGENCIA_DB_PORT=3306
EMERGENCIA_DB_DATABASE=admin_montecristo
EMERGENCIA_DB_USERNAME=admin_montecristo
EMERGENCIA_DB_PASSWORD=…
```

La carpeta del colegio debe llamarse igual que `TENANT_SLUG`
(`…/ia/montecristo`). El filtro del dump es `e{LAB_ORDEN}_{TENANT_SLUG}`.
Con slugs distintos, `LAB_ORDEN=1` en todos no choca.

Probar (en el hosting del colegio, no en el VPS de reserva):

```bash
bash /ruta/al/colegio/scripts/emergencia/respaldar.sh --dry-run
bash /ruta/al/colegio/scripts/emergencia/respaldar.sh
aws s3 ls s3://sistesco/backupDedicado/backupHora/ | grep e1_montecristo | tail
```

Cron **por colegio** (ejemplo montecristo; desplazá el minuto si varios
colegios comparten el mismo servidor):

```cron
5 * * * * /bin/bash /ruta/a/ia/montecristo/scripts/emergencia/respaldar.sh >> $HOME/se-respaldo-montecristo.log 2>&1
```

Otro colegio = otra carpeta, mismo `config.env` impersonal, **otro** `.env` y
otro cron a **su** `respaldar.sh`. **El mismo día:** borrar el cron viejo de
dumps, si existía. `respaldar-archivos.sh` reenvía a `respaldar.sh`.

---

## 3. VPS de emergencia — preparación (una vez por colegio)

Este VPS de reserva es **DirectAdmin + AlmaLinux**
(`sistemasescolares4.com.ar`), el mismo de Silavet. No uses `/var/www` ni
`www-data`: el sitio vive en el `public_html` del usuario del panel. El
recetario detallado es el **§3.1**.

Resumen: clone en carpeta `ia/{TENANT_SLUG}` → PHP CLI del panel →
`scripts/emergencia/config.env` de ese colegio → Composer → **sin cron de
sync**. Los datos y el overlay salen del `.env` de producción (S3).

Diagnóstico (no cambia nada; pegá la salida si falla):

```bash
cd /home/admin/public_html/ia/montecristo
bash scripts/emergencia/diagnostico.sh
```

Los locks usan el prefijo `/tmp/se-…` para no pisar los `/tmp/silavet-…` de
los laboratorios que ya corren en este VPS.

---

## 3.1 DirectAdmin (sistemasescolares4.com.ar) — recetario

Trabajá por **SSH como `admin`**, salvo los pasos marcados **(root)**.
No ejecutes `preparar-vps.sh` con `php`: es un script **bash**.

URL de emergencia:

`https://sistemasescolares4.com/ia/montecristo`

Carpeta Laravel (`APP_DIR`, donde está `artisan`):

`/home/admin/public_html/ia/montecristo`

Si Silavet ya dejó PHP, Composer, Git y AWS CLI en este VPS, los pasos A y B
están hechos: seguí desde el Paso C con la base de **este** colegio.

### Paso A — El PHP de SSH no es el de los sitios

En DirectAdmin hay **varios PHP**. El `php` de AlmaLinux (PATH) suele no tener
MySQL. Los sitios usan `/usr/local/php82/bin/php` o `/usr/local/php83/bin/php`.

El error que bloquea `preparar-vps.sh` es este:

```
PHP Warning: Unable to load dynamic library 'pdo_mysql' ... no-debug-non-zts-.../pdo_mysql.so
ERROR: Falta la extensión PHP: pdo_mysql
```

**Qué significa:** el `php.ini` del CLI pide `extension=pdo_mysql` (y curl, gd,
mbstring, zip, …) como `.so`. En DirectAdmin esas extensiones **ya van
compiladas dentro de PHP**. No hay archivo `.so`. Poner `extension=...` en el
ini **no las activa**: solo tira el warning. `php -m | grep pdo_mysql` miente
porque coincide con el texto del warning.

Comprobación que sí vale (tiene que decir `yes`):

```bash
ls -d /usr/local/php*/bin/php
/usr/local/php83/bin/php -r 'echo extension_loaded("pdo_mysql") ? "yes" : "no", "\n";' 2>/dev/null
/usr/local/php82/bin/php -r 'echo extension_loaded("pdo_mysql") ? "yes" : "no", "\n";' 2>/dev/null
/usr/local/php83/bin/php --ini
```

#### A1. Si algún binario dice `yes`

Ese es tu `PHP_BIN`. En `scripts/emergencia/config.env` de **este** colegio en el
VPS:

```bash
PHP_BIN=/usr/local/php83/bin/php
```

(ajustá `83` / `82` al que dijo `yes`). En DirectAdmin → **Account Manager →
PHP Settings** del dominio, la versión del sitio debe ser **la misma**.

#### A2. Si todos dicen `no` y ves `Unable to load dynamic library`

**(root)** El ini CLI tiene líneas `extension=` de más. Localizá el archivo:

```bash
/usr/local/php83/bin/php --ini
```

Suele ser `/usr/local/php83/lib/php.ini`. Comentá (poné `;` adelante) **todas**
estas si existen:

```ini
;extension=curl
;extension=gd
;extension=mbstring
;extension=mysqli
;extension=pdo_mysql
;extension=zip
```

También revisá `/usr/local/php83/lib/php.conf.d/*.ini` por las mismas líneas.
Volvé a correr el `extension_loaded("pdo_mysql")`. Si ahora dice `yes`, seguí.

#### A3. Si después de comentar el ini sigue en `no`

Las extensiones **no están compiladas** en ese PHP. **(root)** rebuild con
CustomBuild (varios minutos; no corta los sitios que usan otro PHP):

```bash
cd /usr/local/directadmin/custombuild
./build update
./build php n
```

Después, otra vez `extension_loaded("pdo_mysql")` → `yes`.

### Paso B — Composer, Git, AWS CLI (como `admin`)

```bash
export PATH="/usr/local/php83/bin:$PATH"
php -v
php -r 'echo extension_loaded("pdo_mysql") ? "pdo_mysql yes\n" : "pdo_mysql NO\n";'

command -v git || echo "instalar git (root: dnf install git)"
command -v composer || curl -sS https://getcomposer.org/installer | php -- --install-dir="$HOME/bin" --filename=composer
export PATH="$HOME/bin:$PATH"
composer -V

command -v aws || echo "falta AWS CLI — igual que en producción, o §2.1"
aws sts get-caller-identity
aws s3 ls s3://sistesco/backupDedicado/archivos/montecristo/
aws s3 ls s3://sistesco/backupDedicado/backupHora/ | grep e1_montecristo | tail
```

Si `composer` queda en `$HOME/bin`, ese directorio tiene que estar en PATH
cuando corras `preparar-vps.sh` (o agregalo en `~/.bashrc`).

### Paso C — Base MySQL local (panel DirectAdmin)

`restaurar.sh` importa el dump en **este** MySQL, no en el de Hostinger.
Una base por colegio, distinta de las de Silavet.

1. DirectAdmin → **MySQL Management** → Create Database.
2. Anotá **exactamente** los nombres que genera el panel. DirectAdmin suele
   prefijar: `admin_montecristo` / usuario `admin_montecristo`.
3. Esos tres valores van en el `.env` de **producción** (`EMERGENCIA_DB_*`),
   no en un archivo del VPS. Host: `127.0.0.1`. El dump todavía no se importa.

Prueba:

```bash
mysql -h 127.0.0.1 -u ADMIN_USUARIO -p -e "SHOW DATABASES;"
```

### Paso D — `scripts/emergencia/config.env` (por colegio)

En el VPS, **un** `config.env` dentro de la carpeta de **este** colegio (no se
comparte con otros tenants ni con Silavet):

```bash
cd /home/admin/public_html/ia/montecristo
cp scripts/emergencia/config.example.env scripts/emergencia/config.env
chmod 600 scripts/emergencia/config.env
nano scripts/emergencia/config.env
```

```bash
WEB_USER=admin
WEB_GROUP=admin
GIT_URL=https://github.com/sergiorassino/sistema_ia.git
GIT_BRANCH=main
PHP_BIN=/usr/local/php83/bin/php
S3_BUCKET=sistesco
S3_PREFIX_DUMPS=backupDedicado/backupHora
S3_PREFIX_ARCHIVOS=backupDedicado/archivos
AWS_REGION=sa-east-1
HABILITAR_RESTAURAR_WEB=1
RESTAURAR_WEB_TOKEN=…   # ya viene en la plantilla; no hace falta openssl
RESTAURAR_WEB_HOST=sistemasescolares4.com,sistemasescolares4.com.ar
```

Si el código que está en producción es la rama `sergio` y todavía no está
mergeada en `main`, poné `GIT_BRANCH=sergio`. Si no, el VPS va a levantar
`main`.

`HABILITAR_RESPALDO`, `AWS_BIN`, `LAB_ORDEN`, `TENANT_SLUG` y MySQL de
emergencia van en el `.env` (producción; `restaurar.sh` baja el `.env` y pone
`HABILITAR_RESPALDO=0`). La carpeta del clone tiene que llamarse como el tenant:
`/home/admin/public_html/ia/montecristo`.

Los scripts **solo** leen `scripts/emergencia/config.env` de esa carpeta (o
`SE_EMERGENCIA_CONF`). No leen un config global.

### Paso E — RewriteBase (subcarpeta)

```bash
nano /home/admin/public_html/ia/montecristo/.htaccess
```

Descomentá y dejá:

```apache
RewriteBase /ia/montecristo/
```

El document root de DirectAdmin sigue siendo `public_html` del dominio. Laravel
está en la subcarpeta; el `.htaccess` de la raíz del colegio reescribe a `public/`
([09-despliegue-sin-public-en-url.md](09-despliegue-sin-public-en-url.md)).
Ese `.htaccess` niega el acceso web a `scripts/` (ahí vive `config.env`).

En el panel: PHP del dominio = la misma versión que `PHP_BIN`. `mod_rewrite`
activo (lo está por defecto).

### Paso F — preparar (todavía sin bajar S3 ni importar BD)

```bash
export PATH="/usr/local/php83/bin:$HOME/bin:$PATH"
cd /home/admin/public_html/ia/montecristo
bash scripts/emergencia/diagnostico.sh
bash scripts/emergencia/preparar-vps.sh
```

Listo si termina sin `ERROR` (puede avisar AWS o placeholders). Crea `vendor/`,
carpetas del colegio y permisos como `admin`. **No** pongas cron de sync en
este VPS.

Si el clone aún no existía, `preparar-vps.sh` clona `GIT_URL` en `APP_DIR`.
Si `APP_DIR` ya tiene archivos y no es un git, el script se niega: o vaciá la
carpeta o cloná a mano antes.

### Paso G — Ensayo de restauración (cuando A–F están OK)

```bash
bash scripts/emergencia/restaurar.sh --dry-run
# si el dry-run lista dump y archivos:
bash scripts/emergencia/restaurar.sh --yes
```

Esto **sí** pisa la base local y los archivos del colegio. No toca Hostinger.
Para probar sin cambiar el DNS de producción: el dominio ya apunta a este VPS,
o usá `/etc/hosts` en tu PC.

### Paso H — Página para restaurar desde el celular

`restaurar.sh` sigue siendo bash. El PHP del VPS lo dispara en segundo plano.

La clave, el host y el flag **ya están en** `config.example.env`. Al crear un
colegio (`cp …/config.example.env …/config.env`) quedan en ese `config.env`. No
hace falta `openssl` ni editar nada más. Colegios viejos: `preparar-vps.sh` copia
las claves que falten; o un `git pull` alcanza (el PHP usa la plantilla si
`config.env` aún no las tiene).

URL (acceso directo en el celular; cambiá el slug):

`https://sistemasescolares4.com/ia/montecristo/emergencia-restaurar.php`

La clave es `RESTAURAR_WEB_TOKEN` de la plantilla (la misma en todos los
colegios de este sistema, distinta de la de Silavet). En el hosting de
producción la página responde **404** (`RESTAURAR_WEB_HOST` no coincide y
además `HABILITAR_RESPALDO=1`).

DirectAdmin suele **cortar** `exec()` / `proc_open` en PHP-FPM: la página entra
pero no lanza el `.sh`. Dos formas (con una alcanza):

**A — Habilitar exec (queda al toque)**  
DirectAdmin (usuario `admin`) → **PHP Settings** del dominio → en
`disable_functions` borrá `exec`, `shell_exec`, `passthru`, `system`,
`proc_open` y `popen` → guardar y reiniciar PHP. Por SSH (root), el ini
CLI/FPM suele ser `/usr/local/php83/lib/php.ini`; después
`systemctl restart php-fpm83`.

**B — Cron de cola (1 minuto, no es sync horario)**  
Si no querés tocar el ini, un cron **por colegio** que solo mira si hay pedido:

```cron
* * * * * /bin/bash /home/admin/public_html/ia/montecristo/scripts/emergencia/web-cola.sh
```

(DirectAdmin → Cron Jobs). El botón de la web deja un archivo en
`storage/logs/restaurar-web.pedido`; el cron corre `restaurar.sh` a lo sumo
un minuto después.

Ensayo: abrí la URL, clave, **Simular**. Tiene que aparecer dump y archivos en
el log, sin importar BD.

---

## 4. Cuando cae producción

### 4.1 Desde el celular (si hiciste el Paso H)

1. Abrí la URL de `emergencia-restaurar.php` de **ese** colegio.
2. Ingresá la clave (`RESTAURAR_WEB_TOKEN`).
3. Marcá la casilla y **Restaurar ahora**.
4. Dejá la pantalla abierta. El log se actualiza solo. Listo cuando dice
   `Restauración lista.`

Otro colegio = otra carpeta = otra URL
(`…/ia/{slug}/emergencia-restaurar.php`). Producción no se toca: solo pisa el
VPS de reserva.

Si la página da 404: faltó el `git pull` del PHP, o entraste por el host de
producción. En ese caso usá SSH (§4.2).

### 4.2 Por SSH

En el VPS de emergencia (DirectAdmin, como `admin`):

```bash
export PATH="/usr/local/php83/bin:$HOME/bin:$PATH"
cd /home/admin/public_html/ia/montecristo
bash scripts/emergencia/restaurar.sh
# o sin pregunta:
bash scripts/emergencia/restaurar.sh --yes
```

Hace, en orden: `git pull` → `composer install --no-dev` → `s3 sync` de
archivos → aplica `EMERGENCIA_*` del `.env` sobre `APP_URL`/`DB_*` → importa
el dump más reciente `e{LAB_ORDEN}_{TENANT_SLUG}_…` → caches Artisan y
`php artisan se:migrate-legacy --force` (no uses `migrate` a secas: en una base
legacy recrearía el esquema).

Tiempo: minutos de git/composer/archivos + lo que tarde el import MySQL
(eso suele ser lo lento). El disco `public` de este sistema es la carpeta
`public/storage` (no hace falta `storage:link` si esa carpeta vino del espejo).

### Collations Hostinger → DirectAdmin

Los dumps de producción (MariaDB 10.10+/11 en Hostinger) pueden traer
`utf8mb4_uca1400_ai_ci` (y variantes). El MySQL/MariaDB del VPS de emergencia
(DirectAdmin, a menudo 10.5/10.6) **no** las conoce →
`ERROR 1273 (HY000): Unknown collation: 'utf8mb4_uca1400_ai_ci'`.

`restaurar.sh` reescribe esas collations a `utf8mb4_unicode_ci` (y equivalentes
utf8/utf8mb3) al importar. No hace falta editar el `.sql.gz` a mano.

Si un import falló a mitad antes de ese fix: volvé a correr
`restaurar.sh --yes` (el script vuelve a crear la base vacía y reimporta).

Después: apuntar DNS o usar la URL de emergencia. El colegio queda con datos
de **como máximo ~1 hora** de atraso (el último dump + el espejo de archivos
de ese mismo ciclo).

---

## 5. Scripts

| Script | Servidor | Frecuencia |
|--------|----------|------------|
| `scripts/emergencia/respaldar.sh` | Producción | Horario (un cron por colegio) |
| `scripts/emergencia/respaldar-archivos.sh` | Producción | Alias de `respaldar.sh` |
| `scripts/emergencia/diagnostico.sh` | Emergencia | Cuando algo falla |
| `scripts/emergencia/preparar-vps.sh` | Emergencia | Una vez por carpeta/colegio |
| `scripts/emergencia/restaurar.sh` | Emergencia | Solo ante caída (o ensayo) |
| `public/emergencia-restaurar.php` | Emergencia | Opcional: dispara `restaurar.sh` desde el celular |
| `scripts/emergencia/web-cola.sh` | Emergencia | Si PHP no tiene exec: cron 1 min por colegio |

Config **por colegio** (impersonal): `scripts/emergencia/config.env` ←
`config.example.env`.  
Personalización: `HABILITAR_RESPALDO`, `AWS_BIN`, `LAB_ORDEN`, `EMERGENCIA_*` en
el `.env`.  
Página celular: `HABILITAR_RESTAURAR_WEB`, `RESTAURAR_WEB_TOKEN` y
`RESTAURAR_WEB_HOST` ya van en `config.example.env` y se copian a cada
`config.env` (Paso H).  
Override puntual: variable `SE_EMERGENCIA_CONF`.

---

## 6. Qué no hacer

- Cron horario en el VPS de emergencia (git pull, sync o import). El cron de
  1 minuto de `web-cola.sh` sí aplica (solo si PHP-FPM no puede hacer exec).
- Subir `vendor/` o todo el proyecto a S3.
- Restaurar el `.env` de producción sin overlay (`APP_URL` / `DB_HOST` del
  servidor caído).
- Cambiar el prefijo del dump: tiene que seguir siendo `e{LAB_ORDEN}_{slug}_`
  dentro de `backupDedicado/backupHora`, para distinguirlo de los `l1_…` de Silavet.
- En DirectAdmin: `extension=pdo_mysql` (u otras) en el `php.ini` CLI; ni
  `php scripts/emergencia/preparar-vps.sh` (es bash).
- Correr `preparar-vps.sh` como **root** (los archivos quedarían fuera del
  usuario `admin` y PHP-FPM no escribe `storage/`).
- `HABILITAR_RESPALDO=1` o cron de `respaldar.sh` en el VPS de emergencia.
- Poner `AWS_BIN` o paths de usuario en `config.env` (van en el `.env`).
- Dejar el cron viejo de dumps **y** `respaldar.sh` a la vez.
- Un cron que respalde varios colegios a la vez (cada uno tiene su propio
  `respaldar.sh`, `config.env` y cron).
- Generar un token distinto con `openssl` por cada colegio: la plantilla ya
  trae uno compartido de este sistema. Para rotarlo, cambiá
  `RESTAURAR_WEB_TOKEN` en `config.example.env` y en los `config.env` que ya
  existan.
- Reutilizar la clave de restauración de Silavet.
- Reescribir `restaurar.sh` en PHP: el PHP solo lo lanza en background con `--yes`.

---

## 7. Archivos de código

- `scripts/emergencia/lib.sh`
- `scripts/emergencia/diagnostico.sh`
- `scripts/emergencia/respaldar.sh`
- `scripts/emergencia/respaldar-archivos.sh`
- `scripts/emergencia/preparar-vps.sh`
- `scripts/emergencia/restaurar.sh`
- `scripts/emergencia/web-cola.sh`
- `public/emergencia-restaurar.php`
- `scripts/emergencia/config.example.env`
- `scripts/emergencia/env.emergencia.example`
- `.env.example` (bloque `HABILITAR_RESPALDO` / `EMERGENCIA_*`)
- `.htaccess` (niega `scripts/` por HTTP)
