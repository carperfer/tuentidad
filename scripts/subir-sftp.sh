#!/usr/bin/env bash
# Sube el contenido de una carpeta local a una carpeta del servidor por SFTP.
#
# Uso: ./scripts/subir-sftp.sh <carpeta_local> <carpeta_remota>
#
# Variables de entorno:
#   SFTP_HOST, SFTP_USER, SFTP_PASSWORD  datos de acceso (obligatorias)
#   SFTP_PORT                            puerto (por defecto 22)
#   SFTP_KNOWN_HOSTS                     línea(s) de known_hosts del servidor; si falta,
#                                        se obtiene con ssh-keyscan y se muestra para fijarla
#
# Sube siempre todos los archivos (las fechas del checkout de CI no sirven para
# detectar cambios) y no borra nada del servidor, así que
# conserva lo que genera la aplicación (logs, sesiones, fotos en writable/).
set -euo pipefail

LOCAL="${1:?Falta la carpeta local}"
REMOTO="${2:?Falta la carpeta remota}"
: "${SFTP_HOST:?Falta SFTP_HOST}" "${SFTP_USER:?Falta SFTP_USER}" "${SFTP_PASSWORD:?Falta SFTP_PASSWORD}"
SFTP_PORT="${SFTP_PORT:-22}"

KNOWN_HOSTS="$(mktemp)"
trap 'rm -f "$KNOWN_HOSTS"' EXIT

if [[ -n "${SFTP_KNOWN_HOSTS:-}" ]]; then
  printf '%s\n' "$SFTP_KNOWN_HOSTS" > "$KNOWN_HOSTS"
else
  ssh-keyscan -p "$SFTP_PORT" "$SFTP_HOST" 2> /dev/null > "$KNOWN_HOSTS"
  [[ -s "$KNOWN_HOSTS" ]] || { echo "No se pudo obtener la clave del servidor $SFTP_HOST:$SFTP_PORT" >&2; exit 1; }
  echo "::warning::SFTP_KNOWN_HOSTS no está definida: se confía en la clave recibida. Para fijarla, crea la variable con este contenido:"
  cat "$KNOWN_HOSTS"
fi

export LFTP_PASSWORD="$SFTP_PASSWORD"
SSH="ssh -a -x -p $SFTP_PORT -o UserKnownHostsFile=$KNOWN_HOSTS -o StrictHostKeyChecking=yes -o PubkeyAuthentication=no"

lftp -c "
set cmd:fail-exit yes
set net:max-retries 2
set net:timeout 30
set sftp:auto-confirm no
set xfer:use-temp-file yes
set sftp:connect-program '$SSH'
open --env-password -u '$SFTP_USER' -p '$SFTP_PORT' 'sftp://$SFTP_HOST'
mkdir -p -f '$REMOTO'
mirror --reverse --transfer-all --no-perms --parallel=4 --verbose=1 '${LOCAL%/}/' '$REMOTO'
"
