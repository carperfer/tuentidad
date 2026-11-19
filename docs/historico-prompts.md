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

### 2026-10-25 — Diagnóstico del alojamiento OVH

- **Objetivo:** Comprobar que OVH tiene lo necesario para el Sprint 5 (no se puede instalar nada en el servidor).
- **Prompt utilizado:** "Estoy en un iPad y no puedo subir por sftp, lo puedes hacer por mi?"
- **Resultado obtenido:** El workflow Deploy tiene una opción manual («diagnostico») que publica `ovh-check.php` en `www/`. Resultado: todo correcto. PHP 8.3.36 (FPM), MySQL 8.4.11, GD con JPEG/PNG/WebP, exif, `upload_max_filesize` 128 MB, `post_max_size` 130 MB, `memory_limit` 512 MB, `max_execution_time` 165 s, carpetas `writable` escribibles, `.env` de producción y SMTP accesible.
- **Decisión:** Se mantiene el procesado de fotos solo con GD y los límites del Sprint 5. En local se usa MySQL 8.0 y en producción 8.4.

### 2026-10-25 — Rediseño según la guía de layout

- **Objetivo:** Planificar la adaptación de toda la SPA a `docs/frontend-layout.md`.
- **Prompt utilizado:** "En la carpeta doces hay un archivo Frontend-layout.md quiero que metas en el siguiente sprint la adecuación de todo el proyecto a este estilo, secciones posiciones de kas secciones…"
- **Resultado obtenido:** Nuevo Sprint 6 «Rediseño según la guía de layout» (tokens, primitivos, tres columnas, topbar, status bar y plantillas de Inicio y Perfil aplicadas a las páginas existentes). Los sprints siguientes se renumeran: Fotos II pasa a ser el 7, el lanzamiento del MVP el 8 y la versión 2 los sprints 9 a 11.
- **Decisión:** Rediseño como sprint propio en lugar de sumarlo a Fotos II, para que las funciones nuevas se construyan ya sobre el layout. Variante de módulo `boxed` por defecto. Los módulos de funciones que aún no existen no se muestran vacíos.

### 2026-10-25 — Sprint 6: rediseño según la guía de layout

- **Objetivo:** Adaptar toda la SPA a `docs/frontend-layout.md`.
- **Prompt utilizado:** "Continúa con el sprint 6".
- **Resultado esperado:** Las páginas actuales con la retícula de tres columnas, la topbar, la status bar y los módulos de la guía.
- **Resultado obtenido:** Tokens de §10 y variante de módulo `boxed`. Primitivos `Module`, `Avatar`, `MediaObject`, `Dropdown`, `Pager` y `Columns` (240 / flexible / 190 px, con dos y una columna por debajo de 1000 y 760 px). Topbar fija con menú hamburguesa en móvil, status bar «¿Cómo va todo?» y fondo azul con logotipo para visitantes. Inicio, perfil (ajeno y propio) y nueva página Gente con los módulos en las columnas de §5; la edición del perfil pasa a `/perfil/editar` y la lista completa de amigos a `/perfil/{id}/amigos`. Backend: avatares en los listados (según su privacidad), relación con el perfil, amigos de un usuario, amigos en común, últimas fotos y cambio rápido de estado. Tests: 77 de backend y 39 de frontend; revisión visual con capturas en escritorio, tableta y móvil.
- **Decisión:** Las listas de amigos de otra persona solo las ven sus amigos; los amigos en común, cualquier usuario con sesión. Los grises de la guía se oscurecen (`#6e6e6e`) para cumplir el contraste AA. Sin indicador de conexión hasta el chat.

### 2026-11-12 — Sprint 7: etiquetas, comentarios y avisos

- **Objetivo:** Implementar el Sprint 7 de la planificación (Fotos II).
- **Prompt utilizado:** "Por donde lo dejamos?" y, tras el resumen, "Si" para empezar el Sprint 7.
- **Resultado esperado:** Etiquetar amigos en una zona de la foto, retirar etiquetas, «Fotos en las que sale…», comentarios y avisos básicos en la web.
- **Resultado obtenido:** Tablas `photo_tags`, `photo_comments` y `notifications` (cada aviso apunta a la etiqueta, el comentario o la solicitud que lo generó y se borra en cascada con ellos). Controladores `Photos` y `Notifications`; la comprobación de quién puede ver las fotos pasa a `ApiController`. En la SPA, el visor muestra las zonas etiquetadas al pasar el ratón por la foto o por el nombre, permite marcar la zona arrastrando y elegir a la persona, y tiene un módulo de comentarios. Nuevas páginas Avisos (`/avisos`) y «Fotos en las que sale…» (`/perfil/{id}/etiquetas`), módulo en el perfil, contador en la topbar y novedades en la tarjeta de Inicio. Tests: 93 de backend y 45 de frontend; flujo probado en el navegador con Playwright.
- **Decisión:** Se puede etiquetar uno mismo o a amigos que también pueden ver la foto (amigos del dueño). Quitan la etiqueta la persona etiquetada, quien la puso y el dueño. La zona se guarda en porcentajes para que no dependa del tamaño mostrado. Al romper una amistad se borran las etiquetas de cada uno en las fotos del otro, porque ya no podrían verlas ni quitarlas. Los avisos de una foto solo cuentan mientras se puede ver. No hay emails de aviso (el centro de notificaciones y los resúmenes por email siguen en el Sprint 11).

### 2026-11-17 — Sprint 8: calidad y lanzamiento del MVP

- **Objetivo:** Implementar el Sprint 8 de la planificación.
- **Prompt utilizado:** "Inicia sprint 8".
- **Resultado esperado:** Revisión de seguridad, derechos del RGPD, pruebas de integración, revisión visual y copias de seguridad antes de publicar el MVP.
- **Resultado obtenido:** Cabeceras de seguridad (CSP incluida) para la SPA en el `.htaccess`. Las fotos de perfil se reprocesan con GD para quitar el EXIF, también las ya subidas (migración). Controlador `Account` con exportación en JSON y borrado de la cuenta con contraseña; módulo «Tus datos» en «Mi cuenta». Librería `DatabaseBackup` y `POST /api/deploy/backup`: el despliegue hace una copia antes de migrar y un workflow diario otra; se guardan 14 en `writable/backups`. Prueba de integración `MainFlowTest` con el recorrido completo. Revisión con Playwright de 12 páginas en tres anchos, con la SPA compilada servida por Apache: sin errores ni desbordes. Tests: 103 de backend y 48 de frontend.
- **Decisión:** Las copias no se suben a GitHub porque el repositorio es público y los artefactos se podrían descargar; se bajan por SFTP. Las fotos no entran en la copia (el despliegue no borra nada y OVH guarda copias del alojamiento). Al borrar una cuenta se eliminan también las invitaciones que la mencionan y su registro de accesos, que no caían en cascada. Queda pendiente que el responsable complete sus datos en las páginas legales.

### 2026-11-17 — Sprint 9: chat

- **Objetivo:** Empezar la versión 2 con el Sprint 9 (chat entre amigos).
- **Prompt utilizado:** "Empezamos con la versión 2".
- **Resultado esperado:** Conversaciones 1 a 1 con sondeo adaptativo, amigos conectados, mensajes no leídos y la interfaz de §8 de la guía.
- **Resultado obtenido:** Tabla `messages` y controlador `Chat` (estado, mensajes y envío; solo entre amigos). La conexión se calcula con `users.last_active`, que actualiza cada consulta del estado. En la SPA, `ChatService` (sondeo y estado, sin React) con transporte HTTP inyectable, `ChatProvider` en el layout, «Chat (n)» en la topbar, módulo en Inicio, ventanas flotantes con dock y «Mensaje privado» en el perfil. Los mensajes se exportan y se borran con la cuenta. Tests: 111 de backend y 54 de frontend; probado en el navegador con dos usuarios (la respuesta llega en menos de 4 s).
- **Decisión:** Sondeo en lugar de WebSockets porque OVH no los permite; intervalos de 30 s (estado), 4 s (conversación abierta) y 60 s en segundo plano. Conectado = ha consultado en los últimos 2 minutos. No se puede chatear con los perfiles de bienvenida ni con antiguos amigos (sus mensajes sin leer no cuentan). Máximo 3 ventanas abiertas. Sin «Ajustes» del chat por ahora.

### 2026-11-18 — Amistad automática al aceptar una invitación

- **Objetivo:** Que quien se registra con la invitación de un usuario quede como su amigo, como ya pasaba con los perfiles de bienvenida.
- **Prompt utilizado:** "haz una corrección, cuando invitas por correo a alguien y este acepta, se convierte inmediatamente en tu amigo".
- **Resultado obtenido:** El registro crea la amistad con quien invita, sea una persona o un perfil de bienvenida. El email de invitación y el formulario de registro lo avisan («En cuanto crees tu cuenta, seréis amigos»). Si la cuenta que invitó ya no existe, el registro sigue funcionando sin amistad. La prueba de integración deja de pedir amistad tras registrarse. Tests: 113 de backend y 54 de frontend.
- **Decisión:** Sin aviso nuevo para quien invita: verá a la persona en su lista de amigos.

### 2026-11-18 — Álbum por defecto en cada cuenta

- **Objetivo:** Que cada usuario tenga ya un álbum para subir fotos sin tener que crear el primero.
- **Prompt utilizado:** "Otra corrección, por defecto para el perfil de cada usuario debe haber un album ya creado para que pueda subir fotos y no tenga que crear el primero".
- **Resultado obtenido:** Las cuentas nuevas (registro con invitación, primer usuario y comando de consola) empiezan con el álbum vacío «Mis fotos» (`AlbumModel::createDefault`). Una migración lo crea para las cuentas existentes sin ningún álbum, salvo los perfiles de bienvenida. Tests: 115 de backend y 54 de frontend.
- **Decisión:** Es un álbum normal: se puede renombrar o borrar. La migración no se deshace, porque el álbum puede tener ya fotos.

### 2026-11-18 — «Subir fotos» directo al álbum

- **Objetivo:** Que los botones «Subir fotos» lleven directamente al álbum cuando el usuario solo tiene uno (normalmente «Mis fotos»).
- **Prompt utilizado:** "si" (a la propuesta de llevar «Subir fotos» directamente al álbum cuando solo hay uno).
- **Resultado obtenido:** Nueva ruta `/subir-fotos` a la que apuntan los botones de la topbar y de la barra de estado: con un álbum abre ese álbum; con varios o ninguno, la lista de álbumes. Usa `replace`, así que «atrás» vuelve a la página anterior. Tests: 56 de frontend; comprobado en el navegador con un usuario de un álbum y otro de dos.
- **Decisión:** Una ruta intermedia en lugar de pedir los álbumes desde la topbar en cada página.

### 2026-11-18 — Sprint 10: eventos

- **Objetivo:** Implementar el Sprint 10 de la planificación (eventos).
- **Prompt utilizado:** "Continuamos con el Sprint 10".
- **Resultado esperado:** Eventos con invitaciones, confirmación de asistencia, comentarios, recordatorios y el módulo «Calendario» de Inicio.
- **Resultado obtenido:** Tablas `events`, `event_invitations` y `event_comments`, y dos columnas nuevas en `notifications` (`event_id`, `event_comment_id`). Controlador `Events` y librería `EventReminders` con `POST /api/deploy/reminders`, lanzado cada hora por un workflow. En la SPA, páginas de lista, formulario y detalle, módulo «Calendario» en Inicio y avisos de eventos. Los comentarios de fotos y eventos comparten el componente `Comments`. Tests: 125 de backend y 59 de frontend; probado en el navegador con dos usuarios.
- **Decisión:** Eventos privados (quien organiza y sus invitados); solo se puede invitar a amigos, no a perfiles de bienvenida. Fechas en UTC y mostradas en la hora local; los emails, en hora de España. Recordatorio una vez, en las 24 horas previas, a quienes van o quizás van; si cambia la fecha, se vuelve a recordar. Quien dice que no va deja de verlo en su calendario. El calendario incluye cumpleaños solo si el amigo deja ver su fecha de nacimiento. Sin «Eventos patrocinados».

### 2026-11-18 — Sprint 11: notificaciones y pulido

- **Objetivo:** Implementar el Sprint 11 de la planificación.
- **Prompt utilizado:** "Si, aparece en verde. Podemos empezar el Sprint 11".
- **Resultado esperado:** Centro de notificaciones unificado, emails de resumen opcionales y mejoras de rendimiento.
- **Resultado obtenido:** Avisos `friend_accepted` e `invitation_accepted`; la API de avisos pagina por cursor (`before`), filtra por grupo y marca avisos de uno en uno. Columnas `users.digest` y `digest_sent_at`, ajuste en «Mi cuenta» y librería `NotificationDigest` con `POST /api/deploy/digests` y un workflow diario. Índice `notifications (user_id, id)`, cabeceras de caché para `/assets/` e `index.html`, recuentos de eventos en una sola consulta y eventos pasados paginados. Tests: 131 de backend y 61 de frontend; probado en el navegador.
- **Decisión:** Los resúmenes son opcionales y empiezan desactivados (RGPD); al activarlos solo cuentan las novedades desde ese momento, y no se envía nada si no hay novedades. La página Avisos ya no marca todo como leído al abrirse: se marca cada aviso al abrirlo o todos con el botón. Paginación por cursor en los avisos (no se descuadra si llegan nuevos) y por desplazamiento en los eventos pasados.

### 2026-11-18 — Textos legales al día con la versión 2

- **Objetivo:** Que la política de privacidad cubra el chat, los eventos y los emails de resumen.
- **Prompt utilizado:** "si" (a añadir esos puntos a la política de privacidad).
- **Resultado obtenido:** La política de privacidad describe ahora también el perfil, los amigos, las fotos, las etiquetas y los comentarios (que faltaban desde la versión 1), el chat (mensajes y estado de conexión), los eventos (y los cumpleaños en el calendario de los amigos), los recordatorios y el resumen por email (con consentimiento). La política de cookies menciona el almacenamiento de sesión de las ventanas del chat. En las condiciones, la baja se hace desde «Mi cuenta». Fecha de actualización: 18 de noviembre de 2026.
- **Decisión:** Siguen pendientes los datos del responsable y la revisión por alguien con conocimientos legales.

### 2026-11-19 — Versión 3, Sprint 12: barra superior, Inicio, chat, Mensajes y Vídeos

- **Objetivo:** Acercar la web a las capturas de `docs/sourceimages/`.
- **Prompt utilizado:** "En la carpeta docs/sourceimages, hay algunas secciones que faltarían…" (orden de la barra superior, Eventos y Calendario separados en Inicio, estado y tablón en el perfil, Redes con estudios y trabajos, chats abajo con los iconos de cada persona). Respuestas a las dudas: Mensajes como bandeja del chat, vídeos de YouTube, Redes con estudios, trabajos y redes sociales, y avisos y chat como en el original.
- **Resultado obtenido:** Plan de la versión 3 en dos sprints (12: navegación, Inicio, chat, Mensajes y Vídeos; 13: perfil). Sprint 12: barra superior en el orden original con «Mensajes (n)» y «Vídeos»; bandeja `/mensajes` y conversación `/mensajes/{id}` (`GET /api/chat/conversations` y `?before=` en los mensajes); dock inferior siempre visible con «Chat (n)» y los iconos de cada conversación; «Avisos (n)» en la tarjeta de Inicio; módulo «Eventos» encima del «Calendario»; tabla `videos` y página `/videos` con reproducción al pulsar desde youtube-nocookie.com. Tests: 137 de backend y 64 de frontend; probado en el navegador con dos usuarios.
- **Decisión:** Mensajes reutiliza los mensajes del chat (no hay un segundo sistema). Los vídeos no cargan nada de YouTube hasta pulsar y la CSP solo permite marcos de youtube-nocookie.com. Al reproducirlos Google recibe datos técnicos, lo que se indica en la política de privacidad.
