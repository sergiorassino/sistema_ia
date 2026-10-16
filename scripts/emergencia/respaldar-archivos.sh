#!/usr/bin/env bash
# Compatibilidad: el cron unificado es respaldar.sh (dump MySQL + archivos).
# Este archivo reenvía los argumentos para no romper crons ya cargados.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "[$(TZ="${SE_TZ:-America/Argentina/Buenos_Aires}" date '+%Y-%m-%d %H:%M:%S')] AVISO: usá respaldar.sh. Este script ahora hace dump + archivos. Desactivá el cron viejo de dumps." >&2
exec /bin/bash "$SCRIPT_DIR/respaldar.sh" "$@"
