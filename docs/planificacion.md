# Planificación formativa del proyecto

Plan de desarrollo de tuentidad.es con trazabilidad completa del proceso asistido por IA.

## Supuestos

- Sprints de 2 semanas.
- Cada sprint termina con algo desplegable y probado (tests en verde en CI).
- Orden del MVP: invitaciones (incluidos perfiles de bienvenida) → perfil → amigos → fotos → rediseño según la guía de layout → etiquetado y comentarios en fotos. Chat y eventos quedan para la versión 2.

## Estructura del repositorio

```
tuentidad/
├── backend/          # CodeIgniter 4 (API REST bajo /api)
├── frontend/         # React 18 + Vite + TypeScript (SPA)
├── docker/           # Dockerfiles y configuración local
├── docs/             # Documentación y registro de prompts
└── docker-compose.yml
```

En producción (OVH), el build de la SPA se publica junto al `public/` de CodeIgniter. Un `.htaccess` envía `/api/*` a `index.php` y el resto de rutas a `index.html`.

## MVP

### Sprint 0 — Fundaciones

- [x] Monorepo `backend/` + `frontend/` y Docker Compose (PHP 8.3 + Apache, MySQL 8.0)
- [x] Instalación de CodeIgniter 4.7 con configuración por entorno
- [x] SPA con Vite + React 19 + TypeScript, router y cliente HTTP con soporte CSRF
- [x] Layout base con estética inspirada en tuenti.es (cabecera, cajas, paleta)
- [x] CI en GitHub Actions: PHPUnit, PHP-CS-Fixer, Vitest, oxlint y comprobación de tipos
- [x] Script de empaquetado para despliegue (`scripts/build-release.sh`)
- [x] Acceso al servidor: el plan no tiene consola SSH; el FTP de OVH no admite FTPS, se despliega por SFTP
- [x] Verificación del plan OVH con `ovh-check.php` (2026-10-25): PHP 8.3.36 (FPM), MySQL 8.4.11, todas las extensiones y SMTP `ssl0.ovh.net:465` accesible
- [x] Despliegue automático por SFTP en cada push a `main` (`deploy.yml`, credenciales en GitHub Secrets)
- [x] Primer despliegue en OVH

**Entregable:** SPA llamando a `/api/health` funcionando con la estructura de OVH (simulada en local) y despliegue automático de la landing en producción.

> Se usa un único dominio (tuentidad.es) sin entorno de preproducción. Tras el Sprint 1 se decide publicar la aplicación de forma definitiva (`DESPLIEGUE=app`): el acceso es solo por invitación, así que nadie entra sin ser invitado.

### Sprint 1 — Autenticación e invitaciones

- [x] CodeIgniter Shield: login, logout, "recordarme", sesión por cookie (`HttpOnly`, `Secure`, `SameSite`) y CSRF ligado a la sesión
- [x] Modelo de invitaciones: token de un solo uso (solo se guarda su hash), caducidad de 7 días y cupo de 10 por usuario; reenviar no gasta cupo
- [x] Envío de emails con plantilla propia (SMTP; Mailpit en local)
- [x] Registro solo con invitación válida: edad mínima de 14 años, contraseña robusta sin datos personales, aceptación de condiciones
- [x] Recuperación de contraseña con enlace de un solo uso (1 hora) y misma respuesta exista o no la cuenta
- [x] Límite de peticiones en login, registro, invitaciones y recuperación
- [x] Comando `php spark tuentidad:usuario` para crear el primer usuario
- [x] SPA: portada con login, registro por invitación, inicio con invitaciones, recuperación de contraseña
- [x] Tests: 32 de backend (API contra MySQL) y 13 de frontend

**Entregable:** un usuario semilla invita a otro, que se registra e inicia sesión.

- [x] Despliegue en producción sin consola: endpoints `/api/deploy/migrate` (lo llama el workflow) y `/api/deploy/first-user` (solo mientras no haya usuarios), protegidos con `DEPLOY_TOKEN`

### Sprint 2 — Landing y perfiles de bienvenida

- [x] Portada con login y los 2 perfiles de bienvenida (Lucía y Dani), marcados como «Perfil de demostración», con avatares ilustrados
- [x] Perfiles creados por migración (llegan a producción con el despliegue); son usuarios sin credenciales, no pueden iniciar sesión
- [x] Solicitud de amistad por email: límite de peticiones (por IP y por email), campo trampa y aceptación de la privacidad (se guarda la fecha del consentimiento)
- [x] Misma respuesta exista o no la cuenta (si existe, se le envía un recordatorio para iniciar sesión)
- [x] Al registrarse con esa invitación, amistad automática con el perfil de bienvenida (tabla `friendships`, base del Sprint 4)
- [x] Las invitaciones no aceptadas se borran 30 días después de caducar
- [x] Páginas legales: privacidad, condiciones de uso y cookies (solo técnicas, sin banner)
- [x] Tests: 49 de backend y 19 de frontend

**Entregable:** un visitante anónimo puede acabar registrado y como amigo de un perfil de bienvenida.

> **Pendiente:** completar los datos del responsable en `frontend/src/pages/legal/owner.ts` (nombre, NIF, dirección y email de contacto) y revisar los textos legales con asesoría antes de abrir el sitio al público.

### Sprint 3 — Perfil

- [x] Página de perfil: datos personales, foto de perfil y estado
- [x] Edición del perfil y privacidad por campo (público, solo amigos o privado)
- [x] Página de inicio con resumen de amistades e invitaciones propias
- [x] Tests frontend (21 en total)
- [x] Tests backend (55 en total, ejecutados en Docker) y frontend (21)

**Entregable:** perfil editable con datos filtrados según la relación de amistad; pendiente validar la suite backend en el entorno de CI.

### Sprint 4 — Amigos

- [x] Solicitudes de amistad: enviar, aceptar, rechazar y cancelar
- [x] Listado de amigos y eliminación de amistad
- [x] Búsqueda de usuarios
- [x] Novedades de amigos en la página de inicio, filtradas por privacidad
- [x] Tests de backend y frontend para los flujos principales

**Entregable:** red de amigos funcional entre usuarios reales.

### Sprint 5 — Fotos I: álbumes y subida

- [x] Álbumes: crear, editar y borrar
- [x] Subida múltiple con validación de tipo y tamaño (JPG, PNG o WebP; 10 MB y 25 megapíxeles por foto). La SPA sube las fotos de una en una para respetar los límites del hosting compartido
- [x] Miniaturas con GD (sin Imagick) y eliminación de metadatos EXIF; se aplica antes la orientación EXIF
- [x] Visor de fotos con navegación anterior/siguiente (también con las flechas del teclado)
- [x] Permisos: solo el dueño y sus amigos ven los álbumes y las fotos, que se sirven a través de la API
- [x] Tests: 72 de backend y 30 de frontend
- [x] Verificado en OVH: GD con JPEG, PNG y WebP, `exif` cargada, `upload_max_filesize` 128 MB, `post_max_size` 130 MB, `memory_limit` 512 MB y `max_execution_time` 165 s

**Entregable:** subir y navegar álbumes de fotos.

### Sprint 6 — Rediseño según la guía de layout

Adaptar toda la SPA a [frontend-layout.md](frontend-layout.md) (capturas de referencia en `docs/sourceimages/`): retícula, regiones y orden de los módulos de cada página.

- [x] Tokens de diseño (§10) como variables CSS, sustituyendo la paleta actual
- [x] Variante de módulo `boxed` por defecto (cabecera azul claro, la más próxima al estilo actual)
- [x] Primitivos: `Module`, `Avatar` (tamaños de §7.5), `Button` (primario y secundario), `Dropdown`, `Pager`, `MediaObject` y contadores entre paréntesis
- [x] Layout de tres columnas (240 px / flexible / 190 px en 990 px) con los cortes de §11 (dos columnas por debajo de 1000 px y una por debajo de 760 px, con menú hamburguesa)
- [x] Topbar fija (§3): logo, Inicio, Perfil, Gente, buscador de personas, botón **Subir fotos**, Mi cuenta y Salir; pestaña activa resaltada. Nueva página **Gente** (`/gente`) con búsqueda, amigos y solicitudes
- [x] Status bar en Inicio (§4): avatar, «¿Cómo va todo?» con el último estado, hace cuánto se cambió y **Subir fotos**
- [x] Inicio (§5.1): izquierda con tarjeta de usuario e invitaciones; centro con «Novedades» (`FeedItem`); derecha con «Añadir amigos» y solicitudes de amistad
- [x] Perfil ajeno y propio (§5.2 y §5.3): izquierda con foto de perfil e «Información»; centro con cabecera (nombre, relación y acciones: Editar mi perfil, Mis álbumes (n), Añadir como amigo) y caja de estado; derecha con «Fotos» (rejilla 2×3, «Álbumes (n) ▾», «Ver todas (n)»), «Amigos en común» y «Sus amigos / Mis amigos»
- [x] Edición del perfil en una vista propia dentro del layout
- [x] Backend para la columna derecha del perfil: amigos de un usuario, amigos en común y últimas fotos (respetando que solo los amigos ven las fotos)
- [x] Álbumes, álbum y visor de fotos dentro del layout (miniaturas de ~75 px)
- [x] Portada, registro, recuperación de contraseña y páginas legales con los nuevos tokens y la topbar sin sesión
- [x] Footer (§9): copyright y enlaces legales
- [x] Accesibilidad (§12): `<aside>`/`<main>`, un `<h2>` por módulo, `aria-label` en botones de icono, fechas en `<time>` y contraste AA
- [x] Tests: 77 de backend y 39 de frontend; revisión visual con capturas en escritorio, tableta y móvil

Fuera de alcance: los módulos de funciones que aún no existen (tablón, espacio personal, mensajes, vídeos, chat, calendario y eventos, redes, visitas al perfil, eventos patrocinados y selector de idioma) no se muestran vacíos; cada uno se añade en el hueco que le da la guía en el sprint de su función.

**Entregable:** todas las páginas actuales con la estructura de tres columnas y los módulos de la guía.

### Sprint 7 — Fotos II: etiquetas y comentarios

- [x] Etiquetado sobre la foto: se arrastra para marcar la zona (o se usa la zona centrada que aparece por defecto, también con teclado) y se elige a la persona. Se puede etiquetar uno mismo o a sus amigos que pueden ver la foto
- [x] Quitan una etiqueta la persona etiquetada, quien la puso y el dueño de la foto. Al romper una amistad se borran las etiquetas de cada uno en las fotos del otro
- [x] Página «Fotos en las que sale…» (`/perfil/{id}/etiquetas`) y módulo del perfil (rejilla de 5 columnas, §5.2 de la guía de layout); solo muestra las fotos que puede ver quien consulta
- [x] Comentarios en fotos: los borran su autor y el dueño de la foto
- [x] Avisos en la web (etiquetas, comentarios y solicitudes de amistad): página **Avisos** (`/avisos`), contador en la topbar y novedades en verde en la tarjeta de Inicio. Se marcan como leídos al abrir la página o la foto, y desaparecen si ya no se puede ver la foto o la solicitud se responde
- [x] Tests: 93 de backend y 45 de frontend; prueba en el navegador (etiquetar arrastrando, comentar y ver los avisos) en escritorio y móvil

**Entregable:** experiencia de fotos completa estilo Tuenti.

### Sprint 8 — Calidad y lanzamiento del MVP

- Revisión de seguridad (OWASP: XSS, CSRF, IDOR, subidas de archivos)
- RGPD: borrado de cuenta y exportación de datos
- Pruebas de integración de los flujos principales
- Revisión UX y responsive
- Despliegue controlado en OVH y copias de seguridad de la base de datos

**Entregable:** MVP publicado en tuentidad.es.

## Versión 2

### Sprint 9 — Chat

- Conversaciones 1 a 1 entre amigos
- Polling AJAX con intervalo adaptativo (más frecuente con el chat abierto)
- Indicador de amigos conectados y mensajes no leídos
- Transporte encapsulado en un servicio para poder sustituirlo
- Interfaz según §8 de la guía de layout: lista de contactos, ventana flotante y dock inferior

### Sprint 10 — Eventos

- Crear eventos (cumpleaños, fiestas, quedadas) con fecha, lugar y descripción
- Invitar amigos y confirmar asistencia (sí / no / quizás)
- Comentarios en el evento y recordatorios por cron
- Módulo «Calendario» en la columna izquierda de Inicio (§6.1 de la guía de layout)

### Sprint 11 — Notificaciones y pulido

- Centro de notificaciones unificado
- Emails de resumen opcionales
- Mejoras de rendimiento (índices, caché, paginación)

## Seguimiento obligatorio de prompts

En cada sprint:

1. Registrar prompts en `docs/historico-prompts.md`
2. Documentar objetivo y resultado de cada iteración
3. Ajustar este plan si cambian prioridades o alcance
