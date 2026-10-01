# tuentidad

Recreación formativa de la mítica red social tuenti.es en el dominio **tuentidad.es**, usando inteligencia artificial durante todo el proceso.

## Objetivo del proyecto

Construir un ejemplo real y didáctico de la cásica red social, documentando tanto el resultado como el proceso de trabajo con IA para reutilizarlo en futuros desarrollos.

## Alcance funcional

- Red social de acceso por invitación
- Perfiles de bienvenida en la landing: 2 perfiles de demostración visibles públicamente a los que cualquier visitante puede solicitar amistad indicando su email; recibe una invitación para registrarse y, al completar el registro, queda como amigo de ese perfil
- Enfoque en amigos reales
- Subida y gestión de álbumes de fotos
- Etiquetado de amigos en fotos
- Comentarios en fotos
- Chat integrado
- Eventos para organizar cumpleaños, fiestas o quedadas

## Stack tecnológico

- **Backend:** PHP con CodeIgniter 4
- **Frontend:** React (arquitectura por componentes)
- **Base de datos:** MySQL
- **Hosting:** Web Cloud Hosting de OVH

## Versiones y entorno

- PHP 8.3 · CodeIgniter 4.7 · MySQL 8.0
- React 19 + Vite + TypeScript · React Router · Vitest · oxlint
- Entorno local con Docker Compose (backend y base de datos) y Vite en local
- El plan de OVH no tiene consola SSH, pero sí SFTP: el despliegue se hace por SFTP desde GitHub Actions. Versiones de PHP/MySQL pendientes de verificar con `ovh-check.php`

## Arquitectura

- **Backend:** CodeIgniter 4 expuesto únicamente como API REST (JSON) bajo `/api`.
- **Frontend:** SPA en React construida con Vite y servida como estáticos desde el mismo dominio (tuentidad.es).
- **Comunicación:** la SPA consume la API vía HTTP; al compartir dominio no se requiere CORS.
- **Autenticación:** CodeIgniter Shield con sesión por cookie (`HttpOnly`, `Secure`, `SameSite`) y protección CSRF en todas las peticiones que modifican datos.
- **Chat:** polling AJAX periódico contra la API (compatible con hosting compartido, sin WebSockets). El acceso a mensajes se encapsula en un servicio para poder sustituir el transporte en el futuro.

## Desarrollo local

Requisitos: Docker y Node.js 24.

```bash
# Backend (Apache + PHP 8.3), MySQL y Mailpit
docker compose up -d --build
docker compose exec -u www-data app composer install
docker compose exec -u www-data app php spark migrate --all

# Primer usuario (sin invitación); pide los datos que falten
docker compose exec -u www-data app php spark tuentidad:usuario

# Frontend (SPA con recarga en caliente; /api se redirige al backend)
cd frontend && npm install && npm run dev
```

| | En GitHub Codespaces | En local |
|---|---|---|
| SPA | `https://<codespace>-5173.app.github.dev` | http://localhost:5173 |
| API | `https://<codespace>-8080.app.github.dev/api/health` | http://localhost:8080/api/health |
| Emails (Mailpit) | `https://<codespace>-8025.app.github.dev` | http://localhost:8025 |

`<codespace>` es el nombre del Codespace (`echo $CODESPACE_NAME`). En desarrollo ningún email sale fuera: todos se capturan en Mailpit. Los enlaces de los emails apuntan automáticamente a la SPA del Codespace (o a `localhost:5173` fuera de él); se pueden forzar con la variable `TUENTIDAD_PUBLIC_URL` al arrancar Docker Compose.

### API

| Método y ruta | Sesión | Descripción |
|---|---|---|
| `GET /api/csrf` | — | Token CSRF para las peticiones que modifican datos (cabecera `X-CSRF-TOKEN`) |
| `GET /api/auth/me` | — | Usuario con sesión iniciada o `null` |
| `POST /api/auth/login` | — | Iniciar sesión (`email`, `password`, `remember`) |
| `POST /api/auth/logout` | — | Cerrar sesión |
| `POST /api/auth/register` | — | Crear cuenta con una invitación (`token`, `first_name`, `last_name`, `birthdate`, `password`, `password_confirm`, `accept_terms`) |
| `POST /api/auth/forgot-password` | — | Enviar enlace de recuperación (misma respuesta exista o no el email) |
| `POST /api/auth/reset-password` | — | Cambiar la contraseña con el enlace (`token`, `password`, `password_confirm`) |
| `GET /api/invitations/{token}` | — | Datos públicos de una invitación válida |
| `GET /api/invitations` | Sí | Invitaciones enviadas y cupo restante |
| `POST /api/invitations` | Sí | Invitar por email (reenviar a una pendiente no gasta cupo) |

Errores: `401` sin sesión, `404` token no válido o caducado, `422` validación (`errors` por campo), `429` demasiados intentos. Login, registro, invitaciones y recuperación de contraseña tienen límite de peticiones.

### Comprobaciones

```bash
docker compose exec -u www-data app vendor/bin/phpunit   # tests backend
docker compose exec -u www-data app composer cs          # estilo PHP
cd frontend && npm run lint && npm run typecheck && npm test
```

La CI de GitHub Actions ejecuta lo mismo en cada push y PR.

### Nota para GitHub Codespaces

El Codespace tiene reglas `iptables-legacy` que bloquean el tráfico de las redes que crea Docker Compose. Si la API responde `"database": "error"`, permite el tráfico entre contenedores (la regla se pierde al reiniciar el Codespace):

```bash
sudo iptables-legacy -I FORWARD -i br-+ -o br-+ -j ACCEPT
```

Esas redes tampoco tienen salida a Internet, por eso `composer install` se ejecuta con `docker run` o desde el contenedor ya levantado, y Vite se ejecuta fuera de Docker.

## Despliegue

El despliegue es automático: el workflow [`deploy.yml`](.github/workflows/deploy.yml) publica en OVH por SFTP en cada push a `main` ([`scripts/subir-sftp.sh`](scripts/subir-sftp.sh), con `lftp`). También se puede lanzar a mano desde GitHub › Actions › Deploy › *Run workflow*.

Qué se publica lo decide la variable del repositorio `DESPLIEGUE`:

| Valor | Qué se sube |
|---|---|
| `landing` (por defecto) | Solo la página de [`landing/`](landing/index.html) |
| `app` | El paquete de `scripts/build-release.sh` (backend con `vendor/`, SPA compilada) y el `.env` generado a partir de los secrets. Después aplica las migraciones y comprueba que la aplicación responde |

Producción usa `DESPLIEGUE=app`: la aplicación está publicada en tuentidad.es (el acceso es solo por invitación).

En OVH la carpeta web del dominio principal es fija (`www/`), así que el despliegue usa dos carpetas del alojamiento:

```
~/www/          ← solo lo público: la landing, o public/ de la app (SPA, index.php, .htaccess, .ovhconfig)
~/tuentidad/    ← la aplicación: app/, vendor/, writable/, .env — fuera de la web
```

En modo `app`, [`scripts/separar-publico.sh`](scripts/separar-publico.sh) divide el paquete y ajusta `index.php` para cargar la aplicación desde `../tuentidad/`. El código fuente, la documentación y la configuración de desarrollo nunca se suben. Las credenciales viven solo en los secrets de GitHub; el `.env` se genera en cada despliegue y nunca pasa por git.

### Configuración en GitHub

En *Settings › Secrets and variables › Actions*:

**Secrets** (necesarios desde ya; se mantiene el prefijo `FTP_` aunque el acceso es por SFTP):

| Secret | Valor |
|---|---|
| `FTP_SERVER` | Servidor SFTP (panel OVH › FTP-SSH, p. ej. `ssh.clusterXXX.hosting.ovh.net`) |
| `FTP_USERNAME` | Usuario FTP/SFTP |
| `FTP_PASSWORD` | Contraseña FTP/SFTP |

**Secrets de la aplicación** (necesarios al pasar a `DESPLIEGUE=app`). La lista completa, con valores por defecto, se obtiene con `./scripts/configurar-env.sh --variables`:

| Secret | Obligatorio | Por defecto |
|---|---|---|
| `DB_HOSTNAME`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Sí | — |
| `SMTP_PASSWORD` | Sí | — |
| `ENCRYPTION_KEY` | Sí | — (generar una sola vez: `echo "hex2bin:$(openssl rand -hex 32)"`) |
| `DEPLOY_TOKEN` | Sí | — (generar una sola vez: `openssl rand -hex 32`) |
| `APP_BASE_URL` | No | `https://tuentidad.es/` |
| `DB_PORT` | No | `3306` |
| `EMAIL_FROM`, `EMAIL_FROM_NAME` | No | `no-reply@tuentidad.es`, `tuentidad` |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_CRYPTO` | No | `ssl0.ovh.net`, `465`, `ssl` |
| `SMTP_USER` | No | igual que `EMAIL_FROM` |

`ENCRYPTION_KEY` no debe cambiar nunca una vez en producción: los datos cifrados con la clave anterior dejarían de poder leerse.

**Variables** (opcionales):

| Variable | Por defecto | Uso |
|---|---|---|
| `DESPLIEGUE` | `landing` | `landing` o `app` |
| `FTP_PUBLIC_DIR` | `www/` | Carpeta web del alojamiento |
| `FTP_SERVER_DIR` | `tuentidad/` | Carpeta de la aplicación, junto a `www/` (nombre simple) |
| `SFTP_PORT` | `22` | Puerto SFTP |
| `SFTP_KNOWN_HOSTS` | — | Clave pública del servidor (formato `known_hosts`). Si falta, se acepta la que presente el servidor y se muestra en el log para fijarla |

Si faltan los secrets de acceso, el workflow avisa y no despliega. Si falta o es inválido algún secret de la aplicación, falla antes de subir nada.

### Configuración en OVH

- La carpeta raíz del dominio se mantiene en `www/` (no se puede cambiar en el dominio principal).
- La integración Git de OVH debe estar desactivada para que no compita con el despliegue por SFTP.
- Certificado SSL activado: en producción las cookies son `Secure` y el sitio debe servirse por HTTPS.
- `.ovhconfig` (PHP 8.3) se sube a `www/` junto con la app. Si el diagnóstico no muestra PHP 8.3, revisa el `.ovhconfig` de la raíz del alojamiento.
- Los archivos ocultos (`.env`, `.ovhconfig`, …) están bloqueados por OVH y por `public/.htaccess`.

### Tareas sin consola: migraciones y primer usuario

El alojamiento no tiene SSH, así que lo que normalmente se haría con `php spark` se lanza por HTTP con el token de despliegue (`Authorization: Bearer <DEPLOY_TOKEN>`). Sin `tuentidad.deployToken` en el `.env`, estos endpoints responden 404.

- `POST /api/deploy/migrate`: aplica las migraciones pendientes. El workflow lo llama en cada despliegue.
- `POST /api/deploy/first-user`: crea el primer usuario. Solo funciona mientras no exista ninguno; se lanza una vez a mano:

```bash
read -rs DEPLOY_TOKEN   # pega el token (no se muestra)
curl -X POST https://tuentidad.es/api/deploy/first-user \
  -H "Authorization: Bearer $DEPLOY_TOKEN" -H 'Content-Type: application/json' \
  -d '{"email":"tu@email.com","first_name":"Nombre","last_name":"Apellidos","birthdate":"AAAA-MM-DD","password":"…"}'
```

### Diagnóstico del alojamiento

Sube `scripts/ovh-check.php` a `www/` con un cliente SFTP (FileZilla) y ábrelo en `https://tuentidad.es/ovh-check.php`. Comprueba PHP, extensiones, permisos, `.env`, base de datos y SMTP, y se borra solo al terminar.

### Paquete de la aplicación

`scripts/build-release.sh` genera en `release/` el paquete que el workflow publica en modo `app` (después de separarlo con `separar-publico.sh`):

```
release/
├── .ovhconfig          # fija PHP 8.3 en OVH
├── .env.example        # referencia de variables de producción
├── configurar-env.sh   # genera .env (interactivo o --desde-entorno)
├── ovh-check.php       # diagnóstico de un solo uso
├── app/ public/ vendor/ writable/ …
```

`public/.htaccess` envía `/api/*` a CodeIgniter y el resto de rutas a `index.html`.

`configurar-env.sh` también sirve en local: sin argumentos pregunta cada valor (útil para preparar un `.env` a mano), `--listar` muestra uno existente con los secretos ocultos y `--desde-entorno` es el modo que usa el workflow.

## Guía de diseño

El diseño visual (estructura, colores y estilo) debe mantenerse alineado con la experiencia original de tuenti.es.

## Documentación del proceso con IA

Como requisito formativo, el histórico de prompts y la planificación quedan documentados en:

- [`docs/historico-prompts.md`](docs/historico-prompts.md)
- [`docs/planificacion.md`](docs/planificacion.md)
