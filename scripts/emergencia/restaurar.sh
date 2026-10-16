#!/usr/bin/env bash
# VPS de emergencia: restauración a demanda (última hora).
# Colegio y MySQL de emergencia: bloque EMERGENCIA_* / LAB_ORDEN del .env (S3).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

uso() {
    cat <<'EOF'
Uso: restaurar.sh [--yes] [--dry-run]

En el VPS de emergencia (tras preparar-vps.sh):
  1. git pull
  2. composer install --no-dev
  3. baja el espejo de archivos desde S3 (carpeta = TENANT_SLUG del colegio)
  4. aplica EMERGENCIA_* del .env sobre APP_URL / DB_*
  5. importa el dump MySQL más reciente (e{LAB_ORDEN}_{tenant}_…)
  6. limpia caches Laravel y corre se:migrate-legacy --force

Opciones:
  --yes       no pide confirmación
  --dry-run   muestra qué haría (aws --dryrun; no importa BD)
EOF
}

SI=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes|--si|-y) SI=1 ;;
        --dry-run) DRY_RUN=1 ;;
        -h|--help) uso; exit 0 ;;
        *) die "Opción desconocida: $1" ;;
    esac
    shift
done

asegurar_claves_restaurar_web
cargar_config
prepend_php_path
PROYECTO="$(basename "$APP_DIR")"
log "PROYECTO (carpeta) = $PROYECTO"

require_vars PROYECTO APP_DIR S3_BUCKET S3_PREFIX_ARCHIVOS S3_PREFIX_DUMPS
require_vars GIT_BRANCH
require_aws
require_cmd mysql
require_cmd flock
php_ok

es_laravel_dir "$APP_DIR" || die "APP_DIR no parece Laravel: $APP_DIR"
[[ -d "$APP_DIR/.git" ]] || die "Falta el clone. Ejecutá primero preparar-vps.sh."

LOCK="/tmp/se-${PROYECTO}-restaurar.lock"
exec 9>"$LOCK"
flock -n 9 || die "Ya hay una restauración en curso."

confirmar "Esto PISA la base de emergencia y los archivos del colegio en ${APP_DIR} con el último dump y el espejo S3 (tenant ${PROYECTO})."

log "1/6 git pull"
git_actualizar "$APP_DIR"

log "2/6 composer install"
composer_instalar "$APP_DIR"
asegurar_directorios_colegio "$APP_DIR"

log "3/6 archivos del colegio desde S3"
sync_archivos bajar

log "4/6 overlay .env de emergencia"
aplicar_env_colegio "$APP_DIR/.env"
# Prefijos S3 y AWS del VPS (config.env), no los del .env de Hostinger.
reafirmar_s3_desde_config
inferir_aws_bin
require_aws
if [[ "$PROYECTO" != "$(basename "$APP_DIR")" ]]; then
    log "AVISO: TENANT_SLUG=$PROYECTO distinto de la carpeta $(basename "$APP_DIR"). Conviene que coincidan."
fi
require_vars MYSQL_HOST MYSQL_USER MYSQL_DATABASE LAB_ORDEN S3_DUMP_FILTER
if [[ "${MYSQL_PASSWORD:-}" == "__CAMBIAR__" || -z "${MYSQL_PASSWORD:-}" ]]; then
    die "Falta EMERGENCIA_DB_PASSWORD en el .env (bloque de emergencia de producción)."
fi
aplicar_overlay_env "$APP_DIR/.env"

log "5/6 dump MySQL más reciente"
clave="$(clave_dump_mas_reciente)"
log "Dump elegido: s3://${S3_BUCKET}/${clave}"

if [[ "$DRY_RUN" == "1" ]]; then
    log "[dry-run] no se descarga ni importa el dump."
else
    tmp_dump="$(mktemp /tmp/se-dump.XXXXXX)"
    tmp_cnf="$(mysql_cnf_tmp)"
    cleanup() {
        rm -f "$tmp_dump" "$tmp_cnf"
    }
    trap cleanup EXIT

    extra=()
    if [[ -n "${AWS_REGION:-}" ]]; then
        extra+=(--region "$AWS_REGION")
    fi
    aws s3 cp "s3://${S3_BUCKET}/${clave}" "$tmp_dump" "${extra[@]}"

    # Vaciar por si un import anterior falló a mitad (p. ej. collation desconocida).
    mysql --defaults-extra-file="$tmp_cnf" -e \
        "DROP DATABASE IF EXISTS \`${MYSQL_DATABASE}\`; CREATE DATABASE \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    log "Importando dump (collations UCA1400 → unicode_ci si hace falta)…"
    if [[ "$clave" == *.gz ]]; then
        gzip -dc "$tmp_dump" | normalizar_collations_dump \
            | mysql --defaults-extra-file="$tmp_cnf" "$MYSQL_DATABASE"
    else
        normalizar_collations_dump <"$tmp_dump" \
            | mysql --defaults-extra-file="$tmp_cnf" "$MYSQL_DATABASE"
    fi
    log "Importación MySQL terminada."
fi

log "6/6 permisos, caches y se:migrate-legacy"
aplicar_permisos "$APP_DIR"
laravel_post_restaurar "$APP_DIR"

log "Restauración lista."
echo "Probá APP_URL=$(env_valor "$APP_DIR/.env" APP_URL 0) (bloque EMERGENCIA_*)."
echo "Si el virtual host o el DNS de emergencia ya apuntan acá, el colegio debería responder con datos de la última hora."
