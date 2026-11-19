# tuentidad

Recreación formativa de la mítica red social tuenti.es en el dominio **tuentidad.es**, usando inteligencia artificial durante todo el proceso.

## Objetivo del proyecto

Construir un ejemplo real y didáctico de la cásica red social, documentando tanto el resultado como el proceso de trabajo con IA para reutilizarlo en futuros desarrollos.

## Alcance funcional

- Red social de acceso por invitación: al registrarse con una invitación, quien entra queda como amigo de quien le invitó
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
- Entorno local con Docker Compose (backend, base de datos, Mailpit y Vite)
- El plan de OVH no tiene consola SSH, pero sí SFTP: el despliegue se hace por SFTP desde GitHub Actions. Verificado con `ovh-check.php`: PHP 8.3 (FPM) y MySQL 8.4 en producción

## Arquitectura

- **Backend:** CodeIgniter 4 expuesto únicamente como API REST (JSON) bajo `/api`.
- **Frontend:** SPA en React construida con Vite y servida como estáticos desde el mismo dominio (tuentidad.es).
- **Comunicación:** la SPA consume la API vía HTTP; al compartir dominio no se requiere CORS.
- **Autenticación:** CodeIgniter Shield con sesión por cookie (`HttpOnly`, `Secure`, `SameSite`) y protección CSRF en todas las peticiones que modifican datos.
- **Chat:** polling AJAX periódico contra la API (compatible con hosting compartido, sin WebSockets). El acceso a mensajes se encapsula en un servicio para poder sustituir el transporte en el futuro.

## Desarrollo local

Requisitos: Docker y Node.js 24.

```bash
# Backend (Apache + PHP 8.3), MySQL, Mailpit y frontend (Vite con recarga en caliente;
# /api se redirige al backend)
docker compose up -d --build
docker compose exec -u www-data app composer install
docker compose exec -u www-data app php spark migrate --all

# Primer usuario (sin invitación); pide los datos que falten
docker compose exec -u www-data app php spark tuentidad:usuario
```

El contenedor `frontend` instala las dependencias al arrancar en su propio volumen de `node_modules`; tras cambiar `package.json`, basta con `docker compose restart frontend`. Para lint, typecheck y tests sigue haciendo falta `npm install` en `frontend/` en la máquina anfitriona.

|                  | En GitHub Codespaces                                 | En local                         |
| ---------------- | ---------------------------------------------------- | -------------------------------- |
| SPA              | `https://<codespace>-5173.app.github.dev`            | http://localhost:5173            |
| API              | `https://<codespace>-8080.app.github.dev/api/health` | http://localhost:8080/api/health |
| Emails (Mailpit) | `https://<codespace>-8025.app.github.dev`            | http://localhost:8025            |

`<codespace>` es el nombre del Codespace (`echo $CODESPACE_NAME`). En desarrollo ningún email sale fuera: todos se capturan en Mailpit. Los enlaces de los emails apuntan automáticamente a la SPA del Codespace (o a `localhost:5173` fuera de él); se pueden forzar con la variable `TUENTIDAD_PUBLIC_URL` al arrancar Docker Compose.

### API

| Método y ruta                                     | Sesión | Descripción                                                                                                                                                        |
| ------------------------------------------------- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `GET /api/csrf`                                   | —      | Token CSRF para las peticiones que modifican datos (cabecera `X-CSRF-TOKEN`)                                                                                       |
| `GET /api/auth/me`                                | —      | Usuario con sesión iniciada o `null`                                                                                                                               |
| `POST /api/auth/login`                            | —      | Iniciar sesión (`email`, `password`, `remember`)                                                                                                                   |
| `POST /api/auth/logout`                           | —      | Cerrar sesión                                                                                                                                                      |
| `POST /api/auth/register`                         | —      | Crear cuenta con una invitación (`token`, `first_name`, `last_name`, `birthdate`, `password`, `password_confirm`, `accept_terms`)                                  |
| `POST /api/auth/forgot-password`                  | —      | Enviar enlace de recuperación (misma respuesta exista o no el email)                                                                                               |
| `POST /api/auth/reset-password`                   | —      | Cambiar la contraseña con el enlace (`token`, `password`, `password_confirm`)                                                                                      |
| `GET /api/invitations/{token}`                    | —      | Datos públicos de una invitación válida (`welcome: true` si viene de un perfil de bienvenida)                                                                      |
| `GET /api/welcome-profiles`                       | —      | Perfiles de bienvenida de la portada                                                                                                                               |
| `POST /api/welcome-profiles/{id}/friend-requests` | —      | Pedir amistad a un perfil de bienvenida (`email`, `accept_privacy`; `website` es un campo trampa). Envía el enlace de registro; al registrarse, quedan como amigos |
| `GET /api/profiles/{id}`                          | Sí     | Ver un perfil; aplica la privacidad de cada dato según la relación de amistad e indica la relación (`relationship`) y la solicitud pendiente (`request_id`)                                                                                      |
| `GET /api/profiles/{id}/avatar`                   | Sí     | Ver la foto solo si la privacidad del perfil lo permite                                                                                                            |
| `PUT /api/profile`                                | Sí     | Actualizar datos personales, estado, biografía y visibilidad (`public`, `friends` o `private`)                                                                     |
| `PUT /api/profile/status`                         | Sí     | Cambiar solo el estado (`status`), desde la barra «¿Cómo va todo?»                                                                                                 |
| `PUT /api/profile/settings`                       | Sí     | Ajustes de la cuenta: emails de resumen (`digest`: `none`, `daily` o `weekly`) |
| `POST /api/profile/avatar`                        | Sí     | Subir foto JPG, PNG o WebP de hasta 2 MB (máximo 4000 × 4000 px)                                                                                                   |
| `GET /api/dashboard`                              | Sí     | Resumen de amistades e invitaciones propias                                                                                                                        |
| `GET /api/account/export`                         | Sí     | Descarga en JSON de todos los datos de la cuenta (RGPD) |
| `DELETE /api/account`                             | Sí     | Borra la cuenta y todos sus datos y fotos (`password`). Cierra la sesión |
| `GET /api/friends`                                | Sí     | Lista de amistades confirmadas                                                                                                                                     |
| `GET /api/friends/search?q=...`                   | Sí     | Busca usuarios por nombre o apellido sin exponer su email                                                                                                          |
| `GET /api/friends/activity`                       | Sí     | Estados visibles publicados por amistades confirmadas                                                                                                              |
| `GET /api/friend-requests`                        | Sí     | Solicitudes recibidas y enviadas                                                                                                                                   |
| `POST /api/friend-requests`                       | Sí     | Envía una solicitud (`user_id`)                                                                                                                                    |
| `POST /api/friend-requests/{id}/accept`           | Sí     | Acepta una solicitud recibida y crea la amistad mutua                                                                                                              |
| `POST /api/friend-requests/{id}/reject`           | Sí     | Rechaza una solicitud recibida                                                                                                                                     |
| `DELETE /api/friend-requests/{id}`                | Sí     | Cancela una solicitud enviada                                                                                                                                      |
| `DELETE /api/friends/{id}`                        | Sí     | Elimina una amistad en ambos sentidos                                                                                                                              |
| `GET /api/users/{id}/friends`                     | Sí     | Amigos de un usuario, con `total`; con `?limit=n`, una muestra al azar. Solo el propio usuario y sus amigos (`403` para los demás)                                 |
| `GET /api/users/{id}/mutual-friends`              | Sí     | Amigos en común con el usuario autenticado, con `total` (`?limit=n` opcional)                                                                                      |
| `GET /api/users/{id}/photos?limit=n`              | Sí     | Últimas fotos de un usuario y su `total` (solo el dueño y sus amigos)                                                                                              |
| `GET /api/users/{id}/albums`                      | Sí     | Álbumes de un usuario con número de fotos y portada; solo el dueño y sus amigos (`403` para los demás)                                                             |
| `POST /api/albums`                                | Sí     | Crear un álbum (`title`, `description`)                                                                                                                            |
| `GET /api/albums/{id}`                            | Sí     | Álbum con sus fotos en orden de subida (`404` si no es tuyo ni de un amigo)                                                                                        |
| `PUT /api/albums/{id}`                            | Sí     | Editar título y descripción de un álbum propio                                                                                                                     |
| `DELETE /api/albums/{id}`                         | Sí     | Borrar un álbum propio con todas sus fotos                                                                                                                         |
| `POST /api/albums/{id}/photos`                    | Sí     | Subir una foto (`photo`): JPG, PNG o WebP de hasta 10 MB y 25 megapíxeles; se guarda en JPEG (1600 px y miniatura de 240 px) sin metadatos EXIF                    |
| `GET /api/photos/{id}/image`                      | Sí     | Foto a tamaño de visor (solo el dueño y sus amigos)                                                                                                                |
| `GET /api/photos/{id}/thumb`                      | Sí     | Miniatura cuadrada (solo el dueño y sus amigos)                                                                                                                    |
| `DELETE /api/photos/{id}`                         | Sí     | Borrar una foto propia                                                                                                                                             |
| `GET /api/photos/{id}`                            | Sí     | Foto con sus etiquetas (zona en % de la imagen) y comentarios; marca como leídos los avisos de esa foto |
| `GET /api/photos/{id}/taggable`                   | Sí     | Personas que puedes etiquetar: tú y tus amigos que pueden ver la foto, salvo los ya etiquetados |
| `POST /api/photos/{id}/tags`                      | Sí     | Etiquetar (`user_id`, `x`, `y`, `width`, `height` en %). Avisa a la persona etiquetada |
| `DELETE /api/photo-tags/{id}`                     | Sí     | Quitar una etiqueta: la persona etiquetada, quien la puso o el dueño de la foto |
| `POST /api/photos/{id}/comments`                  | Sí     | Comentar (`body`, hasta 500 caracteres). Avisa al dueño y a quienes salen en la foto |
| `DELETE /api/photo-comments/{id}`                 | Sí     | Borrar un comentario: su autor o el dueño de la foto |
| `GET /api/users/{id}/tagged-photos`               | Sí     | Fotos en las que sale un usuario, entre las que puede ver quien consulta, con `total` (`?limit=n` opcional) |
| `GET /api/notifications`                          | Sí     | Avisos de 20 en 20 (`?before=<id>` para la página siguiente, `?group=` con `photos`, `friends` o `events` para filtrar), `next_before` y los no leídos por tipo |
| `GET /api/notifications/unread`                   | Sí     | Avisos no leídos: `total` y uno por tipo |
| `POST /api/notifications/read`                    | Sí     | Marcar todos los avisos como leídos |
| `POST /api/notifications/{id}/read`               | Sí     | Marcar un aviso como leído |
| `GET /api/chat`                                   | Sí     | Estado del chat: amigos (primero los conectados), mensajes sin leer de cada uno y totales. Marca al usuario como conectado (2 min) |
| `GET /api/chat/conversations`                     | Sí     | Bandeja de Mensajes: conversaciones con amigos, con el último mensaje y los no leídos |
| `GET /api/chat/{id}/messages`                     | Sí     | Últimos 50 mensajes con un amigo; con `?after=<id>`, los posteriores, y con `?before=<id>`, los 50 anteriores. Marca como leídos los recibidos |
| `POST /api/chat/{id}/messages`                    | Sí     | Enviar un mensaje a un amigo (`body`, hasta 1000 caracteres) |
| `GET /api/videos`                                 | Sí     | Vídeos tuyos y de tus amigos, de 10 en 10 (`?before=<id>`) |
| `GET /api/users/{id}/videos`                      | Sí     | Vídeos de un usuario (solo él y sus amigos), con `total` |
| `POST /api/videos`                                | Sí     | Compartir un vídeo de YouTube (`url`, `title`); solo se guarda el id del vídeo |
| `DELETE /api/videos/{id}`                         | Sí     | Borrar un vídeo propio |
| `GET /api/events`                                 | Sí     | Eventos propios y a los que estás invitado: próximos y pasados de 20 en 20 (`?past_offset=n`, `past_more`) |
| `POST /api/events`                                | Sí     | Crear un evento (`title`, `place`, `description`, `starts_at` en ISO UTC, `invite`: ids de amigos). Avisa a los invitados |
| `GET /api/events/{id}`                            | Sí     | Evento con invitados, respuestas y comentarios (solo quien organiza y sus invitados). Marca como leídos sus avisos |
| `PUT /api/events/{id}`                            | Sí     | Editar un evento propio; si cambia la fecha, se volverá a recordar |
| `DELETE /api/events/{id}`                         | Sí     | Borrar un evento propio |
| `GET /api/events/invitable`                       | Sí     | Amigos que se pueden invitar (`?event=<id>`: los que aún no lo están) |
| `POST /api/events/{id}/invitations`               | Sí     | Invitar a más amigos (`user_ids`) |
| `PUT /api/events/{id}/rsvp`                       | Sí     | Responder (`status`: `yes`, `no` o `maybe`) |
| `POST /api/events/{id}/comments`                  | Sí     | Comentar; avisa a quien organiza y a quienes van o quizás van |
| `DELETE /api/event-comments/{id}`                 | Sí     | Borrar un comentario: su autor o quien organiza |
| `GET /api/calendar`                               | Sí     | Eventos de los próximos 8 días (salvo los que has rechazado) y cumpleaños de amigos que lo dejan ver |
| `GET /api/invitations`                            | Sí     | Invitaciones enviadas y cupo restante                                                                                                                              |
| `POST /api/invitations`                           | Sí     | Invitar por email (reenviar a una pendiente no gasta cupo)                                                                                                         |

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

Si tras reiniciar el Codespace Docker no arranca los contenedores (errores como `RWLayer … is unexpectedly nil` o `failed to extract layer`), recréalos sin tocar el volumen de la base de datos:

```bash
docker compose rm -fs              # elimina los contenedores (los datos siguen en el volumen)
docker compose build --pull app    # solo si el error menciona capas de imagen
docker compose up -d
```

## Despliegue

El despliegue es automático: el workflow [`deploy.yml`](.github/workflows/deploy.yml) publica en OVH por SFTP en cada push a `main` ([`scripts/subir-sftp.sh`](scripts/subir-sftp.sh), con `lftp`). También se puede lanzar a mano desde GitHub › Actions › Deploy › _Run workflow_.

Qué se publica lo decide la variable del repositorio `DESPLIEGUE`:

| Valor                   | Qué se sube                                                                                                                                                                                         |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `landing` (por defecto) | Solo la página de [`landing/`](landing/index.html)                                                                                                                                                  |
| `app`                   | El paquete de `scripts/build-release.sh` (backend con `vendor/`, SPA compilada) y el `.env` generado a partir de los secrets. Después aplica las migraciones y comprueba que la aplicación responde |

Producción usa `DESPLIEGUE=app`: la aplicación está publicada en tuentidad.es (el acceso es solo por invitación).

En OVH la carpeta web del dominio principal es fija (`www/`), así que el despliegue usa dos carpetas del alojamiento:

```
~/www/          ← solo lo público: la landing, o public/ de la app (SPA, index.php, .htaccess, .ovhconfig)
~/tuentidad/    ← la aplicación: app/, vendor/, writable/, .env — fuera de la web
```

En modo `app`, [`scripts/separar-publico.sh`](scripts/separar-publico.sh) divide el paquete y ajusta `index.php` para cargar la aplicación desde `../tuentidad/`. El código fuente, la documentación y la configuración de desarrollo nunca se suben. Las credenciales viven solo en los secrets de GitHub; el `.env` se genera en cada despliegue y nunca pasa por git.

### Configuración en GitHub

En _Settings › Secrets and variables › Actions_:

**Secrets** (necesarios desde ya; se mantiene el prefijo `FTP_` aunque el acceso es por SFTP):

| Secret         | Valor                                                                        |
| -------------- | ---------------------------------------------------------------------------- |
| `FTP_SERVER`   | Servidor SFTP (panel OVH › FTP-SSH, p. ej. `ssh.clusterXXX.hosting.ovh.net`) |
| `FTP_USERNAME` | Usuario FTP/SFTP                                                             |
| `FTP_PASSWORD` | Contraseña FTP/SFTP                                                          |

**Secrets de la aplicación** (necesarios al pasar a `DESPLIEGUE=app`). La lista completa, con valores por defecto, se obtiene con `./scripts/configurar-env.sh --variables`:

| Secret                                                     | Obligatorio | Por defecto                                                        |
| ---------------------------------------------------------- | ----------- | ------------------------------------------------------------------ |
| `DB_HOSTNAME`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Sí          | —                                                                  |
| `SMTP_PASSWORD`                                            | Sí          | —                                                                  |
| `ENCRYPTION_KEY`                                           | Sí          | — (generar una sola vez: `echo "hex2bin:$(openssl rand -hex 32)"`) |
| `DEPLOY_TOKEN`                                             | Sí          | — (generar una sola vez: `openssl rand -hex 32`)                   |
| `APP_BASE_URL`                                             | No          | `https://tuentidad.es/`                                            |
| `DB_PORT`                                                  | No          | `3306`                                                             |
| `EMAIL_FROM`, `EMAIL_FROM_NAME`                            | No          | `no-reply@tuentidad.com`, `tuentidad`                              |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_CRYPTO`                    | No          | `ssl0.ovh.net`, `465`, `ssl`                                       |
| `SMTP_USER`                                                | No          | igual que `EMAIL_FROM`                                             |

`ENCRYPTION_KEY` no debe cambiar nunca una vez en producción: los datos cifrados con la clave anterior dejarían de poder leerse.

**Variables** (opcionales):

| Variable           | Por defecto  | Uso                                                                                                                                     |
| ------------------ | ------------ | --------------------------------------------------------------------------------------------------------------------------------------- |
| `DESPLIEGUE`       | `landing`    | `landing` o `app`                                                                                                                       |
| `FTP_PUBLIC_DIR`   | `www/`       | Carpeta web del alojamiento                                                                                                             |
| `FTP_SERVER_DIR`   | `tuentidad/` | Carpeta de la aplicación, junto a `www/` (nombre simple)                                                                                |
| `SFTP_PORT`        | `22`         | Puerto SFTP                                                                                                                             |
| `SFTP_KNOWN_HOSTS` | —            | Clave pública del servidor (formato `known_hosts`). Si falta, se acepta la que presente el servidor y se muestra en el log para fijarla |

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
- `POST /api/deploy/backup`: copia de la base de datos (ver abajo). El workflow la hace antes de migrar.
- `POST /api/deploy/reminders`: recordatorios de los eventos de las próximas 24 horas (aviso en la web y email a quienes van o quizás van). Lo lanza cada hora el workflow **Recordatorios de eventos**; cada evento se recuerda una sola vez.
- `POST /api/deploy/digests`: emails de resumen (avisos y mensajes sin leer) para quien los ha activado, cada día o cada semana, solo si hay novedades. Lo lanza cada día a las 07:00 UTC el workflow **Resumen por email**.
- `POST /api/deploy/first-user`: crea el primer usuario. Solo funciona mientras no exista ninguno; se lanza una vez a mano:

```bash
read -rs DEPLOY_TOKEN   # pega el token (no se muestra)
curl -X POST https://tuentidad.es/api/deploy/first-user \
  -H "Authorization: Bearer $DEPLOY_TOKEN" -H 'Content-Type: application/json' \
  -d '{"email":"tu@email.com","first_name":"Nombre","last_name":"Apellidos","birthdate":"AAAA-MM-DD","password":"…"}'
```

### Copias de seguridad

La aplicación vuelca la base de datos en `tuentidad/writable/backups/tuentidad-AAAAMMDD-HHMMSS.sql.gz` (fuera de la web) y conserva las 14 más recientes. Se hacen:

- en cada despliegue, antes de aplicar las migraciones (si la copia falla, no se migra);
- cada día a las 03:30 UTC con el workflow **Copia de seguridad**, que también se puede lanzar a mano (Actions → Copia de seguridad → Run workflow).

Las copias no se suben a GitHub: en un repositorio público, cualquiera podría descargar los artefactos de un workflow. Para sacar una, descárgala por SFTP. Para restaurarla, impórtala desde phpMyAdmin (panel de OVH → Bases de datos), que acepta el `.sql.gz` tal cual, o con `gunzip -c copia.sql.gz | mysql …`. La copia borra y vuelve a crear cada tabla.

Las fotos (`writable/uploads`) no entran en estas copias; el despliegue nunca borra nada del servidor, y OVH guarda sus propias copias del alojamiento (conviene comprobar en el panel cuántos días).

### Diagnóstico del alojamiento

Sube `scripts/ovh-check.php` a `www/` con un cliente SFTP (FileZilla), o lanza a mano el workflow **Deploy** (Actions → Deploy → Run workflow) marcando «Publicar también ovh-check.php», y ábrelo en `https://tuentidad.es/ovh-check.php`. Comprueba PHP, extensiones, permisos, `.env`, base de datos y SMTP, y se borra solo al terminar.

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

---

## Inicio rápido en GitHub Codespaces

La forma más sencilla de empezar a desarrollar es usar **GitHub Codespaces**, que levanta un entorno preconfigurado en la nube sin necesidad de instalar nada en tu máquina.

### 1. Abrir el Codespace

En la página principal del repositorio, haz clic en el botón verde **Code → Codespaces → Create codespace on `feature/…`**.

El devcontainer incluye:

- **Node.js 22 LTS** (para el frontend React)
- **PHP 8.3** (para el backend)
- **MySQL** (base de datos local)
- Extensiones de VS Code: ESLint, Prettier, Intelephense (PHP), GitHub Copilot

### 2. Configurar variables de entorno

```bash
# Copia la plantilla de variables de entorno
cp .env.example .env

# Edita .env con los valores de desarrollo
# (la BD local del Codespace ya está disponible en 127.0.0.1:3306)
```

Los secretos necesarios en Codespaces (como `DB_PASSWORD`) se configuran en:
**Settings del repositorio → Codespaces → Secrets**

### 3. Iniciar el proyecto

```bash
# Si tienes package.json (frontend React):
npm install
npm run dev

# Si tienes composer.json (backend PHP):
composer install
php -S 0.0.0.0:8080 -t public/
```

Los puertos 5173 (frontend), 8080 (backend) y 3306 (MySQL) se redirigen automáticamente.

### 4. Trabajar en una rama feature

Nunca trabajes directamente en `main`. Crea siempre una rama de trabajo:

```bash
git checkout -b feature/nombre-de-tu-tarea
```

Consulta el [flujo de desarrollo completo](docs/development-workflow.md) para más detalles.

---

## Documentación operativa

| Documento                                                | Descripción                                                         |
| -------------------------------------------------------- | ------------------------------------------------------------------- |
| [Flujo de desarrollo](docs/development-workflow.md)      | Estrategia de ramas, proceso de PR y reglas de protección de `main` |
| [Acceso SSH a producción](docs/production-ssh-access.md) | Procedimiento seguro para operaciones puntuales en producción       |
| [Variables de entorno](.env.example)                     | Plantilla de configuración para desarrollo, staging y producción    |

---

## CI / Integración continua

Cada Pull Request hacia `main` ejecuta automáticamente el workflow [`.github/workflows/ci.yml`](.github/workflows/ci.yml), que valida:

1. Detección del tipo de proyecto (Node.js / PHP)
2. Instalación de dependencias
3. Lint, tests y build (si existen scripts definidos)

El merge solo es posible cuando CI está en verde y hay al menos una aprobación.

---

## Documentación del proceso con IA

Como requisito formativo, el histórico de prompts y la planificación quedan documentados en:

- [`docs/historico-prompts.md`](docs/historico-prompts.md)
- [`docs/planificacion.md`](docs/planificacion.md)
