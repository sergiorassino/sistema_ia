#!/usr/bin/env bash
# VPS de emergencia: preparación UNA VEZ. No instala cron horario.
# Deja código, Composer y overlay listos; los datos se bajan recién con restaurar.sh.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

uso() {
    cat <<'EOF'
Uso: preparar-vps.sh [--dry-run]

Una sola vez en vpsEmergencia:
  - clona (o actualiza) el repo
  - composer install --no-dev
  - crea carpetas del colegio
  - verifica PHP, MySQL client, git, aws, composer

NO descarga dumps ni archivos de S3. NO importa la base.
Cuando caiga producción: restaurar.sh
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run) DRY_RUN=1 ;;
        -h|--help) uso; exit 0 ;;
        *) die "Opción desconocida: $1" ;;
    esac
    shift
done

asegurar_claves_restaurar_web
cargar_config
prepend_php_path
inferir_app_dir
require_vars GIT_URL GIT_BRANCH WEB_USER

php_ok
require_cmd git
require_cmd mysql
command -v composer >/dev/null 2>&1 || die "No está instalado: composer. Instalalo con el PHP de DirectAdmin (docs/10 §3.1)."
if [[ -n "${AWS_BIN:-}" ]]; then
    require_aws
elif ! type -P aws >/dev/null 2>&1; then
    log "AVISO: aws no está instalado. Hace falta para restaurar.sh (ver docs/10 §2.1)."
fi

if [[ -d "$APP_DIR/.git" ]]; then
    log "Repo ya presente en $APP_DIR"
    git_actualizar "$APP_DIR"
elif [[ -e "$APP_DIR" && -n "$(ls -A "$APP_DIR" 2>/dev/null || true)" ]]; then
    die "APP_DIR existe, no está vacío y no es un clone git: $APP_DIR"
else
    log "Clonando $GIT_URL ($GIT_BRANCH) → $APP_DIR"
    if [[ "$DRY_RUN" == "1" ]]; then
        log "[dry-run] git clone --branch $GIT_BRANCH $GIT_URL $APP_DIR"
    else
        mkdir -p "$(dirname "$APP_DIR")"
        git clone --branch "$GIT_BRANCH" "$GIT_URL" "$APP_DIR"
    fi
fi

if [[ "$DRY_RUN" != "1" ]]; then
    if [[ ! -f "$APP_DIR/.env" ]]; then
        cp "$APP_DIR/.env.example" "$APP_DIR/.env"
        log "Copiado .env.example → .env (placeholder hasta restaurar.sh)."
    fi
    asegurar_directorios_colegio "$APP_DIR"
fi

composer_instalar "$APP_DIR"

if [[ "$DRY_RUN" != "1" ]]; then
    aplicar_permisos "$APP_DIR"
    verificar_preparacion
fi

cat <<EOF

Preparación lista. El MySQL y APP_URL de emergencia salen del bloque
EMERGENCIA_* del .env de producción (se baja con restaurar.sh).

  1. Credenciales AWS en este VPS
  2. Virtual host apuntando a $APP_DIR (carpeta = TENANT_SLUG)
  3. Base/usuario DirectAdmin iguales a EMERGENCIA_DB_* del .env de producción

Este VPS NO debe tener cron de sync horario. Tras restaurar, HABILITAR_RESPALDO
queda en 0 en el .env (overlay). No ejecutes respaldar.sh acá.
Cuando caiga el servidor original:

  bash $APP_DIR/scripts/emergencia/restaurar.sh

EOF
