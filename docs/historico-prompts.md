# Histórico de prompts (IA)

Este documento recoge el histórico de prompts relevantes del proyecto para su reutilización con fines formativos.

## Formato de registro

Para cada entrada registrar:

1. Fecha y hora (UTC)
2. Objetivo del prompt
3. Prompt utilizado
4. Resultado esperado
5. Resultado obtenido
6. Decisión tomada tras el resultado

## Entradas

### 2026-07-02T07:38:47Z — Definición de alcance base del proyecto

- **Objetivo:** Establecer requisitos funcionales, técnicos y de diseño de la recreación de tuenti.es en tuentidad.es.
- **Prompt utilizado:** Planteamiento inicial del proyecto (recreación formativa con IA, funcionalidades sociales, stack PHP/React/MySQL, hosting OVH y diseño basado en tuenti.es).
- **Resultado esperado:** Documento de base con alcance claro y trazable para futuras iteraciones.
- **Resultado obtenido:** Se consolidan y documentan requisitos en el README y en la planificación del proyecto.
- **Decisión:** Mantener este registro como primera entrada y ampliar el histórico en cada iteración futura.

### 2026-10-01 — Refinamiento del stack: CodeIgniter 4

- **Objetivo:** Concretar el framework de backend antes de planificar el desarrollo.
- **Prompt utilizado:** "Refinamos el stack tecnologico: PHP con Codeigniter 4. ¿Algo más que refinar antes de ir a planificación del desarrollo?"
- **Resultado esperado:** Stack actualizado y lista de decisiones técnicas pendientes.
- **Resultado obtenido:** Se fija CodeIgniter 4 como framework backend y se identifican decisiones abiertas (integración React/CI4, chat en hosting compartido, autenticación, versiones, despliegue, RGPD).
- **Decisión:** Resolver las decisiones abiertas antes de detallar la planificación.

### 2026-10-01 — Arquitectura: API REST + SPA

- **Objetivo:** Decidir la integración entre React y CodeIgniter 4.
- **Prompt utilizado:** "Opción A" (CI4 como API REST + React como SPA con Vite en el mismo dominio).
- **Resultado esperado:** Arquitectura frontend/backend definida.
- **Resultado obtenido:** Se documenta la arquitectura desacoplada en el README.
- **Decisión:** CI4 solo expone API JSON bajo `/api`; React se despliega como estáticos en el mismo dominio.

### 2026-10-01 — Chat por polling y autenticación con Shield

- **Objetivo:** Cerrar las decisiones de chat y autenticación condicionadas por el hosting compartido de OVH.
- **Prompt utilizado:** "El chat será con polling. La autenticación CodeIgniter Shield con sesión por cookie y protección CSRF."
- **Resultado esperado:** Decisiones técnicas documentadas.
- **Resultado obtenido:** README y planificación actualizados.
- **Decisión:** Chat mediante polling AJAX desacoplado tras un servicio; autenticación con CodeIgniter Shield, sesión por cookie y CSRF.

### 2026-10-01 — Versiones, MVP y perfiles de bienvenida

- **Objetivo:** Cerrar versiones, alcance del MVP y resolver la entrada de nuevos usuarios sin registro abierto.
- **Prompt utilizado:** "Si, añadiria que como no permite el registro inicial, en la landing del portal, muestre 2 perfiles "falsos", a los cuales les permitas solicitar amistad poniendo tu correo electronico, y te envia la invitación a registrarte, dejandote como amigo de este perfil."
- **Resultado esperado:** Mecanismo de captación compatible con el modelo de acceso por invitación.
- **Resultado obtenido:** Se documentan versiones (PHP 8.3, CI 4.6, MySQL 8.0, React 18 + Vite + TS, Docker Compose), orden del MVP y el flujo de perfiles de bienvenida con medidas anti-abuso.
- **Decisión:** Los perfiles de bienvenida forman parte del módulo de invitaciones del MVP y se marcan visiblemente como demostración.

### 2026-10-01 — Planificación por sprints

- **Objetivo:** Convertir las fases generales en un plan de sprints accionable.
- **Prompt utilizado:** "si" (confirmación para commitear el refinamiento y pasar a la planificación detallada).
- **Resultado esperado:** Plan por sprints con entregables claros.
- **Resultado obtenido:** `docs/planificacion.md` reescrito: estructura de monorepo, MVP en 8 sprints (0–7) y versión 2 en 3 sprints (8–10).
- **Decisión:** Plan aprobado por el autor del proyecto ("Perfecto").

### 2026-10-01 — Sprint 0: fundaciones

- **Objetivo:** Montar la base técnica del proyecto.
- **Prompt utilizado:** "he hecho merge" (inicio del Sprint 0 tras aprobar la planificación).
- **Resultado esperado:** Monorepo con backend y frontend funcionando en local, CI y empaquetado para OVH.
- **Resultado obtenido:**
  - Backend CodeIgniter 4.7 en Docker (PHP 8.3 + Apache, MySQL 8.0) con `/api/health` y `/api/csrf`, filtro CSRF y cabeceras seguras.
  - Frontend Vite + React 19 + TypeScript con cliente HTTP con CSRF, layout inspirado en tuenti.es y tests con Vitest.
  - CI en GitHub Actions y script `scripts/build-release.sh` probado en Apache en modo producción.
  - Incidencias: el firewall del Codespace bloquea las redes de Docker Compose (documentado en el README); la plantilla de Vite trae React 19 y oxlint en vez de React 18 y ESLint.
- **Decisión:** Adoptar CodeIgniter 4.7, React 19 y oxlint. Quedan pendientes la verificación del plan OVH y el primer despliegue.

### 2026-10-01 — Configuración del servidor OVH

- **Objetivo:** Proveer las variables de producción desde la consola del servidor y poder actualizarlas después.
- **Prompt utilizado:** "Añadelo y creame un sh que me pregunte por estas variables para proveerlas por consola del servidor y se queden guardadas. Y a la vez si en algun momento cambia alguna variable, pueda actualizarla con el script."
- **Resultado esperado:** Script interactivo para `.env`, diagnóstico del alojamiento y versión de PHP fijada.
- **Resultado obtenido:**
  - `scripts/configurar-env.sh`: pregunta y valida las variables, mantiene los valores actuales con Enter, permite actualizar variables sueltas, oculta secretos, genera la clave de cifrado, guarda copia `.env.bak` y prueba la conexión a MySQL.
  - `scripts/ovh-check.php`: diagnóstico web de un solo uso (PHP, extensiones, límites, permisos, `.env`, MySQL, SMTP) que se borra solo.
  - `deploy/.ovhconfig` (PHP 8.3) y `backend/.env.example`, incluidos en el paquete de `build-release.sh`.
  - Probado de punta a punta con el paquete en Apache y MySQL en Docker, incluyendo contraseñas con caracteres especiales.
- **Decisión:** El `.env` de producción se gestiona solo con `configurar-env.sh`; nunca se sube al repositorio ni en el paquete.

### 2026-10-01 — Despliegue automático por rama `produccion`

- **Objetivo:** Adaptar el despliegue automático de OVH (push a `main`) para que solo se publique la landing.
- **Prompt utilizado:** "El despliegue se hace automáticamente al hacer un push a main. Pero por ahora sólo se muestra la landing."
- **Resultado esperado:** Que al fusionar el Sprint 0 no se publique el código fuente en la web.
- **Resultado obtenido:** La landing pasa a `landing/`; el workflow `deploy.yml` genera en cada push a `main` la rama `produccion` solo con el contenido publicable. Se crea la rama `produccion` inicial con la landing.
- **Decisión:** OVH debe desplegar la rama `produccion` en lugar de `main` (cambio en el panel de OVH antes de fusionar el PR del Sprint 0).

### 2026-10-01 — Despliegue por FTP con GitHub Secrets

- **Objetivo:** Desplegar sin SSH y sin guardar credenciales en el repositorio (que es público).
- **Prompt utilizado:** "parece que no hay SSH y tendre que meter las variables de entorno directamente en el repositorio" → se propone usar GitHub Secrets + FTP → "si".
- **Resultado esperado:** Despliegue automático en cada push a `main` con las credenciales fuera de git.
- **Resultado obtenido:**
  - `deploy.yml` sube por FTP (FTPS por defecto) en cada push a `main`. La variable `DESPLIEGUE` elige entre publicar solo la landing (`landing`, por defecto) o la aplicación completa (`app`).
  - En modo `app`, el `.env` se genera en el workflow con `configurar-env.sh --desde-entorno` a partir de los secrets, validando cada valor.
  - Sustituye a la integración Git de OVH y a la rama `produccion`, que deja de usarse.
- **Decisión:** Las credenciales viven solo en GitHub Secrets. Nunca se versiona un `.env` con valores reales.

### 2026-10-01 — Carpeta web fija `www/` en OVH

- **Objetivo:** Adaptar el despliegue a que OVH no permite cambiar la carpeta raíz del dominio principal (`www/`).
- **Prompt utilizado:** "OVH indica que no puedo cambiar la carpeta principal de www, pero si puedo desactivar el despliegue de git."
- **Resultado esperado:** Publicar en `www/` sin exponer el código ni el `.env`.
- **Resultado obtenido:** El workflow sube lo público a `www/` y la aplicación a `tuentidad/` (fuera de la web); `separar-publico.sh` ajusta `index.php` para cargarla desde `../tuentidad/`. Se bloquean los archivos ocultos en `.htaccess`. Probado simulando la estructura de OVH en Apache con MySQL.
- **Decisión:** Desactivar la integración Git de OVH y desplegar solo por FTP.
- **Incidencia:** Dos commits se subieron por error directamente a `main` al no comprobar la rama local tras la fusión del PR #4; la web mostró el listado del repositorio hasta que se cambió la rama de OVH a `produccion`. Desde entonces se trabaja siempre en rama y PR.

### 2026-10-01 — Un único dominio, sin preproducción

- **Objetivo:** Decidir dónde se prueba la aplicación desplegada antes del MVP.
- **Prompt utilizado:** "usaremos un único dominio para producción, no necesitaremos un entorno pre por ahora"
- **Resultado esperado:** Estrategia de entornos definida.
- **Resultado obtenido:** Se descarta `pre.tuentidad.es`. La landing sigue publicada hasta el MVP; la aplicación se valida en local con la estructura de OVH simulada y el alojamiento con `ovh-check.php`.
- **Decisión:** Un único entorno (`produccion`). El paso a `DESPLIEGUE=app` se hará en el Sprint 7.

### 2026-10-01 — Despliegue por SFTP

- **Objetivo:** Corregir el primer despliegue, que falló porque el FTP de OVH no admite FTPS (`500 This security scheme is not implemented`).
- **Prompt utilizado:** "si que funciona sftp" (tras proponer FTP sin cifrar como única alternativa).
- **Resultado esperado:** Despliegue cifrado sin depender de FTP.
- **Resultado obtenido:** `scripts/subir-sftp.sh` sube por SFTP con `lftp`, verificando la clave del servidor (`SFTP_KNOWN_HOSTS`), sin borrar nada del servidor y reemplazando cada archivo de forma atómica. Probado contra un servidor SFTP en Docker: primera subida, cambios, conservación de archivos del servidor, clave falsa y contraseña incorrecta.
- **Decisión:** Desplegar siempre por SFTP. Se descarta FTP sin cifrar.

### 2026-10-01 — Sprint 1: autenticación e invitaciones

- **Objetivo:** Implementar el acceso por invitación de punta a punta.
- **Prompt utilizado:** "ya" (inicio del Sprint 1 tras cerrar el despliegue por SFTP).
- **Resultado esperado:** Un usuario invita a otro, que se registra e inicia sesión.
- **Resultado obtenido:**
  - Backend: CodeIgniter Shield (sesión + CSRF en modo sesión), tablas `invitations` y `password_resets` (solo hashes de tokens), campos de perfil en `users`, regla de edad mínima, límites de peticiones, emails con plantilla y comando `tuentidad:usuario`.
  - Frontend: contexto de sesión, rutas protegidas, portada con login, registro por invitación, inicio con envío y listado de invitaciones, recuperación de contraseña. El cliente reintenta una vez si el token CSRF ha cambiado.
  - Mailpit en Docker Compose para ver los emails en local.
  - 32 tests de backend y 13 de frontend. Flujo completo probado con curl y a través del proxy de Vite.
  - Acciones de GitHub actualizadas a `checkout@v7` y `setup-node@v7` (Node 24).
- **Decisión:** Queda pendiente cómo ejecutar migraciones y crear el primer usuario en OVH sin SSH (antes del Sprint 7).

### 2026-10-01 — Primera prueba real en producción

- **Objetivo:** Publicar la aplicación en tuentidad.es y comprobar que funciona en OVH.
- **Prompt utilizado:** "perfecto, podemos hacer la primera prueba en real para ver que se carga todo en producción" → ante la pregunta de qué debe ver un visitante: "App completa definitiva".
- **Resultado esperado:** Despliegue de la aplicación con base de datos y primer usuario sin consola en el servidor.
- **Resultado obtenido:** Endpoints `/api/deploy/migrate` y `/api/deploy/first-user` protegidos con token (`DEPLOY_TOKEN`), sin CSRF, con límite de peticiones y desactivados si no hay token. El workflow aplica las migraciones y comprueba `/api/health` tras subir. Creación de usuarios compartida entre el comando de consola y el endpoint (`UserCreator`). Probado de punta a punta en local con la estructura de OVH y una base de datos vacía.
- **Decisión:** La aplicación queda publicada definitivamente (`DESPLIEGUE=app`); la landing deja de mostrarse.
