# Acceso SSH a producción — Procedimiento seguro

Este documento describe cuándo y cómo acceder al servidor de producción mediante SSH, con énfasis en seguridad y auditoría.

> **Principio rector:** producción no es un entorno de desarrollo. Se accede de forma puntual, con propósito definido y dejando rastro.

---

## 1. ¿Cuándo está justificado acceder a producción?

El acceso SSH a producción solo está justificado para:

| Caso | Ejemplo |
|---|---|
| Operación de mantenimiento crítica | Reinicio de servicio caído que no se puede hacer por panel OVH |
| Depuración de un bug que no se puede reproducir en staging | Error específico con datos reales que no se puede anonimizar |
| Backup manual urgente | Volcado de BD antes de una operación de riesgo |
| Verificación puntual de estado del servidor | Revisar logs de error en producción |
| Parche de seguridad urgente (hotfix) | Solo si el despliegue automático falla |

**No** se accede a producción para:
- Desarrollo diario de funcionalidades.
- Pruebas de código nuevo.
- Experimentar con configuraciones.

---

## 2. Configuración de acceso SSH

### 2.1. Generación de clave SSH (solo una vez por máquina)

```bash
# Genera un par de claves Ed25519 (recomendado sobre RSA)
ssh-keygen -t ed25519 -C "tu@email.com" -f ~/.ssh/tuentidad_prod

# Añade la clave pública al servidor (pide al admin del servidor que la registre)
cat ~/.ssh/tuentidad_prod.pub
```

La **clave pública** va en el servidor (`~/.ssh/authorized_keys`).  
La **clave privada** nunca sale de tu máquina (ni la compartas, ni la subas a ningún repositorio).

### 2.2. Configuración del cliente SSH (`~/.ssh/config`)

```sshconfig
Host tuentidad-prod
  HostName IP_DEL_SERVIDOR_OVH
  User USUARIO_SSH
  IdentityFile ~/.ssh/tuentidad_prod
  ServerAliveInterval 60
  ServerAliveCountMax 3
```

Con esta configuración puedes conectarte con:

```bash
ssh tuentidad-prod
```

---

## 3. Túnel SSH para acceso temporal a la base de datos

La base de datos MySQL de producción **no debe estar expuesta a Internet**. El acceso se realiza mediante un túnel SSH que redirige el puerto remoto a tu máquina local de forma temporal.

### 3.1. Abrir el túnel

```bash
# Abre el túnel: el puerto 3307 local apunta al MySQL de producción
ssh -N -L 3307:127.0.0.1:3306 tuentidad-prod
```

Deja este terminal abierto mientras necesites el túnel. Ciérralo cuando termines.

### 3.2. Conectar con un cliente MySQL a través del túnel

Con el túnel activo, conéctate como si MySQL fuera local:

```bash
mysql -h 127.0.0.1 -P 3307 -u USUARIO_BD -p NOMBRE_BD
```

O con cualquier cliente gráfico (TablePlus, DBeaver, MySQL Workbench):
- Host: `127.0.0.1`
- Puerto: `3307`
- Usuario / contraseña: los de producción (obtenidos de forma segura, no del repositorio)

### 3.3. Cerrar el túnel

Cuando termines, cierra el terminal donde está el túnel (o usa `Ctrl+C`).  
El túnel es temporal: no deja puertos abiertos permanentemente.

---

## 4. Buenas prácticas de seguridad

### Acceso

- Usa siempre **claves SSH** (nunca contraseñas por SSH).
- Configura `PasswordAuthentication no` en el servidor (`/etc/ssh/sshd_config`).
- Usa claves **Ed25519** (más seguras y cortas que RSA 2048).
- Protege tu clave privada con **passphrase**.
- Revoca el acceso de colaboradores cuando dejen el proyecto (elimina su clave pública del servidor).

### Operaciones

- Antes de cualquier operación destructiva (DROP, UPDATE masivo, borrado de ficheros), haz un **backup**.
- Ejecuta operaciones de BD en una transacción cuando sea posible: `BEGIN; ... ROLLBACK;` para verificar antes de `COMMIT`.
- Nunca ejecutes comandos copiados de internet sin entender qué hacen.
- Si vas a hacer un `UPDATE` o `DELETE`, añade primero un `LIMIT 1` para validar que afecta al registro correcto.

### Sesión

- Cierra la sesión SSH cuando termines (`exit` o `Ctrl+D`).
- No dejes sesiones SSH abiertas sin atender.
- Usa `tmux` o `screen` solo si necesitas una operación larga que deba sobrevivir a una desconexión, y ciérralo al terminar.

---

## 5. Auditoría y trazabilidad

Cada acceso a producción debe quedar documentado, aunque sea brevemente:

- **¿Quién accedió?** (nombre del miembro del equipo)
- **¿Cuándo?** (fecha y hora aproximada)
- **¿Por qué?** (motivo del acceso)
- **¿Qué se hizo?** (operación realizada)

Puedes usar la sección de issues del repositorio, un mensaje en el canal del equipo o una entrada en `docs/historico-prompts.md` para registrarlo.

Ejemplo de registro mínimo:

```
2026-07-15 — @carperfer — Acceso a producción
Motivo: verificar logs de error 500 reportados por usuario
Acción: tail -f /var/log/apache2/error.log — se detectó fallo en carga de imagen
Resolución: hotfix deployado vía PR #12
```

---

## 6. Procedimiento de emergencia (hotfix en producción)

Si necesitas aplicar un parche urgente:

```
1. Crea una rama hotfix desde main
   git checkout main && git pull
   git checkout -b hotfix/descripcion-del-problema

2. Aplica el parche mínimo necesario

3. Abre PR hacia main con etiqueta [HOTFIX] en el título
   - Describe el problema y el impacto

4. Solicita review exprés a otro miembro del equipo

5. Merge a main → deploy automático

6. Verifica en producción que el problema está resuelto

7. Documenta el incidente brevemente
```

En ningún caso se editan ficheros directamente en producción por SSH como solución permanente. El servidor de producción debe reflejar siempre el estado de la rama `main`.

---

## 7. Acceso para GitHub Actions (CI/CD)

Si el pipeline de CI/CD despliega por SSH a producción:

- Usa una **clave SSH dedicada para CI** (distinta a las de los desarrolladores).
- Guarda la clave privada como **GitHub Actions Secret** (`PROD_SSH_PRIVATE_KEY`).
- La clave pública correspondiente en el servidor debe tener permisos mínimos (idealmente solo para el directorio de la app, no para el servidor completo).
- Rota la clave si sospechas que ha sido comprometida.

Ver las variables recomendadas en [`.env.example`](../.env.example).
