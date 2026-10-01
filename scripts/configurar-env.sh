#!/usr/bin/env bash
# Crea o actualiza el .env de producción de tuentidad en el servidor.
#
# Uso (desde la consola SSH, en la carpeta donde está desplegada la aplicación):
#   ./configurar-env.sh                 pregunta todas las variables; Enter mantiene el valor actual
#   ./configurar-env.sh CLAVE [CLAVE…]  pregunta solo esas variables (p. ej. database.default.password)
#   ./configurar-env.sh --listar        muestra la configuración actual con los secretos ocultos
#   ./configurar-env.sh --ayuda         muestra esta ayuda
#
# El archivo se guarda junto a este script (o en $ENV_FILE) con permisos 600.
# Antes de sobrescribirlo se guarda una copia en .env.bak.
# Las líneas del .env que este script no gestiona se conservan tal cual.
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${ENV_FILE:-$DIR/.env}"

# Variables que se preguntan: clave|descripción|valor por defecto|tipo
# Tipos: texto, secreto, url, numero, email, opcion:a,b
PREGUNTAS=(
  "app.baseURL|URL pública del sitio|https://tuentidad.es/|url"
  "database.default.hostname|Servidor MySQL (panel OVH › Bases de datos)||texto"
  "database.default.database|Nombre de la base de datos||texto"
  "database.default.username|Usuario de la base de datos||texto"
  "database.default.password|Contraseña de la base de datos||secreto"
  "database.default.port|Puerto MySQL|3306|numero"
  "email.fromEmail|Email remitente|no-reply@tuentidad.es|email"
  "email.fromName|Nombre del remitente|tuentidad|texto"
  "email.SMTPHost|Servidor SMTP|ssl0.ovh.net|texto"
  "email.SMTPUser|Usuario SMTP (normalmente el email completo)||email"
  "email.SMTPPass|Contraseña SMTP||secreto"
  "email.SMTPPort|Puerto SMTP|465|numero"
  "email.SMTPCrypto|Cifrado SMTP|ssl|opcion:ssl,tls"
)

# Variables fijas: se escriben si no existen, no se preguntan
FIJAS=(
  "CI_ENVIRONMENT|production"
  "database.default.DBDriver|MySQLi"
  "database.default.charset|utf8mb4"
  "database.default.DBCollat|utf8mb4_unicode_ci"
  "email.protocol|smtp"
)

declare -A VALORES=()
declare -A CAMBIADAS=()
declare -a ORDEN_NUEVAS=()

ayuda() {
  awk 'NR > 1 && /^#/ { sub(/^# ?/, ""); print; next } NR > 1 { exit }' "$0"
}

# --- Lectura y escritura del formato .env de CodeIgniter ---------------------

# Convierte el valor tal como está en el archivo al valor real
decodificar() {
  local crudo="$1" comilla
  if [[ "$crudo" == \'* || "$crudo" == \"* ]]; then
    comilla="${crudo:0:1}"
    crudo="${crudo:1}"
    crudo="${crudo%"$comilla"*}"
    crudo="${crudo//\\$comilla/$comilla}"
    crudo="${crudo//\\\\/\\}"
  else
    crudo="${crudo%% #*}"
  fi
  printf '%s' "$crudo"
}

# Convierte un valor real a la forma segura para el archivo: entre comillas simples
codificar() {
  local valor="$1"
  valor="${valor//\\/\\\\}"
  valor="${valor//\'/\\\'}"
  printf "'%s'" "$valor"
}

cargar() {
  [[ -f "$ENV_FILE" ]] || return 0
  local linea clave valor
  while IFS= read -r linea || [[ -n "$linea" ]]; do
    [[ "$linea" =~ ^[[:space:]]*# || "$linea" != *=* ]] && continue
    clave="${linea%%=*}"
    clave="${clave#"${clave%%[![:space:]]*}"}"
    clave="${clave%"${clave##*[![:space:]]}"}"
    valor="${linea#*=}"
    valor="${valor#"${valor%%[![:space:]]*}"}"
    valor="${valor%"${valor##*[![:space:]]}"}"
    VALORES["$clave"]="$(decodificar "$valor")"
  done < "$ENV_FILE"
}

fijar() {
  local clave="$1" valor="$2"
  if [[ -z "${VALORES[$clave]+x}" ]]; then
    ORDEN_NUEVAS+=("$clave")
  fi
  VALORES["$clave"]="$valor"
  CAMBIADAS["$clave"]=1
}

guardar() {
  local tmp linea clave
  tmp="$(mktemp "${ENV_FILE}.XXXXXX")"
  chmod 600 "$tmp"

  if [[ -f "$ENV_FILE" ]]; then
    while IFS= read -r linea || [[ -n "$linea" ]]; do
      if [[ ! "$linea" =~ ^[[:space:]]*# && "$linea" == *=* ]]; then
        clave="${linea%%=*}"
        clave="${clave//[[:space:]]/}"
        if [[ -n "${CAMBIADAS[$clave]+x}" ]]; then
          printf '%s = %s\n' "$clave" "$(codificar "${VALORES[$clave]}")" >> "$tmp"
          continue
        fi
      fi
      printf '%s\n' "$linea" >> "$tmp"
    done < "$ENV_FILE"
    cp -p "$ENV_FILE" "$ENV_FILE.bak"
    chmod 600 "$ENV_FILE.bak"
  else
    printf '# Configuración de producción de tuentidad (generado por configurar-env.sh)\n' >> "$tmp"
  fi

  for clave in "${ORDEN_NUEVAS[@]}"; do
    printf '%s = %s\n' "$clave" "$(codificar "${VALORES[$clave]}")" >> "$tmp"
  done

  mv "$tmp" "$ENV_FILE"
}

# --- Preguntas ---------------------------------------------------------------

definicion() {
  local entrada
  for entrada in "${PREGUNTAS[@]}"; do
    if [[ "${entrada%%|*}" == "$1" ]]; then
      printf '%s' "$entrada"
      return 0
    fi
  done
  return 1
}

validar() {
  local tipo="$1" valor="$2"
  case "$tipo" in
    url)    [[ "$valor" =~ ^https?://[^[:space:]]+/$ ]] || { echo "  Debe empezar por http(s):// y terminar en /"; return 1; } ;;
    numero) [[ "$valor" =~ ^[0-9]+$ ]] || { echo "  Debe ser un número"; return 1; } ;;
    email)  [[ "$valor" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || { echo "  No parece un email válido"; return 1; } ;;
    opcion:*)
      local opciones=",${tipo#opcion:},"
      [[ "$opciones" == *",$valor,"* ]] || { echo "  Opciones válidas: ${tipo#opcion:}"; return 1; } ;;
  esac
  return 0
}

preguntar() {
  local clave descripcion defecto tipo actual valor repetido
  IFS='|' read -r clave descripcion defecto tipo <<< "$1"

  actual="${VALORES[$clave]-}"
  # Sugerencias que dependen de otras variables
  if [[ "$clave" == "email.SMTPUser" && -z "$defecto" ]]; then
    defecto="${VALORES[email.fromEmail]-}"
  fi

  while true; do
    if [[ "$tipo" == "secreto" ]]; then
      if [[ -n "$actual" ]]; then
        read -r -s -p "$descripcion [guardada, Enter para mantener]: " valor; echo
        [[ -z "$valor" ]] && return 0
      else
        read -r -s -p "$descripcion: " valor; echo
        [[ -z "$valor" ]] && { echo "  Este valor es obligatorio"; continue; }
      fi
      read -r -s -p "Repite la contraseña: " repetido; echo
      [[ "$valor" == "$repetido" ]] || { echo "  No coinciden, vuelve a intentarlo"; continue; }
    else
      local sugerido="${actual:-$defecto}"
      read -r -p "$descripcion${sugerido:+ [$sugerido]}: " valor
      valor="${valor:-$sugerido}"
      [[ -z "$valor" ]] && { echo "  Este valor es obligatorio"; continue; }
      [[ "$tipo" == "url" && "$valor" != */ ]] && valor="$valor/"
      validar "$tipo" "$valor" || continue
      [[ "$valor" == "$actual" ]] && return 0
    fi
    fijar "$clave" "$valor"
    return 0
  done
}

clave_cifrado() {
  [[ -n "${VALORES[encryption.key]-}" ]] && return 0
  local hex
  if command -v php > /dev/null; then
    hex="$(php -r 'echo bin2hex(random_bytes(32));')"
  elif command -v openssl > /dev/null; then
    hex="$(openssl rand -hex 32)"
  else
    hex="$(od -An -tx1 -N32 /dev/urandom | tr -d ' \n')"
  fi
  fijar "encryption.key" "hex2bin:$hex"
  echo "Generada una clave de cifrado nueva (encryption.key)."
}

probar_bd() {
  command -v php > /dev/null || return 0
  local respuesta
  read -r -p "¿Probar la conexión a la base de datos? [S/n]: " respuesta
  [[ "$respuesta" =~ ^[nN] ]] && return 0

  # shellcheck disable=SC2016 # código PHP: las $ no son de bash
  DB_HOST="${VALORES[database.default.hostname]}" \
  DB_NAME="${VALORES[database.default.database]}" \
  DB_USER="${VALORES[database.default.username]}" \
  DB_PASS="${VALORES[database.default.password]}" \
  DB_PORT="${VALORES[database.default.port]}" \
  php -r '
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASS"), getenv("DB_NAME"), (int) getenv("DB_PORT"));
    if ($db->connect_errno) { fwrite(STDERR, "✗ No se pudo conectar: {$db->connect_error}\n"); exit(1); }
    echo "✓ Conexión correcta (MySQL {$db->server_info})\n";
  ' || echo "  Revisa los datos con: $0 database.default.hostname database.default.password …"
}

listar() {
  cargar
  if [[ ${#VALORES[@]} -eq 0 ]]; then
    echo "No existe $ENV_FILE o está vacío. Ejecuta $0 para crearlo."
    return 0
  fi
  local clave
  for clave in $(printf '%s\n' "${!VALORES[@]}" | sort); do
    if [[ "$clave" =~ (password|Pass|key)$ ]]; then
      printf '%-28s = ********\n' "$clave"
    else
      printf '%-28s = %s\n' "$clave" "${VALORES[$clave]}"
    fi
  done
}

# --- Programa principal ------------------------------------------------------

case "${1-}" in
  -h|--ayuda|--help) ayuda; exit 0 ;;
  -l|--listar)       listar; exit 0 ;;
esac

cargar

if [[ $# -eq 0 ]]; then
  echo "Configuración de $ENV_FILE"
  echo "Pulsa Enter para aceptar el valor entre corchetes."
  echo
fi

for entrada in "${FIJAS[@]}"; do
  clave="${entrada%%|*}"
  [[ -z "${VALORES[$clave]+x}" ]] && fijar "$clave" "${entrada#*|}"
done
clave_cifrado

if [[ $# -gt 0 ]]; then
  for clave in "$@"; do
    if [[ "$clave" == "encryption.key" ]]; then
      echo "encryption.key no se puede cambiar con este script: los datos cifrados con la clave anterior dejarían de poder leerse." >&2
      exit 1
    fi
    entrada="$(definicion "$clave")" || { echo "Variable desconocida: $clave (usa --listar para ver las disponibles)" >&2; exit 1; }
    preguntar "$entrada"
  done
else
  for entrada in "${PREGUNTAS[@]}"; do
    preguntar "$entrada"
  done
fi

if [[ ${#CAMBIADAS[@]} -eq 0 ]]; then
  echo "Sin cambios."
  exit 0
fi

guardar
echo "✓ Guardado en $ENV_FILE (${#CAMBIADAS[@]} variable(s) actualizada(s))."

for clave in "${!CAMBIADAS[@]}"; do
  if [[ "$clave" == database.default.* ]]; then
    probar_bd
    break
  fi
done
