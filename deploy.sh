#!/usr/bin/env bash
#
# Deploy de todos los colegios por git pull.
#
# Cada colegio es un clon directo de este repo (la app esta en la raiz del
# clon, ej: public_html/ia/25demayo). Este script recorre todas las carpetas
# de colegios y actualiza solo lo que cambio.
#
# Uso:
#   bash deploy.sh            # toma como base la carpeta padre de este script
#   bash deploy.sh /ruta/ia   # o se le pasa la carpeta que contiene los colegios
#
# Conserva intactos .env, storage/, vendor/ y public/build de cada colegio
# (git pull no toca archivos ignorados / no versionados).
#
set -uo pipefail

# Carpeta que contiene las carpetas de los colegios.
# Por defecto: la carpeta padre de donde vive este script.
BASE="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

echo "== Deploy de colegios en: $BASE =="

errores=0
for dir in "$BASE"/*/; do
  [ -d "${dir}.git" ] || continue   # solo carpetas que son clones git
  echo ">>> $dir"
  if ! git -C "$dir" pull --ff-only; then
    echo "   !! Fallo git pull en $dir"
    errores=$((errores + 1))
    continue
  fi
  php "${dir}artisan" config:clear >/dev/null 2>&1 || true
  php "${dir}artisan" view:clear  >/dev/null 2>&1 || true
done

echo "== Deploy completo (errores: $errores) =="
echo "Si cambio composer.lock, en cada colegio: php composer.phar install --no-dev -o"
