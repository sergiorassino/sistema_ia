#!/usr/bin/env bash
# Informe de requisitos del VPS de emergencia (DirectAdmin / AlmaLinux).
# No cambia nada. Pegá la salida si algo falla.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"
set +e
set -u

ok() { echo "  OK   $*"; }
fail() { echo "  FAIL $*"; }
info() { echo "  INFO $*"; }

echo "=== SE emergencia — diagnóstico ==="
echo "fecha (TZ=$TZ): $(date -Iseconds 2>/dev/null || date)"
echo "host:  $(hostname)  user: $(whoami)"
echo "pwd:   $(pwd)"
echo

echo "--- PHP instalados ---"
shopt -s nullglob
phps=(/usr/local/php*/bin/php /usr/bin/php)
shopt -u nullglob
if [[ ${#phps[@]} -eq 0 ]]; then
    fail "no hay /usr/local/php*/bin/php ni /usr/bin/php"
fi

exts=(pdo_mysql mbstring xml curl zip tokenizer fileinfo ctype json gd)
for bin in "${phps[@]}"; do
    [[ -x "$bin" ]] || continue
    ver="$("$bin" -r 'echo PHP_VERSION;' 2>/dev/null || echo "?")"
    ini="$("$bin" -r 'echo php_ini_loaded_file() ?: "(ninguno)";' 2>/dev/null || echo "?")"
    echo "BIN $bin"
    echo "    version=$ver"
    echo "    ini=$ini"
    for ext in "${exts[@]}"; do
        if "$bin" -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null; then
            ok "$ext"
        else
            fail "$ext no cargada"
        fi
    done
    warns="$("$bin" -r 'echo 1;' 2>&1 >/dev/null | grep -c 'Unable to load dynamic library' || true)"
    if [[ "${warns:-0}" -gt 0 ]]; then
        fail "$warns warning(s) Unable to load dynamic library — sacá extension=... del ini CLI"
        "$bin" -r 'echo 1;' 2>&1 >/dev/null | grep 'Unable to load dynamic library' | head -n 8 | sed 's/^/    /'
        if [[ -n "$ini" && "$ini" != "(ninguno)" && -f "$ini" ]]; then
            echo "    líneas extension= en $ini:"
            grep -nE '^[[:space:]]*extension=' "$ini" | sed 's/^/    /' || true
        fi
    fi
    echo
done

echo "--- PATH php / composer / git / aws / mysql / flock ---"
for cmd in php composer git aws mysql flock gzip; do
    p="$(command -v "$cmd" 2>/dev/null || true)"
    if [[ -n "$p" ]]; then
        ok "$cmd → $p"
    else
        fail "$cmd no está en PATH"
    fi
done
echo

echo "--- config.env (por colegio) ---"
if [[ -n "${SE_EMERGENCIA_CONF:-}" && -f "${SE_EMERGENCIA_CONF}" ]]; then
    ok "SE_EMERGENCIA_CONF=$SE_EMERGENCIA_CONF"
elif [[ -f "$SCRIPT_DIR/config.env" ]]; then
    ok "$SCRIPT_DIR/config.env"
else
    fail "falta $SCRIPT_DIR/config.env — copiá config.example.env → config.env"
    echo "=== fin (sin config) ==="
    exit 0
fi

cargar_config
if [[ -f "$APP_DIR/.env" ]]; then
    # shellcheck disable=SC2034
    if aplicar_env_colegio "$APP_DIR/.env" 2>/dev/null; then
        :
    fi
    # AWS_BIN de Hostinger solo si este .env es de producción (no en el VPS).
    hab="$(env_valor "$APP_DIR/.env" HABILITAR_RESPALDO 0)"
    if [[ "$hab" == "1" ]]; then
        aplicar_env_hosting_produccion "$APP_DIR/.env" 2>/dev/null || true
    else
        inferir_aws_bin
    fi
fi
prepend_php_path
echo "    APP_DIR=$APP_DIR"
echo "    PHP_BIN=${PHP_BIN:-php}"
echo "    WEB_USER=${WEB_USER:-}  WEB_GROUP=${WEB_GROUP:-}"
echo "    GIT_URL=${GIT_URL:-}  GIT_BRANCH=${GIT_BRANCH:-}"
echo "    S3_BUCKET=$S3_BUCKET"
echo "    AWS_BIN=${AWS_BIN:-"(PATH / inferido)"}"
echo "    HABILITAR_RESPALDO=${HABILITAR_RESPALDO:-0}"
echo "    S3_PREFIX_DUMPS=$S3_PREFIX_DUMPS"
echo "    S3_PREFIX_ARCHIVOS=$S3_PREFIX_ARCHIVOS"
PROYECTO="${PROYECTO:-$(basename "$APP_DIR")}"
echo

echo "--- .env del colegio ---"
if [[ -f "$APP_DIR/.env" ]]; then
    ok "$APP_DIR/.env"
    echo "    HABILITAR_RESPALDO=$(env_valor "$APP_DIR/.env" HABILITAR_RESPALDO 0)"
    echo "    AWS_BIN=$(env_valor "$APP_DIR/.env" AWS_BIN 0)"
    echo "    TENANT_SLUG=$(env_valor "$APP_DIR/.env" TENANT_SLUG 0)"
    echo "    LAB_ORDEN=$(env_valor "$APP_DIR/.env" LAB_ORDEN 0)"
    echo "    EMERGENCIA_APP_URL=$(env_valor "$APP_DIR/.env" EMERGENCIA_APP_URL 0)"
    echo "    EMERGENCIA_DB_HOST=$(env_valor "$APP_DIR/.env" EMERGENCIA_DB_HOST 0)"
    echo "    EMERGENCIA_DB_DATABASE=$(env_valor "$APP_DIR/.env" EMERGENCIA_DB_DATABASE 0)"
    echo "    EMERGENCIA_DB_USERNAME=$(env_valor "$APP_DIR/.env" EMERGENCIA_DB_USERNAME 0)"
    epass="$(env_valor "$APP_DIR/.env" EMERGENCIA_DB_PASSWORD 0)"
    if [[ -z "$epass" || "$epass" == "__CAMBIAR__" ]]; then
        fail "EMERGENCIA_DB_PASSWORD vacío"
    else
        ok "EMERGENCIA_DB_PASSWORD definido (no se muestra)"
        MYSQL_PASSWORD="$epass"
    fi
    slug="$(env_valor "$APP_DIR/.env" TENANT_SLUG 0)"
    [[ -n "$slug" ]] && PROYECTO="$slug"
    orden="$(env_valor "$APP_DIR/.env" LAB_ORDEN 0)"
    [[ -n "$orden" ]] && S3_DUMP_FILTER="l${orden}_${PROYECTO}"
    edb="$(env_valor "$APP_DIR/.env" EMERGENCIA_DB_DATABASE 0)"
    euser="$(env_valor "$APP_DIR/.env" EMERGENCIA_DB_USERNAME 0)"
    ehost="$(env_valor "$APP_DIR/.env" EMERGENCIA_DB_HOST 0)"
    [[ -n "$edb" ]] && MYSQL_DATABASE="$edb"
    [[ -n "$euser" ]] && MYSQL_USER="$euser"
    [[ -n "$ehost" ]] && MYSQL_HOST="$ehost"
    MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
else
    fail "no hay .env (restaurar.sh lo baja de S3)"
fi
echo

echo "--- overlay legado (opcional) ---"
overlay="$(ruta_overlay)"
if [[ -f "$overlay" ]]; then
    info "existe $overlay (solo se usa si el .env no tiene EMERGENCIA_APP_URL)"
else
    ok "sin overlay legado (correcto si usás el bloque .env)"
fi
echo

echo "--- proyecto ---"
if [[ -d "$APP_DIR" ]]; then
    ok "APP_DIR existe"
    [[ -f "$APP_DIR/artisan" ]] && ok "artisan" || fail "falta artisan"
    [[ -d "$APP_DIR/.git" ]] && ok ".git" || fail "no es un clone git"
    [[ -d "$APP_DIR/vendor" ]] && ok "vendor/" || fail "falta vendor (preparar-vps / composer install)"
    ht="$APP_DIR/.htaccess"
    if [[ -f "$ht" ]]; then
        base="/ia/${PROYECTO}"
        if grep -qE "^[[:space:]]*RewriteBase[[:space:]]+${base}/?" "$ht"; then
            ok "RewriteBase ${base}"
        else
            fail "descomentá RewriteBase ${base}/ en $ht"
        fi
    else
        fail "falta $ht"
    fi
else
    fail "APP_DIR no existe: $APP_DIR"
fi
echo

echo "--- PHP_BIN del config ---"
php_bin="${PHP_BIN:-php}"
if command -v "$php_bin" >/dev/null 2>&1; then
    if php_ext_cargada "$php_bin" pdo_mysql && php_ext_cargada "$php_bin" mbstring && php_ext_cargada "$php_bin" curl; then
        ok "$php_bin carga pdo_mysql + mbstring + curl"
    else
        fail "$php_bin no carga las extensiones de Laravel. Este es el bloqueo de preparar-vps.sh."
    fi
else
    fail "PHP_BIN no ejecutable: $php_bin"
fi
echo

echo "--- AWS ---"
aws_ok=0
if [[ -n "${AWS_BIN:-}" ]]; then
    if [[ -x "$AWS_BIN" ]]; then
        aws_ok=1
        ok "AWS_BIN=$AWS_BIN"
    else
        fail "AWS_BIN no ejecutable: $AWS_BIN"
    fi
elif type -P aws >/dev/null 2>&1; then
    aws_ok=1
    ok "aws en PATH"
else
    fail "aws no instalado (AWS_BIN o PATH)"
fi
if [[ "$aws_ok" == "1" ]]; then
    if aws sts get-caller-identity >/dev/null 2>&1; then
        ok "aws autenticado"
        aws s3 ls "s3://${S3_BUCKET}/${S3_PREFIX_ARCHIVOS%/}/${PROYECTO}/" 2>&1 | head -n 5 | sed 's/^/    /' || fail "no listó archivos del proyecto"
        aws s3 ls "s3://${S3_BUCKET}/${S3_PREFIX_DUMPS%/}/" 2>&1 | grep -F "$S3_DUMP_FILTER" | tail -n 3 | sed 's/^/    /' || fail "ningún dump con S3_DUMP_FILTER=$S3_DUMP_FILTER"
    else
        fail "aws sts get-caller-identity falló (credenciales)"
    fi
fi
echo

echo "--- MySQL local ---"
if command -v mysql >/dev/null 2>&1; then
    if [[ "${MYSQL_PASSWORD:-}" != "__CAMBIAR__" && -n "${MYSQL_PASSWORD:-}" ]]; then
        cnf="$(mysql_cnf_tmp)"
        if mysql --defaults-extra-file="$cnf" -e "SELECT 1;" >/dev/null 2>&1; then
            ok "conexión MySQL $MYSQL_USER@$MYSQL_HOST"
            mysql --defaults-extra-file="$cnf" -e "SHOW DATABASES LIKE '${MYSQL_DATABASE}';" 2>/dev/null | sed 's/^/    /'
        else
            fail "no conecta con EMERGENCIA_DB_* del .env (creá usuario/base en DirectAdmin)"
        fi
        rm -f "$cnf"
    else
        info "saltado: MYSQL_PASSWORD placeholder"
    fi
else
    fail "no está el cliente mysql"
fi

echo
echo "--- Restaurar desde el celular ---"
web="${HABILITAR_RESTAURAR_WEB:-}"
tok="${RESTAURAR_WEB_TOKEN:-}"
whost="${RESTAURAR_WEB_HOST:-}"
ex="$SCRIPT_DIR/config.example.env"
if [[ "$web" != "1" && -f "$ex" ]]; then
    web="$(grep -E '^[[:space:]]*HABILITAR_RESTAURAR_WEB=' "$ex" | tail -n 1 | cut -d= -f2- || true)"
    [[ -z "$tok" ]] && tok="$(grep -E '^[[:space:]]*RESTAURAR_WEB_TOKEN=' "$ex" | tail -n 1 | cut -d= -f2- || true)"
    [[ -z "$whost" ]] && whost="$(grep -E '^[[:space:]]*RESTAURAR_WEB_HOST=' "$ex" | tail -n 1 | cut -d= -f2- || true)"
    info "este config.env no tiene las claves web; se usan las de la plantilla"
fi
if [[ "$web" == "1" ]]; then
    if [[ ${#tok} -ge 16 ]]; then
        ok "HABILITAR_RESTAURAR_WEB=1 (token definido)"
    else
        fail "HABILITAR_RESTAURAR_WEB=1 pero RESTAURAR_WEB_TOKEN corto o vacío"
    fi
    echo "    host permitido: ${whost:-'(falta RESTAURAR_WEB_HOST)'}"
    echo "    página: public/emergencia-restaurar.php"
else
    info "página web de restauración apagada (normal en producción; en el VPS ver docs/10 §4.1)"
fi

echo
echo "=== fin ==="
echo "Si PHP_BIN tiene FAIL de extensiones: docs/10-backup-y-vps-emergencia.md §3.1 (DirectAdmin)."
