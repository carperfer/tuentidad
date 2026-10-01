#!/usr/bin/env bash
# Separa el paquete de build-release.sh en dos carpetas para OVH, donde la
# carpeta web (www/) es fija y no se puede apuntar a public/:
#
#   <destino>/www/  contenido de public/ + .ovhconfig  → se sube a www/
#   <destino>/app/  resto de la aplicación             → se sube fuera de la web
#
# index.php se ajusta para cargar la aplicación desde ../<carpeta_app>/.
#
# Uso: ./scripts/separar-publico.sh <release> <destino> <carpeta_app>
#   p. ej. ./scripts/separar-publico.sh release sitio tuentidad/
set -euo pipefail

RELEASE="${1:?Falta la carpeta del paquete (p. ej. release)}"
DESTINO="${2:?Falta la carpeta de destino (p. ej. sitio)}"
CARPETA_APP="${3:?Falta la carpeta de la aplicación en el servidor (p. ej. tuentidad/)}"
CARPETA_APP="${CARPETA_APP%/}"

if [[ ! "$CARPETA_APP" =~ ^[A-Za-z0-9._-]+$ ]]; then
  echo "La carpeta de la aplicación debe ser un nombre simple junto a www/ (recibido: $CARPETA_APP)" >&2
  exit 1
fi

rm -rf "$DESTINO"
mkdir -p "$DESTINO"
mv "$RELEASE/public" "$DESTINO/www"
mv "$RELEASE/.ovhconfig" "$DESTINO/www/"
mv "$RELEASE" "$DESTINO/app"

ORIGINAL="require FCPATH . '../app/Config/Paths.php';"
AJUSTADO="require FCPATH . '../$CARPETA_APP/app/Config/Paths.php';"
grep -qF "$ORIGINAL" "$DESTINO/www/index.php" || { echo "No se encontró la ruta a Paths.php en index.php" >&2; exit 1; }
sed -i "s#$(printf '%s' "$ORIGINAL" | sed 's/[.[\*^$]/\\&/g')#$AJUSTADO#" "$DESTINO/www/index.php"
grep -qF "$AJUSTADO" "$DESTINO/www/index.php"

echo "✓ $DESTINO/www → carpeta web · $DESTINO/app → ../$CARPETA_APP/"
