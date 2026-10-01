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

- PHP 8.3 · CodeIgniter 4.6 · MySQL 8.0
- React 18 + Vite + TypeScript
- Entorno local con Docker Compose
- Pendiente verificar en el panel de OVH: versiones de PHP/MySQL disponibles y acceso SSH

## Arquitectura

- **Backend:** CodeIgniter 4 expuesto únicamente como API REST (JSON) bajo `/api`.
- **Frontend:** SPA en React construida con Vite y servida como estáticos desde el mismo dominio (tuentidad.es).
- **Comunicación:** la SPA consume la API vía HTTP; al compartir dominio no se requiere CORS.
- **Autenticación:** CodeIgniter Shield con sesión por cookie (`HttpOnly`, `Secure`, `SameSite`) y protección CSRF en todas las peticiones que modifican datos.
- **Chat:** polling AJAX periódico contra la API (compatible con hosting compartido, sin WebSockets). El acceso a mensajes se encapsula en un servicio para poder sustituir el transporte en el futuro.

## Guía de diseño

El diseño visual (estructura, colores y estilo) debe mantenerse alineado con la experiencia original de tuenti.es.

## Documentación del proceso con IA

Como requisito formativo, el histórico de prompts y la planificación quedan documentados en:

- [`docs/historico-prompts.md`](docs/historico-prompts.md)
- [`docs/planificacion.md`](docs/planificacion.md)
