#!/usr/bin/env bash
# Funciones compartidas: respaldo incremental de archivos y restauración de emergencia.
# No ejecutar este archivo solo; lo sourcean los scripts de esta carpeta.
# Misma rutina que Silavet (docs/13 allí); acá: docs/10-backup-y-vps-emergencia.md.

set -euo pipefail

_EMERGENCIA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DRY_RUN="${DRY_RUN:-0}"

# Nombres de dump y logs en hora argentina (el hosting suele estar en UTC).
# Override: SE_TZ en .env (o config.env).
aplicar_zona_horaria() {
    export TZ="${SE_TZ:-America/Argentina/Buenos_Aires}"
}
aplicar_zona_horaria

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

die() {
    echo "ERROR: $*" >&2
    exit 1
}

# Hosting compartido: AWS CLI suele no estar en PATH. Preferí AWS_BIN en el .env;
# si falta, se intenta ~/.local/bin/aws.
aws() {
    local bin="${AWS_BIN:-aws}"
    command "$bin" "$@"
}

require_aws() {
    local bin="${AWS_BIN:-aws}"
    if [[ "$bin" == */* || "$bin" == ./* ]]; then
        [[ -x "$bin" ]] || die "AWS_BIN no es ejecutable: $bin (definilo en el .env del lab)."
        return 0
    fi
    type -P aws >/dev/null 2>&1 || die "No está instalado: aws. En Hostinger/shared instalalo en tu home y poné AWS_BIN en el .env (docs/10 §2.1)."
}

# Si AWS_BIN quedó vacío: ~/.local/bin/aws (instalación típica sin root).
inferir_aws_bin() {
    if [[ -n "${AWS_BIN:-}" ]]; then
        return 0
    fi
    if [[ -n "${HOME:-}" && -x "${HOME}/.local/bin/aws" ]]; then
        AWS_BIN="${HOME}/.local/bin/aws"
        log "AWS_BIN inferido: $AWS_BIN"
    fi
}

require_cmd() {
    local cmd="$1"
    command -v "$cmd" >/dev/null 2>&1 || die "No está instalado: $cmd"
}

require_vars() {
    local var
    for var in "$@"; do
        if [[ -z "${!var:-}" ]]; then
            die "Falta la variable $var en el archivo de configuración."
        fi
    done
}

# Carga scripts/emergencia/config.env de ESTE colegio (o SE_EMERGENCIA_CONF).
cargar_config() {
    local candidato=""
    if [[ -n "${SE_EMERGENCIA_CONF:-}" ]]; then
        candidato="$SE_EMERGENCIA_CONF"
    elif [[ -f "$_EMERGENCIA_DIR/config.env" ]]; then
        candidato="$_EMERGENCIA_DIR/config.env"
    fi

    [[ -n "$candidato" && -f "$candidato" ]] || die "No hay scripts/emergencia/config.env en este colegio. Copiá config.example.env → config.env y completá (docs/10)."

    # shellcheck disable=SC1090
    set -a
    source "$candidato"
    set +a

    aplicar_zona_horaria

    # set -a exporta AWS_REGION= vacío y el CLI arma https://s3..amazonaws.com
    if [[ -z "${AWS_REGION:-}" ]]; then
        unset AWS_REGION
    fi
    if [[ -z "${AWS_DEFAULT_REGION:-}" ]]; then
        unset AWS_DEFAULT_REGION
    fi
    if [[ -z "${AWS_BIN:-}" ]]; then
        unset AWS_BIN
    fi
    if [[ -z "${MYSQLDUMP_BIN:-}" ]]; then
        unset MYSQLDUMP_BIN
    fi

    if [[ -z "${S3_DUMP_FILTER:-}" && -n "${LAB_ORDEN:-}" && -n "${PROYECTO:-}" ]]; then
        S3_DUMP_FILTER="e${LAB_ORDEN}_${PROYECTO}"
        log "S3_DUMP_FILTER por defecto: $S3_DUMP_FILTER"
    fi

    inferir_app_dir
    inferir_aws_bin

    CONFIG_CARGADO="$candidato"
    log "Config: $CONFIG_CARGADO"
}

# Si config.env aún no tiene las claves de la página celular, las copia desde
# la plantilla (mismo token en todos los labs). No pisa un valor ya escrito.
asegurar_claves_restaurar_web() {
    local dest="${1:-$_EMERGENCIA_DIR/config.env}"
    local src="$_EMERGENCIA_DIR/config.example.env"
    local clave val

    [[ -f "$src" ]] || return 0
    if [[ ! -f "$dest" ]]; then
        cp "$src" "$dest"
        chmod 600 "$dest"
        log "Creado $dest desde la plantilla (incluye restauración web)."
        return 0
    fi

    for clave in HABILITAR_RESTAURAR_WEB RESTAURAR_WEB_TOKEN RESTAURAR_WEB_HOST; do
        if grep -qE "^[[:space:]]*${clave}=" "$dest"; then
            continue
        fi
        val="$(grep -E "^[[:space:]]*${clave}=" "$src" | tail -n 1 || true)"
        if [[ -n "$val" ]]; then
            echo "$val" >> "$dest"
            log "Agregado $clave a $dest (plantilla)."
        fi
    done
}

# En restaurar.sh el .env bajado de producción no debe pisar bucket/prefijos del VPS.
reafirmar_s3_desde_config() {
    [[ -n "${CONFIG_CARGADO:-}" && -f "$CONFIG_CARGADO" ]] || return 0
    local linea clave valor
    while IFS= read -r linea || [[ -n "$linea" ]]; do
        [[ "$linea" =~ ^[[:space:]]*# ]] && continue
        [[ "$linea" =~ ^[[:space:]]*$ ]] && continue
        [[ "$linea" == *=* ]] || continue
        clave="${linea%%=*}"
        valor="${linea#*=}"
        case "$clave" in
            S3_BUCKET|S3_PREFIX_DUMPS|S3_PREFIX_ARCHIVOS|AWS_REGION)
                printf -v "$clave" '%s' "$valor"
                export "$clave"
                ;;
        esac
    done <"$CONFIG_CARGADO"
    if [[ -z "${AWS_REGION:-}" ]]; then
        unset AWS_REGION
    fi
    # Typo frecuente visto en .env de producción.
    if [[ "${S3_PREFIX_DUMPS:-}" == *backupDedicdo* ]]; then
        log "AVISO: S3_PREFIX_DUMPS tenía typo backupDedicdo; usando backupDedicado"
        S3_PREFIX_DUMPS="${S3_PREFIX_DUMPS//backupDedicdo/backupDedicado}"
    fi
    if [[ "${S3_PREFIX_ARCHIVOS:-}" == *backupDedicdo* ]]; then
        log "AVISO: S3_PREFIX_ARCHIVOS tenía typo backupDedicdo; usando backupDedicado"
        S3_PREFIX_ARCHIVOS="${S3_PREFIX_ARCHIVOS//backupDedicdo/backupDedicado}"
    fi
    log "S3 desde config.env: bucket=$S3_BUCKET dumps=$S3_PREFIX_DUMPS"
}

# DirectAdmin: el PHP de PATH no es el de los sitios. Composer y `php` deben
# ser el binario de PHP_BIN.
prepend_php_path() {
    local php_bin="${PHP_BIN:-php}"
    local php_dir resolved
    resolved="$(command -v "$php_bin" 2>/dev/null || true)"
    [[ -n "$resolved" ]] || return 0
    php_dir="$(dirname "$resolved")"
    case ":$PATH:" in
        *":${php_dir}:"*) ;;
        *) export PATH="${php_dir}:$PATH" ;;
    esac
    log "PATH PHP: $resolved"
}

# Carpeta Laravel = padre de scripts/emergencia, si config.env no trae APP_DIR.
inferir_app_dir() {
    if [[ -z "${APP_DIR:-}" ]]; then
        APP_DIR="$(cd "$_EMERGENCIA_DIR/../.." && pwd)"
        log "APP_DIR inferido: $APP_DIR"
    fi
}

# Tenant, LAB_ORDEN y MySQL de emergencia (bloque EMERGENCIA_*).
# No aplica AWS_BIN ni paths del hosting de producción: en restaurar.sh el .env
# bajado de S3 trae rutas de Hostinger que no existen en el VPS.
aplicar_env_colegio() {
    local envf="${1:-$APP_DIR/.env}"
    [[ -f "$envf" ]] || die "No existe $envf"

    local slug orden ehost eport edb euser epass
    slug="$(env_valor "$envf" TENANT_SLUG 0)"
    if [[ -n "$slug" ]]; then
        PROYECTO="$slug"
    elif [[ -z "${PROYECTO:-}" ]]; then
        PROYECTO="$(basename "$APP_DIR")"
        log "PROYECTO = basename (sin TENANT_SLUG): $PROYECTO"
    fi

    orden="$(env_valor "$envf" LAB_ORDEN 0)"
    if [[ -n "$orden" ]]; then
        LAB_ORDEN="$orden"
    fi
    [[ -n "${LAB_ORDEN:-}" ]] || die "Falta LAB_ORDEN en $envf (bloque de emergencia; número del dump e1_, e2_, …). Ver .env.example."

    ehost="$(env_valor "$envf" EMERGENCIA_DB_HOST 0)"
    eport="$(env_valor "$envf" EMERGENCIA_DB_PORT 0)"
    edb="$(env_valor "$envf" EMERGENCIA_DB_DATABASE 0)"
    euser="$(env_valor "$envf" EMERGENCIA_DB_USERNAME 0)"
    epass="$(env_valor "$envf" EMERGENCIA_DB_PASSWORD 0)"
    [[ -n "$ehost" ]] && MYSQL_HOST="$ehost"
    [[ -n "$eport" ]] && MYSQL_PORT="$eport"
    [[ -n "$edb" ]] && MYSQL_DATABASE="$edb"
    [[ -n "$euser" ]] && MYSQL_USER="$euser"
    [[ -n "$epass" ]] && MYSQL_PASSWORD="$epass"
    MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
    MYSQL_PORT="${MYSQL_PORT:-3306}"

    S3_DUMP_FILTER="e${LAB_ORDEN}_${PROYECTO}"
    log "Colegio TENANT_SLUG=$PROYECTO LAB_ORDEN=$LAB_ORDEN filtro=$S3_DUMP_FILTER"
}

# Solo en producción (respaldar.sh): AWS_BIN, HABILITAR_RESPALDO, etc. del .env.
aplicar_env_hosting_produccion() {
    local envf="${1:-$APP_DIR/.env}"
    [[ -f "$envf" ]] || die "No existe $envf"

    local v
    v="$(env_valor "$envf" HABILITAR_RESPALDO 0)"
    [[ -n "$v" ]] && HABILITAR_RESPALDO="$v"
    v="$(env_valor "$envf" AWS_BIN 0)"
    [[ -n "$v" ]] && AWS_BIN="$v"
    v="$(env_valor "$envf" AWS_REGION 0)"
    [[ -n "$v" ]] && AWS_REGION="$v"
    v="$(env_valor "$envf" MYSQLDUMP_BIN 0)"
    [[ -n "$v" ]] && MYSQLDUMP_BIN="$v"
    v="$(env_valor "$envf" SE_TZ 0)"
    [[ -n "$v" ]] && SE_TZ="$v"

    if [[ -z "${AWS_BIN:-}" ]]; then
        unset AWS_BIN
    fi
    if [[ -z "${MYSQLDUMP_BIN:-}" ]]; then
        unset MYSQLDUMP_BIN
    fi
    if [[ -z "${AWS_REGION:-}" ]]; then
        unset AWS_REGION
    fi

    aplicar_zona_horaria
    inferir_aws_bin
}

env_set_clave() {
    local archivo="$1" clave="$2" valor="$3"
    local tmp
    tmp="$(mktemp)"
    grep -vE "^${clave}=" "$archivo" >"$tmp" || true
    printf '%s=%s\n' "$clave" "$valor" >>"$tmp"
    mv "$tmp" "$archivo"
}

ruta_overlay() {
    if [[ -n "${ENV_EMERGENCIA_FILE:-}" ]]; then
        echo "$ENV_EMERGENCIA_FILE"
        return
    fi
    echo "$_EMERGENCIA_DIR/env.emergencia"
}

s3_uri_archivos() {
    echo "s3://${S3_BUCKET}/${S3_PREFIX_ARCHIVOS%/}/${PROYECTO}"
}

s3_uri_dumps() {
    echo "s3://${S3_BUCKET}/${S3_PREFIX_DUMPS%/}"
}

# Nombre: e1_montecristo_2026_07_24_21_30_02.sql.gz (hora Argentina, ver aplicar_zona_horaria).
nombre_dump_hora() {
    require_vars LAB_ORDEN PROYECTO
    [[ "$LAB_ORDEN" =~ ^[0-9]+$ ]] || die "LAB_ORDEN debe ser un número (1 → e1_, 2 → e2_, …)."
    aplicar_zona_horaria
    echo "e${LAB_ORDEN}_${PROYECTO}_$(date +%Y_%m_%d_%H_%M_%S).sql.gz"
}

filtro_dump_por_defecto() {
    require_vars LAB_ORDEN PROYECTO
    echo "e${LAB_ORDEN}_${PROYECTO}"
}

# Lee una clave de .env Laravel (última aparición). No hace source (valores con espacios/$).
env_valor() {
    local archivo="$1" clave="$2" obligatorio="${3:-1}"
    local linea valor
    [[ -f "$archivo" ]] || die "No existe $archivo"
    linea="$(grep -E "^${clave}=" "$archivo" | tail -n 1 || true)"
    if [[ -z "$linea" ]]; then
        [[ "$obligatorio" == "1" ]] && die "Falta ${clave} en $archivo"
        echo ""
        return 0
    fi
    valor="${linea#*=}"
    if [[ "$valor" == \"*\" ]]; then
        valor="${valor#\"}"
        valor="${valor%\"}"
    elif [[ "$valor" == \'*\' ]]; then
        valor="${valor#\'}"
        valor="${valor%\'}"
    fi
    printf '%s' "$valor"
}

mysqldump_binario() {
    local bin="${MYSQLDUMP_BIN:-mysqldump}"
    command -v "$bin" >/dev/null 2>&1 || die "No está instalado: $bin (MYSQLDUMP_BIN en config.env)."
    echo "$bin"
}

# Dump de la BD del .env de producción → S3 (mismo prefijo que la rutina anterior).
respaldar_mysql_hora() {
    require_aws
    require_vars PROYECTO APP_DIR S3_BUCKET S3_PREFIX_DUMPS LAB_ORDEN
    [[ "${HABILITAR_RESPALDO:-}" == "1" ]] || die "HABILITAR_RESPALDO=1 en el .env (solo producción). No corras el respaldo en el VPS de emergencia."
    es_laravel_dir "$APP_DIR" || die "APP_DIR no parece Laravel: $APP_DIR"

    local envf="$APP_DIR/.env"
    local host port user password database
    host="$(env_valor "$envf" DB_HOST)"
    port="$(env_valor "$envf" DB_PORT 0)"
    port="${port:-3306}"
    user="$(env_valor "$envf" DB_USERNAME)"
    password="$(env_valor "$envf" DB_PASSWORD 0)"
    database="$(env_valor "$envf" DB_DATABASE)"
    [[ -n "$database" ]] || die "DB_DATABASE vacío en $envf"

    local nombre uri extra dump_bin cnf tmp
    nombre="$(nombre_dump_hora)"
    uri="$(s3_uri_dumps)/${nombre}"
    extra=()
    [[ -n "${AWS_REGION:-}" ]] && extra+=(--region "$AWS_REGION")
    dump_bin="$(mysqldump_binario)"

    log "Dump MySQL ${database}@${host} → ${uri}"

    if [[ "$DRY_RUN" == "1" ]]; then
        log "[dry-run] $dump_bin | gzip → aws s3 cp $uri"
        return 0
    fi

    cnf="$(mktemp)"
    tmp="$(mktemp /tmp/se-dump.XXXXXX)"
    chmod 600 "$cnf" "$tmp"
    cleanup_dump() {
        rm -f "$cnf" "$tmp"
    }
    trap cleanup_dump EXIT

    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
        "$host" "$port" "$user" "$password" >"$cnf"

    local flags=(--defaults-extra-file="$cnf" --single-transaction --quick)
    if "$dump_bin" --help 2>/dev/null | grep -q -- '--no-tablespaces'; then
        flags+=(--no-tablespaces)
    fi
    if "$dump_bin" --help 2>/dev/null | grep -q -- '--column-statistics'; then
        flags+=(--column-statistics=0)
    fi

    "$dump_bin" "${flags[@]}" "$database" | gzip -c >"$tmp"
    [[ -s "$tmp" ]] || die "El dump quedó vacío."

    aws s3 cp "$tmp" "$uri" "${extra[@]}"
    log "Dump subido: $nombre"

    trap - EXIT
    cleanup_dump
}

# Carpetas relativas al proyecto que no están en git (o no de forma fiable).
# Array (no process substitution): en Hostinger no existe /dev/fd.
# Mismas carpetas S3 que Silavet: backupDedicado/backupHora y backupDedicado/archivos.
# El dump del colegio se distingue por el prefijo e (e1_slug_…).
RUTAS_ARCHIVOS_COLEGIO=(
    public/archivos
    public/storage
    storage/app/public
    storage/app/private
    storage/fonts
    storage/certs
    afipSE/cert
)

# Archivos sueltos (no carpetas). Si no existen, el respaldo los omite.
ARCHIVOS_SUELTOS_COLEGIO=(
    public/img/firmaRepLegal.jpg
)

es_laravel_dir() {
    local dir="$1"
    [[ -f "$dir/artisan" && -f "$dir/composer.json" ]]
}

asegurar_directorios_colegio() {
    local dir="$1"
    mkdir -p \
        "$dir/public/archivos" \
        "$dir/public/storage" \
        "$dir/storage/app/public" \
        "$dir/storage/app/private" \
        "$dir/storage/fonts" \
        "$dir/storage/certs" \
        "$dir/afipSE/cert" \
        "$dir/storage/app/livewire-tmp" \
        "$dir/storage/framework/cache" \
        "$dir/storage/framework/sessions" \
        "$dir/storage/framework/views" \
        "$dir/storage/logs" \
        "$dir/bootstrap/cache"
}

aplicar_permisos() {
    local dir="$1"
    local user="${WEB_USER:-www-data}"
    local group="${WEB_GROUP:-$user}"

    if [[ "$DRY_RUN" == "1" ]]; then
        log "[dry-run] chown ${user}:${group} storage bootstrap/cache public/{archivos,storage} afipSE/cert"
        return 0
    fi

    if ! id "$user" >/dev/null 2>&1; then
        log "AVISO: el usuario web '$user' no existe; no se cambia el dueño."
        return 0
    fi

    chown -R "${user}:${group}" \
        "$dir/storage" \
        "$dir/bootstrap/cache" \
        "$dir/public/archivos" \
        "$dir/public/storage" \
        "$dir/afipSE/cert" \
        2>/dev/null || log "AVISO: no se pudieron ajustar todos los permisos (¿hace falta root?)."
}

sync_archivo_suelto() {
    local sentido="$1" rel="$2"
    local uri
    local -a extra=()
    local -a dry=()
    [[ "$sentido" == "subir" || "$sentido" == "bajar" ]] || die "sentido inválido: $sentido (usar subir o bajar)"
    uri="$(s3_uri_archivos)"
    [[ "$DRY_RUN" == "1" ]] && dry=(--dryrun)
    [[ -n "${AWS_REGION:-}" ]] && extra+=(--region "$AWS_REGION")

    if [[ "$sentido" == "subir" ]]; then
        [[ -f "$APP_DIR/$rel" ]] || { log "Omitido (no existe): $rel"; return 0; }
        log "s3 cp $rel → ${uri}/${rel}"
        aws s3 cp "$APP_DIR/$rel" "${uri}/${rel}" "${extra[@]}" "${dry[@]}"
        return 0
    fi

    mkdir -p "$(dirname "$APP_DIR/$rel")"
    log "s3 cp ${uri}/${rel} → $rel"
    aws s3 cp "${uri}/${rel}" "$APP_DIR/$rel" "${extra[@]}" "${dry[@]}" \
        || log "AVISO: no está en S3: $rel"
}

sync_archivos() {
    local sentido="$1" # subir | bajar
    local origen destino
    local uri
    [[ "$sentido" == "subir" || "$sentido" == "bajar" ]] || die "sentido inválido: $sentido (usar subir o bajar)"
    uri="$(s3_uri_archivos)"
    local dry=()
    [[ "$DRY_RUN" == "1" ]] && dry=(--dryrun)

    require_aws
    require_vars PROYECTO APP_DIR S3_BUCKET S3_PREFIX_ARCHIVOS
    es_laravel_dir "$APP_DIR" || die "APP_DIR no parece un proyecto Laravel: $APP_DIR"

    local extra=()
    if [[ -n "${AWS_REGION:-}" ]]; then
        extra+=(--region "$AWS_REGION")
    fi

    local rel
    for rel in "${RUTAS_ARCHIVOS_COLEGIO[@]}"; do
        [[ -z "$rel" ]] && continue
        if [[ "$sentido" == "subir" ]]; then
            [[ -e "$APP_DIR/$rel" ]] || { log "Omitido (no existe): $rel"; continue; }
            origen="$APP_DIR/$rel"
            destino="${uri}/${rel}"
        else
            origen="${uri}/${rel}"
            destino="$APP_DIR/$rel"
            mkdir -p "$destino"
        fi
        log "s3 sync $origen → $destino"
        aws s3 sync "$origen" "$destino" \
            "${extra[@]}" \
            "${dry[@]}" \
            --exclude "*.tmp" \
            --exclude ".DS_Store" \
            --exclude "Thumbs.db" \
            --exclude "livewire-tmp/*"
    done

    for rel in "${ARCHIVOS_SUELTOS_COLEGIO[@]}"; do
        [[ -z "$rel" ]] && continue
        sync_archivo_suelto "$sentido" "$rel"
    done

    if [[ "$sentido" == "subir" ]]; then
        if [[ -f "$APP_DIR/.env" ]]; then
            log "s3 cp .env → ${uri}/.env"
            aws s3 cp "$APP_DIR/.env" "${uri}/.env" "${extra[@]}" "${dry[@]}"
        else
            log "AVISO: no hay .env para subir."
        fi
    else
        log "s3 cp ${uri}/.env → $APP_DIR/.env"
        aws s3 cp "${uri}/.env" "$APP_DIR/.env" "${extra[@]}" "${dry[@]}"
    fi
}

# El dump más reciente del proyecto (por fecha de modificación en S3).
clave_dump_mas_reciente() {
    require_aws
    require_vars S3_BUCKET S3_PREFIX_DUMPS S3_DUMP_FILTER

    local extra=()
    if [[ -n "${AWS_REGION:-}" ]]; then
        extra+=(--region "$AWS_REGION")
    fi

    local listado linea clave err
    err="$(mktemp)"
    if ! listado="$(aws s3 ls "$(s3_uri_dumps)/" --recursive "${extra[@]}" 2>"$err")"; then
        die "Falló aws s3 ls en $(s3_uri_dumps)/ — $(tr '\n' ' ' <"$err"). Probá: aws s3 ls s3://${S3_BUCKET}/  y ajustá S3_PREFIX_DUMPS (debe ser backupDedicado/backupHora)."
    fi
    rm -f "$err"

    linea="$(
        echo "$listado" \
            | grep -F "$S3_DUMP_FILTER" \
            | grep -E '\.(sql|sql\.gz|gz)$' \
            | sort \
            | tail -n 1
    )" || true

    [[ -n "$linea" ]] || die "No se encontró ningún dump en $(s3_uri_dumps)/ que coincida con S3_DUMP_FILTER='$S3_DUMP_FILTER'."

    clave="$(echo "$linea" | awk '{ $1=$2=$3=""; sub(/^ +/, ""); print }')"
    [[ -n "$clave" ]] || die "No se pudo interpretar la clave S3 del dump: $linea"
    echo "$clave"
}

# Hostinger (MariaDB 10.10+/11) emite collations UCA 14.0 que DirectAdmin
# (MariaDB 10.5/10.6 típico) no conoce → ERROR 1273 Unknown collation.
# Reescribe a equivalentes amplios antes de importar en el VPS de emergencia.
normalizar_collations_dump() {
    sed -E \
        -e 's/utf8mb4_uca1400_[a-z0-9_]+/utf8mb4_unicode_ci/g' \
        -e 's/utf8mb3_uca1400_[a-z0-9_]+/utf8mb3_general_ci/g' \
        -e 's/utf8_uca1400_[a-z0-9_]+/utf8_general_ci/g' \
        -e 's/\buca1400_[a-z0-9_]+\b/utf8mb4_unicode_ci/g'
}

mysql_cnf_tmp() {
    require_vars MYSQL_HOST MYSQL_USER MYSQL_DATABASE
    local tmp
    tmp="$(mktemp)"
    chmod 600 "$tmp"
    cat >"$tmp" <<EOF
[client]
host=${MYSQL_HOST}
port=${MYSQL_PORT:-3306}
user=${MYSQL_USER}
password=${MYSQL_PASSWORD:-}
EOF
    echo "$tmp"
}

# Pisa APP_URL y DB_* con el bloque EMERGENCIA_* del mismo .env (bajado de S3).
# Si no hay bloque, usa el overlay viejo env.emergencia.
aplicar_overlay_env() {
    local env_file="$1"
    local url host port db user pass overlay

    [[ -f "$env_file" ]] || die "No existe $env_file (¿falló la descarga de S3?)."

    url="$(env_valor "$env_file" EMERGENCIA_APP_URL 0)"
    if [[ -n "$url" ]]; then
        host="$(env_valor "$env_file" EMERGENCIA_DB_HOST 0)"
        port="$(env_valor "$env_file" EMERGENCIA_DB_PORT 0)"
        db="$(env_valor "$env_file" EMERGENCIA_DB_DATABASE)"
        user="$(env_valor "$env_file" EMERGENCIA_DB_USERNAME)"
        pass="$(env_valor "$env_file" EMERGENCIA_DB_PASSWORD 0)"
        host="${host:-127.0.0.1}"
        port="${port:-3306}"
        if [[ "$url" == *__CAMBIAR__* || "$pass" == "__CAMBIAR__" || "$db" == "__CAMBIAR__" ]]; then
            die "El bloque EMERGENCIA_* de $env_file todavía tiene placeholders. Completalo en producción y volvé a respaldar."
        fi
        [[ -n "$pass" ]] || die "Falta EMERGENCIA_DB_PASSWORD en $env_file"

        if [[ "$DRY_RUN" == "1" ]]; then
            log "[dry-run] overlay desde .env: APP_URL=$url DB=$db user=$user host=$host"
            return 0
        fi

        env_set_clave "$env_file" APP_ENV production
        env_set_clave "$env_file" APP_DEBUG false
        env_set_clave "$env_file" APP_URL "$url"
        env_set_clave "$env_file" DB_HOST "$host"
        env_set_clave "$env_file" DB_PORT "$port"
        env_set_clave "$env_file" DB_DATABASE "$db"
        env_set_clave "$env_file" DB_USERNAME "$user"
        env_set_clave "$env_file" DB_PASSWORD "$pass"
        env_set_clave "$env_file" SESSION_SECURE_COOKIE true
        env_set_clave "$env_file" SESSION_DOMAIN ""
        # Evita que un cron o un respaldo accidental use el .env de producción en el VPS.
        env_set_clave "$env_file" HABILITAR_RESPALDO 0
        log "Overlay aplicado desde bloque EMERGENCIA_* → $env_file"
        return 0
    fi

    overlay="$(ruta_overlay)"
    [[ -f "$overlay" ]] || die "No hay EMERGENCIA_APP_URL en $env_file ni overlay $overlay. Completá el bloque de emergencia en el .env de producción."

    if grep -q '__CAMBIAR__' "$overlay"; then
        die "El overlay $overlay todavía tiene placeholders __CAMBIAR__. Completalo antes de restaurar."
    fi

    if [[ "$DRY_RUN" == "1" ]]; then
        log "[dry-run] aplicaría overlay $overlay sobre $env_file"
        return 0
    fi

    local tmp key line
    tmp="$(mktemp)"
    cp "$env_file" "$tmp"

    while IFS= read -r line || [[ -n "$line" ]]; do
        [[ "$line" =~ ^[[:space:]]*# ]] && continue
        [[ "$line" =~ ^[[:space:]]*$ ]] && continue
        key="${line%%=*}"
        [[ -n "$key" ]] || continue
        grep -vE "^${key}=" "$tmp" >"${tmp}.new"
        mv "${tmp}.new" "$tmp"
        echo "$line" >>"$tmp"
    done <"$overlay"

    mv "$tmp" "$env_file"
    log "Overlay aplicado: $overlay → $env_file"
}

git_actualizar() {
    local dir="$1"
    local branch="${GIT_BRANCH:-main}"

    [[ -d "$dir/.git" ]] || die "No hay repositorio git en $dir. Ejecutá primero preparar-vps.sh."

    if [[ "$DRY_RUN" == "1" ]]; then
        log "[dry-run] git fetch && git checkout $branch && git pull --ff-only"
        return 0
    fi

    (
        cd "$dir"
        git fetch --prune origin
        git checkout "$branch"
        git pull --ff-only origin "$branch"
    )
}

# DirectAdmin: el `php` de PATH suele ser el de AlmaLinux. Composer tiene que
# usar el mismo binario que Laravel (PHP_BIN).
composer_con_php() {
    local php_bin="${PHP_BIN:-php}"
    local php_dir
    php_dir="$(dirname "$(command -v "$php_bin")")"
    export PATH="${php_dir}:$PATH"
    command -v composer >/dev/null 2>&1 || die "No está instalado: composer. En DirectAdmin: curl -sS https://getcomposer.org/installer | $php_bin -- --install-dir=\$HOME/bin --filename=composer"
    composer "$@"
}

composer_instalar() {
    local dir="$1"
    if [[ "$DRY_RUN" == "1" ]]; then
        log "[dry-run] composer install --no-dev en $dir"
        return 0
    fi
    (
        cd "$dir"
        composer_con_php install --no-dev --optimize-autoloader --no-interaction
    )
}

laravel_post_restaurar() {
    local dir="$1"
    local php_bin="${PHP_BIN:-php}"

    if [[ "$DRY_RUN" == "1" ]]; then
        log "[dry-run] artisan config/view/route:clear + se:migrate-legacy --force"
        return 0
    fi

    rm -f "$dir/public/hot"
    (
        cd "$dir"
        "$php_bin" artisan config:clear
        "$php_bin" artisan view:clear
        "$php_bin" artisan route:clear
        "$php_bin" artisan cache:clear || true
        "$php_bin" artisan se:migrate-legacy --force || log "AVISO: se:migrate-legacy falló; revisá a mano."
    )
}

confirmar() {
    local mensaje="$1"
    if [[ "${SI:-0}" == "1" ]]; then
        return 0
    fi
    echo
    echo "$mensaje"
    read -r -p "¿Continuar? [y/N] " resp
    [[ "$resp" == "y" || "$resp" == "Y" || "$resp" == "si" || "$resp" == "sí" ]] || die "Cancelado."
}

# No usar `php -m | grep`: en DirectAdmin los warnings de .so faltantes
# contienen el nombre del módulo y el grep miente. extension_loaded es la prueba.
php_ext_cargada() {
    local php_bin="$1"
    local ext="$2"
    "$php_bin" -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null
}

php_ok() {
    local php_bin="${PHP_BIN:-php}"
    require_cmd "$php_bin"
    "$php_bin" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' 2>/dev/null \
        || die "Se necesita PHP 8.2 o superior ($php_bin). En DirectAdmin: PHP_BIN=/usr/local/php83/bin/php en config.env (default) o en el .env."

    local ext ini
    ini="$("$php_bin" -r 'echo php_ini_loaded_file() ?: "(ninguno)";' 2>/dev/null || true)"
    log "PHP: $php_bin  ini: $ini"

    for ext in pdo_mysql mbstring xml curl zip tokenizer fileinfo ctype json; do
        if ! php_ext_cargada "$php_bin" "$ext"; then
            die "Falta la extensión PHP: $ext (binario $php_bin, ini $ini). En DirectAdmin NO se habilita con extension=$ext en php.ini (eso genera el warning de .so). Sacá esas líneas del ini CLI y/o rebuild PHP: ver docs/10 §3.1. Diagnóstico: bash $_EMERGENCIA_DIR/diagnostico.sh"
        fi
    done
    if ! php_ext_cargada "$php_bin" gd; then
        log "AVISO: no está la extensión gd (algunos informes/imágenes pueden fallar)."
    fi
}

verificar_preparacion() {
    php_ok
    require_cmd git
    command -v composer >/dev/null 2>&1 || die "No está instalado: composer."
    require_cmd mysql
    require_aws

    es_laravel_dir "$APP_DIR" || die "APP_DIR no es un proyecto Laravel: $APP_DIR"
    [[ -d "$APP_DIR/.git" ]] || die "No hay .git en $APP_DIR."

    if [[ -f "$APP_DIR/.env" ]] && grep -qE '^EMERGENCIA_APP_URL=' "$APP_DIR/.env"; then
        log "Bloque EMERGENCIA_* presente en .env (se completa al restaurar desde S3)."
    else
        log "AVISO: el .env local aún no tiene EMERGENCIA_*. restaurar.sh lo baja de S3 (tiene que estar en el .env de producción)."
    fi

    if aws sts get-caller-identity >/dev/null 2>&1; then
        log "AWS CLI autenticado."
    else
        log "AVISO: aws sts get-caller-identity falló. Configurá credenciales antes de restaurar."
    fi

    log "VPS listo para restaurar a demanda (sin sync horario en este servidor)."
}
