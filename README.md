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
- El plan de OVH no tiene SSH: el despliegue se hace por FTP desde GitHub Actions. Versiones de PHP/MySQL pendientes de verificar con `ovh-check.php`

## Arquitectura

- **Backend:** CodeIgniter 4 expuesto únicamente como API REST (JSON) bajo `/api`.
- **Frontend:** SPA en React construida con Vite y servida como estáticos desde el mismo dominio (tuentidad.es).
- **Comunicación:** la SPA consume la API vía HTTP; al compartir dominio no se requiere CORS.
- **Autenticación:** CodeIgniter Shield con sesión por cookie (`HttpOnly`, `Secure`, `SameSite`) y protección CSRF en todas las peticiones que modifican datos.
- **Chat:** polling AJAX periódico contra la API (compatible con hosting compartido, sin WebSockets). El acceso a mensajes se encapsula en un servicio para poder sustituir el transporte en el futuro.

## Desarrollo local

Requisitos: Docker y Node.js 24.

```bash
# Backend (Apache + PHP 8.3) y MySQL
docker compose up -d --build
docker compose exec -u www-data app composer install

# Frontend (SPA con recarga en caliente; /api se redirige al backend)
cd frontend && npm install && npm run dev
```

- SPA: http://localhost:5173
- API: http://localhost:8080/api/health

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

El despliegue es automático: el workflow [`deploy.yml`](.github/workflows/deploy.yml) publica en OVH por FTP en cada push a `main`. También se puede lanzar a mano desde GitHub › Actions › Deploy › *Run workflow*.

Qué se publica lo decide la variable del repositorio `DESPLIEGUE`:

| Valor | Qué se sube |
|---|---|
| `landing` (por defecto) | Solo la página de [`landing/`](landing/index.html) |
| `app` | El paquete de `scripts/build-release.sh` (backend con `vendor/`, SPA compilada) y el `.env` generado a partir de los secrets |

El código fuente, la documentación y la configuración de desarrollo nunca se suben. Las credenciales viven solo en los secrets de GitHub; el `.env` se genera en cada despliegue y nunca pasa por git.

### Configuración en GitHub

En *Settings › Secrets and variables › Actions*:

**Secrets** (necesarios desde ya):

| Secret | Valor |
|---|---|
| `FTP_SERVER` | Servidor FTP (panel OVH › FTP-SSH, p. ej. `ftp.clusterXXX.hosting.ovh.net`) |
| `FTP_USERNAME` | Usuario FTP |
| `FTP_PASSWORD` | Contraseña FTP |

**Secrets de la aplicación** (necesarios al pasar a `DESPLIEGUE=app`). La lista completa, con valores por defecto, se obtiene con `./scripts/configurar-env.sh --variables`:

| Secret | Obligatorio | Por defecto |
|---|---|---|
| `DB_HOSTNAME`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Sí | — |
| `SMTP_PASSWORD` | Sí | — |
| `ENCRYPTION_KEY` | Sí | — (generar una sola vez: `echo "hex2bin:$(openssl rand -hex 32)"`) |
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
| `FTP_SERVER_DIR` | `tuentidad/` | Carpeta del alojamiento donde se sube |
| `FTP_PROTOCOL` | `ftps` | `ftps` (cifrado) o `ftp` si el servidor no admite TLS |

Si faltan los secrets de FTP, el workflow avisa y no despliega. Si falta o es inválido algún secret de la aplicación, falla antes de subir nada.

### Configuración en OVH

- La carpeta raíz del dominio (*Multisitio*) debe ser `tuentidad/public` (o `<FTP_SERVER_DIR>/public`). Así `.env`, `vendor/` y `writable/` quedan fuera de la web.
- Certificado SSL activado: en producción las cookies son `Secure` y el sitio debe servirse por HTTPS.
- Si existía una integración Git con el repositorio, hay que desasociarla para que no compita con el despliegue por FTP.
- OVH lee `.ovhconfig` (PHP 8.3) de la raíz del alojamiento o de la carpeta de primer nivel del multisitio. Si el diagnóstico no muestra PHP 8.3, copia el archivo a la raíz del alojamiento.

### Diagnóstico del alojamiento

Sube `scripts/ovh-check.php` a `tuentidad/public/` con un cliente FTP (FileZilla) y ábrelo en `https://tuentidad.es/ovh-check.php`. Comprueba PHP, extensiones, permisos, `.env`, base de datos y SMTP, y se borra solo al terminar.

### Paquete de la aplicación

`scripts/build-release.sh` genera en `release/` el mismo paquete que publica el workflow en modo `app`:

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
