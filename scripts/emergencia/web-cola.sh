#!/usr/bin/env bash
# Cola de restauración disparada desde emergencia-restaurar.php cuando
# PHP-FPM no tiene exec/proc_open (DirectAdmin suele cortarlos).
#
# Cron UNA vez por lab (no es sync horario; solo mira si hay pedido):
# * * * * * /bin/bash /home/admin/public_html/ia/SLUG/scripts/emergencia/web-cola.sh
#
# docs/10-backup-y-vps-emergencia.md Paso H

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

cargar_config
prepend_php_path

PEDIDO="${APP_DIR}/storage/logs/restaurar-web.pedido"
LOG="${APP_DIR}/storage/logs/restaurar-emergencia.log"

[[ -f "$PEDIDO" ]] || exit 0

modo="$(head -n 1 "$PEDIDO" | tr -d '[:space:]')"
rm -f "$PEDIDO"

extra=(--yes)
if [[ "$modo" == "dry-run" ]]; then
    extra=(--dry-run --yes)
fi

mkdir -p "$(dirname "$LOG")"
{
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cola web: $SCRIPT_DIR/restaurar.sh ${extra[*]}"
} >> "$LOG"

# restaurar.sh tiene su propio flock; no tomamos el lock acá.
set +e
/bin/bash "$SCRIPT_DIR/restaurar.sh" "${extra[@]}" >> "$LOG" 2>&1
echo "EXIT:$?" >> "$LOG"
exit 0
