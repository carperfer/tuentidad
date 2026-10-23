# Flujo de desarrollo — tuentidad

Este documento describe la estrategia de ramas, el proceso de promoción entre entornos y las reglas recomendadas de protección de `main` para el repositorio `carperfer/tuentidad`.

---

## 1. Convención de ramas

| Rama | Propósito | Restricciones |
|---|---|---|
| `main` | Código en producción. Cada merge desencadena un despliegue. | Sin pushes directos. Solo acepta merges mediante PR aprobado con CI en verde. |
| `feature/*` | Desarrollo de nuevas funcionalidades o tareas. | Rama de trabajo diario. Se crea desde `main` y se elimina tras el merge. |
| `hotfix/*` | Correcciones urgentes de producción. | Se crea desde `main`, se aplica directamente a `main` con PR express. |

### Ejemplos de nombres

```
feature/registro-usuarios
feature/album-fotos
feature/chat-tiempo-real
hotfix/fix-login-crash
hotfix/parche-seguridad-sesiones
```

---

## 2. Flujo de trabajo recomendado

### Paso a paso para una feature

```
1. Crea tu rama desde main
   git checkout main
   git pull origin main
   git checkout -b feature/mi-funcionalidad

2. Desarrolla en local o en GitHub Codespaces
   - Trabaja contra una BD local o de staging (nunca producción)
   - Haz commits frecuentes y con mensajes descriptivos

3. Abre un Pull Request hacia main
   - Título claro: "[Feature] Registro de usuarios"
   - Descripción: qué hace, qué se probó, capturas si aplica

4. CI se ejecuta automáticamente
   - El workflow .github/workflows/ci.yml valida lint / test / build
   - El PR no puede mergearse hasta que CI esté en verde

5. Code review
   - Al menos una persona del equipo revisa y aprueba el PR

6. Merge a main
   - Se hace Squash merge o Merge commit (según preferencia del equipo)
   - La rama feature/* se elimina después del merge

7. Deploy automático
   - El merge a main desencadena el despliegue a producción
   - Monitoriza los logs tras el deploy
```

---

## 3. Reglas recomendadas de protección de `main`

Las siguientes reglas deben configurarse en **Settings → Branches → Branch protection rules** del repositorio de GitHub:

- ✅ **Require a pull request before merging** — ningún push directo a `main`.
- ✅ **Require approvals** — mínimo 1 aprobación de reviewer antes del merge.
- ✅ **Require status checks to pass before merging** — el job `validate` de `ci.yml` debe estar en verde.
- ✅ **Require branches to be up to date before merging** — la rama feature debe estar actualizada con `main` antes de mergear.
- ✅ **Do not allow bypassing the above settings** — ni siquiera el propietario puede saltarse las reglas.
- ⬜ **Require signed commits** — opcional pero recomendable para mayor trazabilidad.
- ⬜ **Restrict who can push to matching branches** — si el equipo crece, limitar quién puede aprobar merges.

---

## 4. Estrategia de staging / preview

Dado que la app está en fase inicial sobre OVH Web Cloud Hosting, se recomienda el siguiente esquema progresivo:

### Opción A — Subdirectorio de staging en el mismo servidor (mínimo esfuerzo)

```
tuentidad.es/           → producción (rama main)
staging.tuentidad.es/   → staging (rama develop o rama feature)
```

- Crear un subdominio `staging.tuentidad.es` apuntando a un directorio diferente del servidor OVH.
- Protegerlo con autenticación básica HTTP para que no sea público.
- El CI puede desplegar ahí en cada PR antes de aprobar el merge.

### Opción B — Entornos preview en Vercel / Railway / Render (recomendado a futuro)

Si el frontend React se despliega en una plataforma serverless:
- Cada PR generará automáticamente una URL de preview única.
- El backend PHP puede apuntar a una BD de staging separada.
- Esto permite QA visual antes del merge.

### Opción C — Solo GitHub Codespaces (fase actual)

Mientras el proyecto está en construcción:
- Cada desarrollador levanta su entorno en Codespaces (ver [inicio rápido](../README.md)).
- No existe staging externo; las demos se hacen en el propio Codespace.
- El deploy a producción ocurre solo al mergear a `main`.

---

## 5. Ciclo de vida de una rama feature

```
main ──────────────────────────────────────────── main
        \                                    /
         feature/mi-feature ────────────────
               ↑                        ↑
           git checkout             PR → CI → review → merge
```

**Regla de oro:** `main` debe estar siempre en un estado desplegable.

---

## 6. Gestión de secretos y variables de entorno

- Nunca commites credenciales reales en el repositorio.
- Usa `.env.example` como plantilla pública (ver [`.env.example`](../.env.example)).
- Copia `.env.example` como `.env` localmente (está en `.gitignore`).
- Para producción y CI, usa **GitHub Actions Secrets** (Settings → Secrets and variables → Actions).
- Para Codespaces, usa **Codespaces Secrets** (Settings → Codespaces → Secrets).

---

## 7. Checklist de PR

Antes de pedir review, verifica:

- [ ] El código compila / no tiene errores de sintaxis.
- [ ] Los tests pasan localmente (si existen).
- [ ] No hay credenciales ni secretos en el diff.
- [ ] El `.env.example` está actualizado si añadiste nuevas variables.
- [ ] La rama está actualizada con `main`.
- [ ] La descripción del PR explica qué hace el cambio y cómo probarlo.
