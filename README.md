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

Los puertos 3000 (frontend), 8080 (backend) y 3306 (MySQL) se redirigen automáticamente.

### 4. Trabajar en una rama feature

Nunca trabajes directamente en `main`. Crea siempre una rama de trabajo:

```bash
git checkout -b feature/nombre-de-tu-tarea
```

Consulta el [flujo de desarrollo completo](docs/development-workflow.md) para más detalles.

---

## Documentación operativa

| Documento | Descripción |
|---|---|
| [Flujo de desarrollo](docs/development-workflow.md) | Estrategia de ramas, proceso de PR y reglas de protección de `main` |
| [Acceso SSH a producción](docs/production-ssh-access.md) | Procedimiento seguro para operaciones puntuales en producción |
| [Variables de entorno](.env.example) | Plantilla de configuración para desarrollo, staging y producción |

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
