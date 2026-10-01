# Planificación formativa del proyecto

Plan base para implementar tuentidad.es con trazabilidad completa del proceso asistido por IA.

## Fase 1 — Fundaciones

- Definir requisitos funcionales y no funcionales
- Alinear identidad visual con tuenti.es original
- Establecer arquitectura base: PHP (CodeIgniter 4) + React + MySQL
- Configurar entorno de despliegue en OVH Web Cloud Hosting

## Alcance del MVP

Orden de construcción: invitaciones (incluidos perfiles de bienvenida) → perfil → amigos → fotos con etiquetado y comentarios. Chat y eventos quedan para la versión 2.

## Fase 2 — Núcleo social

- Sistema de invitaciones y acceso privado
- Perfiles de bienvenida en la landing con solicitud de amistad por email:
  - Formulario público con limitación de peticiones por IP/email, campo honeypot y aceptación de política de privacidad
  - Envío de invitación con token de un solo uso y caducidad
  - Al registrarse con el token, se crea automáticamente la amistad con el perfil de bienvenida
  - Los perfiles se identifican de forma visible como perfiles de demostración
- Gestión de perfiles y red de amigos reales
- Álbumes de fotos, etiquetado y comentarios

## Fase 3 — Comunicación y actividad

- Chat integrado mediante polling AJAX
- Sistema de eventos (creación, invitación y confirmación)
- Notificaciones de actividad social

## Fase 4 — Calidad y publicación

- Pruebas funcionales y de integración por módulo
- Revisión de seguridad y datos personales
- Validación UX final y despliegue controlado en OVH

## Seguimiento obligatorio de prompts

En cada fase:

1. Registrar prompts en `docs/historico-prompts.md`
2. Documentar objetivo y resultado de cada iteración
3. Ajustar este plan si cambian prioridades o alcance
