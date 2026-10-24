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

### 2026-10-01 — Remitente de los emails en tuentidad.com

- **Objetivo:** Ajustar el remitente a la cuenta de correo disponible en OVH.
- **Prompt utilizado:** "Base de datos creada ¿Te paso las credenciales? La cuenta de correo ha tenido que ser no-reply@tuentidad.com"
- **Resultado obtenido:** El remitente por defecto pasa a `no-reply@tuentidad.com`. Las credenciales no se comparten en la conversación: se introducen directamente como secrets de GitHub.
- **Decisión:** Las credenciales de producción solo viven en GitHub Secrets.

### 2026-10-01 — Primer despliegue real y correcciones

- **Objetivo:** Poner en marcha la aplicación en tuentidad.es y corregir lo que apareció en el log de producción.
- **Prompts utilizados:** "lanzado", "fusionada", "ha funcionado y he podido loguearme en producción, pero enviar un correo ha fallado…", "¿has corregido?"
- **Resultado obtenido:**
  - `/api/*` respondía `File not found.`: el PHP-FPM de OVH no admite `index.php/ruta` tras una reescritura. Se reescribe a `index.php` (PR #9). Migraciones aplicadas y primer usuario creado por HTTP.
  - El envío de emails falla con `535 Authentication failed`: la conexión con `ssl0.ovh.net` es correcta, el problema es la contraseña de `no-reply@tuentidad.com` en `SMTP_PASSWORD` (pendiente de revisar por el autor).
  - Al iniciar sesión cambia el token CSRF y la primera petición posterior daba 403 (el cliente reintentaba). El cliente ahora pide un token nuevo tras el login y el registro.
  - Un JSON mal formado daba 500; ahora responde 400 con un mensaje claro y no se registra como error crítico.
  - Tras un reinicio del Codespace, el almacén de imágenes de Docker quedó dañado: se reconstruyeron las imágenes sin tocar el volumen de la base de datos.
- **Decisión:** Las credenciales nunca se pegan en la conversación ni en comandos guardados; se recomienda cambiar la contraseña del primer usuario.

### 2026-10-02 — Envío de emails en producción resuelto

- **Objetivo:** Que las invitaciones salgan desde producción.
- **Prompts utilizados:** "las credenciales son correctas, pero en producción sigue indicando que no se pudo enviar", "faltaban secretos, redespliego", "perfecto, ya ha funcionado".
- **Resultado obtenido:** Diagnóstico por pasos: DNS (el correo de `tuentidad.com` es MX Plan, `ssl0.ovh.net:465` es correcto), prueba SMTP con `curl` desde el Codespace (la primera se hizo sin contraseña por un error en el comando propuesto; la segunda dio `235`), y revisión de los secrets: faltaban algunos en el environment `produccion`. Tras completarlos y redesplegar, los emails salen.
- **Decisión:** Ante fallos de configuración en producción, probar primero los servicios externos desde fuera del alojamiento para separar el problema de la aplicación.

### 2026-10-02 — Sprint 2: perfiles de bienvenida y páginas legales

- **Objetivo:** Dar una puerta de entrada a quien no conoce a nadie dentro y publicar las páginas legales.
- **Prompt utilizado:** "perfecto, ya ha funcionado, podemos continuar"
- **Resultado obtenido:**
  - Dos perfiles de demostración (Lucía y Dani) creados por migración, sin credenciales, con avatares SVG ilustrados.
  - `GET /api/welcome-profiles` y `POST /api/welcome-profiles/{id}/friend-requests` con límite por IP y por email, campo trampa, consentimiento con fecha y respuesta idéntica exista o no la cuenta.
  - Tabla `friendships` y amistad automática al registrarse desde un perfil de bienvenida.
  - Borrado de invitaciones no aceptadas 30 días después de caducar.
  - `InvitationSender` compartido entre invitaciones de usuarios y de perfiles de bienvenida.
  - Los tests detectaron que `/api/deploy/first-user` contaba los perfiles de bienvenida como usuarios: ahora cuenta solo cuentas con credenciales.
  - Páginas de privacidad, condiciones y cookies, con los datos del responsable pendientes de completar.
  - 49 tests de backend y 19 de frontend.
- **Decisión:** Solo la invitación desde un perfil de bienvenida crea amistad automática (la de invitaciones entre usuarios se decide en el Sprint 4).

### 2026-10-23 — Sprint 3: perfil y privacidad

- **Objetivo:** Continuar el desarrollo con perfiles editables, datos visibles según privacidad y un resumen de actividad propia.
- **Prompt utilizado:** "Continua con el sprint"; se eligió privacidad configurable por campo (público, solo amigos o privado).
- **Resultado obtenido:** Implementados edición de perfil, biografía, estado, avatar privado en almacenamiento, filtros por amistad y resumen de amistades e invitaciones. Las pruebas frontend pasan (21 en total). Se añadieron pruebas backend y la suite completa pasa en Docker (55 tests). La columna social se llama `profile_status` porque Shield ya usa `users.status`, y las URLs de avatar son relativas para funcionar en cualquier dominio.
- **Decisión:** Mantener pendiente el cierre del Sprint 3 hasta ejecutar las pruebas backend en CI o corregir la conexión entre contenedores del Codespace.

### 2026-10-23T13:29:14Z — Sprint 4: amigos

- **Objetivo:** Implementar el siguiente sprint de la planificación: solicitudes, búsqueda, amistades y novedades.
- **Prompt utilizado:** "Siguiente fase".
- **Resultado obtenido:** Añadidos endpoints autenticados para buscar usuarios, gestionar solicitudes de amistad, listar y eliminar amigos; las amistades confirmadas se mantienen simétricas. La pantalla de inicio muestra solicitudes, contactos y estados visibles de amigos. Se agregó timestamp de cambio de estado y el feed excluye perfiles no amigos y estados privados.
- **Decisión:** Mantener las solicitudes pendientes separadas de `friendships`; exponer solo nombre y apellido en búsqueda/listados. Validación en curso con PHPUnit, Vitest, typecheck y lint.
- **Cierre (2026-10-24):** Suite completa en verde (61 tests de backend y 24 de frontend).

### 2026-10-24 — Frontend en Docker Compose

- **Objetivo:** Que `docker compose up` arranque también la SPA.
- **Prompt utilizado:** "Puedes hacer que el docker compose up también arranque el frontend".
- **Resultado obtenido:** Servicio `frontend` (Node 24) con Vite y recarga en caliente en el puerto 5173; `/api` se redirige al contenedor `app` y `node_modules` va en un volumen propio.
- **Decisión:** Lint, typecheck y tests del frontend siguen ejecutándose en la máquina anfitriona.

### 2026-10-24 — Sprint 5: álbumes y subida de fotos

- **Objetivo:** Implementar el Sprint 5 de la planificación.
- **Prompt utilizado:** "¿Siguiente fase?"; se aceptó el planteamiento propuesto: "Si, al final haremos una prueba para ver que en el servidor OVH esta todo instalado correctamente, sino cambiaremos la estrategia porque no se pueden instalar cosas en OVH".
- **Resultado esperado:** Álbumes con subida múltiple, miniaturas, visor y fotos visibles solo para amigos.
- **Resultado obtenido:** Tablas `albums` y `photos`; librería `PhotoProcessor` solo con GD que reduce a 1600 px, crea una miniatura cuadrada de 240 px, aplica la orientación EXIF y guarda en JPEG sin metadatos. Las fotos se guardan en `writable/uploads/photos` y solo se sirven por la API al dueño y a sus amigos. La SPA tiene páginas de álbumes, álbum y visor (con flechas del teclado) y sube las fotos de una en una. Tests: 72 de backend y 30 de frontend; subida real comprobada en Docker.
- **Decisión:** Álbumes visibles solo para amigos (sin privacidad por álbum, como dice el plan). Se guarda solo la versión reducida, no el original. Queda pendiente verificar en OVH las extensiones y límites de PHP; si falta algo, se cambiará la estrategia porque en OVH no se puede instalar nada.
