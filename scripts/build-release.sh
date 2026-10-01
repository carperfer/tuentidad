#!/usr/bin/env bash
# Genera en release/ el paquete listo para subir a OVH:
#   - backend de CodeIgniter con dependencias de producción
#   - build de la SPA copiado dentro de public/
# En OVH, la carpeta raíz del dominio debe apuntar a release/public.
#
# Por defecto usa el composer local. Para usar el del contenedor Docker:
#   COMPOSER_CMD="docker compose run --rm -T -w /var/www/html/release app composer" ./scripts/build-release.sh
set -euo pipefail

COMPOSER_CMD="${COMPOSER_CMD:-composer}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RELEASE="$ROOT/release"

echo "→ Build del frontend"
(cd "$ROOT/frontend" && npm ci && npm run build)

echo "→ Copia del backend"
rm -rf "$RELEASE"
mkdir -p "$RELEASE"
cp -r "$ROOT/backend/app" "$ROOT/backend/public" "$ROOT/backend/composer.json" \
      "$ROOT/backend/composer.lock" "$ROOT/backend/spark" "$ROOT/backend/preload.php" "$RELEASE/"
mkdir -p "$RELEASE/writable"
for dir in cache logs session uploads debugbar; do
  mkdir -p "$RELEASE/writable/$dir"
  cp "$ROOT/backend/writable/index.html" "$RELEASE/writable/$dir/" 2>/dev/null || true
done
cp "$ROOT/backend/writable/.htaccess" "$RELEASE/writable/"

echo "→ Dependencias de producción"
(cd "$RELEASE" && $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-progress)

echo "→ SPA dentro de public/"
cp -r "$ROOT/frontend/dist/." "$RELEASE/public/"

echo "✓ Paquete generado en $RELEASE"
echo "  Recuerda crear release/.env en el servidor (ver backend/env)."
