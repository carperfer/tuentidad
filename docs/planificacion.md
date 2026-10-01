# Planificación formativa del proyecto

Plan de desarrollo de tuentidad.es con trazabilidad completa del proceso asistido por IA.

## Supuestos

- Sprints de 2 semanas.
- Cada sprint termina con algo desplegable y probado (tests en verde en CI).
- Orden del MVP: invitaciones (incluidos perfiles de bienvenida) → perfil → amigos → fotos con etiquetado y comentarios. Chat y eventos quedan para la versión 2.

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
- [ ] Verificación del plan OVH (versiones PHP/MySQL, SSH, cron, SMTP)
- [ ] Primer despliegue en OVH y automatización del despliegue

**Entregable:** "Hola mundo" de la SPA llamando a `/api/health`, desplegado en OVH.

### Sprint 1 — Autenticación e invitaciones

- CodeIgniter Shield: login, logout, sesión por cookie (`HttpOnly`, `Secure`, `SameSite`) y CSRF
- Modelo de invitaciones: token de un solo uso, caducidad y cupo por usuario
- Envío de invitaciones por email (SMTP OVH)
- Registro solo con token válido (verificación de edad mínima de 14 años)
- Recuperación de contraseña

**Entregable:** un usuario semilla invita a otro, que se registra e inicia sesión.

### Sprint 2 — Landing y perfiles de bienvenida

- Landing pública con login y los 2 perfiles de bienvenida (marcados como demostración)
- Seeder de perfiles de bienvenida
- Formulario de solicitud de amistad por email: limitación de peticiones (Throttler), honeypot y aceptación de privacidad
- Al registrarse con esa invitación, amistad automática con el perfil de bienvenida
- Páginas legales: política de privacidad, términos y cookies

**Entregable:** un visitante anónimo puede acabar registrado y como amigo de un perfil de bienvenida.

### Sprint 3 — Perfil

- Página de perfil: datos personales, foto de perfil y estado
- Edición del perfil y ajustes de privacidad (qué ven los amigos y qué ven los demás)
- Página de inicio con resumen de la actividad propia

**Entregable:** perfil completo editable y visible para amigos.

### Sprint 4 — Amigos

- Solicitudes de amistad: enviar, aceptar, rechazar y cancelar
- Listado de amigos y eliminación de amistad
- Búsqueda de usuarios
- Novedades de amigos en la página de inicio

**Entregable:** red de amigos funcional entre usuarios reales.

### Sprint 5 — Fotos I: álbumes y subida

- Álbumes: crear, editar y borrar
- Subida múltiple con validación de tipo y tamaño
- Generación de miniaturas (GD/Imagick) y eliminación de metadatos EXIF
- Visor de fotos con navegación anterior/siguiente
- Permisos: solo los amigos ven las fotos

**Entregable:** subir y navegar álbumes de fotos.

### Sprint 6 — Fotos II: etiquetas y comentarios

- Etiquetado de amigos sobre la foto (seleccionando una zona)
- El etiquetado puede retirarse por la persona etiquetada
- Página "fotos en las que sales"
- Comentarios en fotos
- Notificaciones básicas en la web (etiquetas, comentarios, solicitudes de amistad)

**Entregable:** experiencia de fotos completa estilo Tuenti.

### Sprint 7 — Calidad y lanzamiento del MVP

- Revisión de seguridad (OWASP: XSS, CSRF, IDOR, subidas de archivos)
- RGPD: borrado de cuenta y exportación de datos
- Pruebas de integración de los flujos principales
- Revisión UX y responsive
- Despliegue controlado en OVH y copias de seguridad de la base de datos

**Entregable:** MVP publicado en tuentidad.es.

## Versión 2

### Sprint 8 — Chat

- Conversaciones 1 a 1 entre amigos
- Polling AJAX con intervalo adaptativo (más frecuente con el chat abierto)
- Indicador de amigos conectados y mensajes no leídos
- Transporte encapsulado en un servicio para poder sustituirlo

### Sprint 9 — Eventos

- Crear eventos (cumpleaños, fiestas, quedadas) con fecha, lugar y descripción
- Invitar amigos y confirmar asistencia (sí / no / quizás)
- Comentarios en el evento y recordatorios por cron

### Sprint 10 — Notificaciones y pulido

- Centro de notificaciones unificado
- Emails de resumen opcionales
- Mejoras de rendimiento (índices, caché, paginación)

## Seguimiento obligatorio de prompts

En cada sprint:

1. Registrar prompts en `docs/historico-prompts.md`
2. Documentar objetivo y resultado de cada iteración
3. Ajustar este plan si cambian prioridades o alcance
