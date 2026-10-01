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
- Pendiente verificar en el panel de OVH: versiones de PHP/MySQL disponibles y acceso SSH

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

`scripts/build-release.sh` genera en `release/` el paquete para OVH: backend con dependencias de producción, build de la SPA dentro de `public/` y las herramientas de servidor.

```
release/
├── .ovhconfig          # fija PHP 8.3 en OVH
├── .env.example        # referencia de variables de producción
├── configurar-env.sh   # crea/actualiza .env desde la consola SSH
├── ovh-check.php       # diagnóstico de un solo uso
├── app/ public/ vendor/ writable/ …
```

- La carpeta raíz del dominio en OVH debe apuntar a `public/` dentro de la carpeta subida.
- OVH lee `.ovhconfig` de la raíz del alojamiento o de la carpeta de primer nivel del multisitio. Si `ovh-check.php` no muestra PHP 8.3, copia el archivo a la raíz del alojamiento.
- `public/.htaccess` envía `/api/*` a CodeIgniter y el resto de rutas a `index.html`.
- En producción las cookies se marcan como `Secure`, por lo que el sitio debe servirse por HTTPS.

### Primera puesta en marcha

1. Sube el contenido de `release/` al alojamiento (nunca se incluye `.env`, así que las subidas posteriores no lo sobrescriben).
2. Por SSH, en esa carpeta, ejecuta `./configurar-env.sh`: pregunta los datos de base de datos, SMTP y URL, los valida, genera la clave de cifrado y prueba la conexión a MySQL.
3. Copia el diagnóstico a `public/` y ábrelo en el navegador: `cp ovh-check.php public/` → `https://tuentidad.es/ovh-check.php`. Comprueba PHP, extensiones, permisos, base de datos y SMTP, y se borra solo al terminar.

### Cambiar una variable más adelante

```bash
./configurar-env.sh                              # repasa todas; Enter mantiene el valor actual
./configurar-env.sh database.default.password    # cambia solo esa (admite varias)
./configurar-env.sh --listar                     # muestra la configuración con los secretos ocultos
```

Antes de guardar se hace una copia en `.env.bak`. `encryption.key` no se puede cambiar con el script, porque los datos cifrados con la clave anterior dejarían de poder leerse.

## Guía de diseño

El diseño visual (estructura, colores y estilo) debe mantenerse alineado con la experiencia original de tuenti.es.

## Documentación del proceso con IA

Como requisito formativo, el histórico de prompts y la planificación quedan documentados en:

- [`docs/historico-prompts.md`](docs/historico-prompts.md)
- [`docs/planificacion.md`](docs/planificacion.md)
