# Tuentidad · Guía de disposición del frontend (layout)

> Documento de referencia para implementar la estructura visual de Tuentidad a partir de la interfaz clásica de Tuenti (2009–2011).
> Define la retícula, las regiones de cada página, los componentes reutilizables y los patrones de interacción.
> Los valores de color y medida se han estimado a partir de capturas: son puntos de partida, no especificaciones exactas.

---

## 1. Principios generales

1. **Ancho fijo centrado.** La interfaz original es una columna central de ~990 px centrada en la ventana, con fondo blanco y márgenes laterales grises/blancos. Tuentidad mantiene ese ancho en escritorio y lo adapta en pantallas pequeñas (ver §9).
2. **Tres columnas** en casi todas las páginas: lateral izquierda (identidad / contexto), central (contenido principal / actividad) y lateral derecha (social: fotos, amigos, chat).
3. **Módulos apilados.** Cada columna es una pila vertical de _módulos_ (cajas) con cabecera y cuerpo. Todo el sitio se construye combinando los mismos módulos.
4. **Densidad alta, tipografía pequeña.** Texto base 12–13 px, mucha información por pantalla, separadores finos de 1 px.
5. **Acciones como enlaces.** La mayoría de acciones son enlaces azules ("Ver todas (45)", "Responder", "Crear evento"); los botones con caja se reservan para acciones principales ("Subir fotos", "Mensaje privado", "Invitar").
6. **Contadores entre paréntesis** junto a cada enlace o título: `Álbumes (3)`, `Ver todos (60)`, `Chat (11)`.

---

## 2. Estructura global de la página

```
┌──────────────────────────────────────────────────────────────┐
│ HEADER (barra azul fija)                                      │  ~38 px
├──────────────────────────────────────────────────────────────┤
│ STATUS BAR (avatar + "¿Cómo va todo?" + Subir fotos)          │  ~36 px  (solo en algunas vistas)
├───────────────┬──────────────────────────────┬───────────────┤
│ COL. IZQUIERDA│ COL. CENTRAL                 │ COL. DERECHA  │
│ 240 px        │ 500 px                       │ 190 px        │
│               │                              │               │
├───────────────┴──────────────────────────────┴───────────────┤
│ FOOTER (copyright · idiomas · enlaces legales)                │
└──────────────────────────────────────────────────────────────┘
  CHAT DOCK (fijo abajo, ventanas de conversación flotantes)
```

### 2.1 Retícula

| Elemento                 | Valor orientativo                   |
| ------------------------ | ----------------------------------- |
| Contenedor               | 990 px, centrado (`margin: 0 auto`) |
| Padding contenedor       | 10 px horizontal                    |
| Columna izquierda        | 240 px                              |
| Columna central          | 500 px (flexible)                   |
| Columna derecha          | 190 px                              |
| Separación (gutter)      | 20 px                               |
| Separación entre módulos | 16–20 px vertical                   |

```css
.layout {
  max-width: 990px;
  margin: 0 auto;
  padding: 0 10px;
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr) 190px;
  grid-template-areas: "left main right";
  gap: 20px;
}
.layout__left {
  grid-area: left;
}
.layout__main {
  grid-area: main;
}
.layout__right {
  grid-area: right;
}
```

### 2.2 Esqueleto HTML

```html
<body>
  <header class="topbar">…</header>
  <div class="statusbar">…</div>
  <!-- opcional según vista -->
  <div class="layout">
    <aside class="layout__left">…módulos…</aside>
    <main class="layout__main">…módulos…</main>
    <aside class="layout__right">…módulos…</aside>
  </div>
  <footer class="footer">…</footer>
  <div class="chat-dock">…</div>
</body>
```

---

## 3. Header (barra superior)

Barra horizontal azul con degradado, a todo el ancho de la ventana; su contenido respeta el contenedor de 990 px.

```
[logo tuentidad] Inicio  Perfil  Mensajes(1)  Gente  Vídeos  [Buscar…  🔍] [Subir fotos ⬆]      Chat(11)  Mi cuenta  Salir
```

| Zona                  | Contenido                                 | Notas                                                                 |
| --------------------- | ----------------------------------------- | --------------------------------------------------------------------- |
| Izquierda             | Logotipo (icono + texto en blanco)        | Enlaza a Inicio                                                       |
| Navegación principal  | Inicio, Perfil, Mensajes, Gente, Vídeos   | Pestaña activa con fondo azul más oscuro a toda la altura de la barra |
| Badge de notificación | Número en una pastilla junto a "Mensajes" | Ver §7.6                                                              |
| Buscador              | Input blanco con icono de lupa            | ~220 px de ancho                                                      |
| Acción principal      | Botón **Subir fotos** con flecha          | Botón oscuro sobre la barra                                           |
| Derecha               | Chat (n), Mi cuenta / Preferencias, Salir | "Chat" despliega la lista de amigos conectados (§8)                   |

Implementación: `display:flex; align-items:center;` con la zona derecha empujada con `margin-left:auto`. Altura fija ~38 px; se recomienda `position: sticky; top: 0`.

---

## 4. Status bar (barra de estado)

Franja gris clara bajo el header, presente en Inicio y en versiones del perfil propio.

```
[avatar 24px] [ ¿Cómo va todo?            ✏ ] hace 2 horas                        [⬆ Subir fotos]
```

- Avatar mini del usuario actual.
- Input de estado de una línea con placeholder "¿Cómo va todo?" (o el último estado, p. ej. "Tuenti rocks!") y botón **Cambiar**.
- Marca temporal relativa del último cambio ("hace 2 horas").
- Botón secundario "Subir fotos" alineado a la derecha.

---

## 5. Plantillas de página

### 5.1 Inicio (`/`)

| Columna izquierda                                                                                                           | Columna central                                                    | Columna derecha                                               |
| --------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ | ------------------------------------------------------------- |
| **Tarjeta de usuario**: nombre, visitas al perfil, notificaciones (invitaciones a eventos, etiquetas, comentarios en fotos) | **Novedades desde la última vez que entraste** (feed de actividad) | **Añadir amigos** (importar contactos, botón "Buscar amigos") |
| **Invitar a tus amigos**: invitaciones disponibles + input email + botón "Invitar"                                          | Botón/enlace **Ver más** al final                                  | **Chat (n)**: lista de amigos conectados + "Ajustes"          |
| **Eventos patrocinados**                                                                                                    |                                                                    |                                                               |
| **Calendario**: "Crear evento"; grupos Hoy / Mañana / Esta semana (cumpleaños y eventos)                                    |                                                                    |                                                               |

### 5.2 Perfil de otro usuario (`/perfil/:id`)

| Columna izquierda                                                                      | Columna central                                                                 | Columna derecha                                                      |
| -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| **Foto de perfil** grande (~200×230 px) con marco                                      | **Cabecera de perfil**: nombre + relación ("Amigo/Amiga")                       | **Fotos**: rejilla 2×3 + desplegable "Álbumes (n)" + "Ver todas (n)" |
| **Redes**: Estudios universitarios, Trayectoria profesional, Colegios, Zonas de marcha | Enlaces/acciones: Ver álbumes de fotos (n), Vídeos ▾, botón **Mensaje privado** | **Amigos en común**: rejilla de 3 + "Ver todos (n)"                  |
| **Información** (datos personales)                                                     | **Caja de estado** (último estado + tiempo)                                     | **Mis amigos / Sus amigos**: rejilla de 3 con contadores             |
|                                                                                        | **Espacio personal**: entrada actual + paginador "1 de 17"                      |                                                                      |
|                                                                                        | **Fotos en las que sale X** (variante): rejilla de 5 columnas + "Ver álbumes"   |                                                                      |
|                                                                                        | **Tablón**: caja para escribir + lista de mensajes                              |                                                                      |

### 5.3 Perfil propio (`/perfil`)

Igual que 5.2 con estas diferencias:

- Acciones de cabecera: **Editar mi perfil**, **Mis álbumes de fotos (n)**, **Mis vídeos** (botones con icono).
- "Mi espacio personal" con acción **Nueva entrada** en la cabecera del módulo.
- "Fotos en las que salgo".
- Las entradas del tablón muestran **Responder**.
- No aparece "Amigos en común".

---

## 6. Inventario de módulos

Todos los módulos comparten la misma anatomía:

```html
<section class="module">
  <header class="module__header">
    <h2 class="module__title">Amigos en común</h2>
    <a class="module__action">Ver álbumes</a>
    <!-- opcional, alineado a la derecha -->
  </header>
  <div class="module__body">…</div>
  <footer class="module__footer"><a>Ver todos (60)</a></footer>
  <!-- opcional -->
</section>
```

Hay **dos variantes de cabecera** (las capturas muestran ambas generaciones de diseño); elegir una para todo el proyecto:

- **`module--plain`** (diseño más reciente): título negrita 14–17 px sobre fondo blanco con línea inferior gris de 1 px.
- **`module--boxed`** (diseño clásico): cabecera con fondo azul muy claro (`--c-header-bg`), título 12 px negrita, cuerpo con borde de 1 px.

### 6.1 Columna izquierda

| Módulo                        | Contenido                                                                           | Detalles                                                                                                  |
| ----------------------------- | ----------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| `ProfilePhoto`                | Imagen de perfil                                                                    | Marco blanco con borde gris de 1 px y padding ~6 px                                                       |
| `UserCard`                    | Nombre (enlace), "756 visitas a tu perfil" con icono de gráfica, notificaciones     | Notificaciones en verde con icono; miniatura de la foto etiquetada si aplica                              |
| `ProfileNetworks` ("Redes")   | Grupos: Estudios universitarios, Trayectoria profesional, Colegios, Zonas de marcha | Etiqueta de grupo en negrita; valor como enlace azul; detalle en negro; años en gris ("2006-actualmente") |
| `ProfileInfo` ("Información") | Pares etiqueta/valor                                                                | Mismo estilo que Redes                                                                                    |
| `InviteFriends`               | "26 invitaciones disponibles" + input email + botón "Invitar"                       | Input y botón en la misma línea                                                                           |
| `SponsoredEvents`             | Lista de eventos patrocinados                                                       | Icono + enlace + fecha y contador en gris                                                                 |
| `Calendar`                    | Agrupaciones Hoy / Mañana / Esta semana                                             | Acción "Crear evento" en la cabecera; estado vacío en gris ("No tienes ningún evento")                    |

### 6.2 Columna central

| Módulo                        | Contenido                                                                                                                 |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `ProfileHeader`               | Nombre (22–24 px, peso normal) + relación en gris pequeño; línea de acciones debajo                                       |
| `StatusBox`                   | Fondo gris claro, borde 1 px, texto del estado 14 px + marca temporal en gris 11 px. Variante "bocadillo" junto al nombre |
| `PersonalSpace`               | Título de entrada + fecha; cuerpo con `MediaItem` (vídeo); pie con `Pager`                                                |
| `PhotoGrid` (central)         | Rejilla de 5 columnas de miniaturas ~75 px                                                                                |
| `Wall` ("Tablón")             | `Composer` + lista de `WallPost`                                                                                          |
| `ActivityFeed` ("Novedades…") | Lista de `FeedItem` + "Ver más"                                                                                           |

### 6.3 Columna derecha

| Módulo                                          | Contenido                                                                                                         |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `PhotosSidebar` ("Fotos")                       | Rejilla 2 columnas × 3 filas (~75 px), desplegable "Álbumes (3) ▾" en la cabecera, pie "Ver todas (45)"           |
| `FriendsGrid` ("Amigos en común", "Mis amigos") | Rejilla de 3 columnas: avatar ~50 px, punto verde si está conectado, nombre en 2 líneas, contador opcional "(14)" |
| `AddFriends`                                    | Texto + botón "Buscar amigos"                                                                                     |
| `ChatList` ("Chat (11)")                        | Lista vertical de nombres con punto verde; pie "Ajustes"                                                          |

---

## 7. Componentes y patrones

### 7.1 `MediaItem` (vídeo en espacio personal)

- Miniatura ~100×66 px con **duración superpuesta** abajo a la derecha (fondo negro semitransparente, icono ▶, texto blanco "3:22").
- A la derecha: título en negrita, "14 vistas" en gris, botones pequeños de icono (compartir, añadir).
- Variante sin reproducción local: "Este vídeo solo está disponible en Youtube".

### 7.2 `Pager`

- Alineado a la derecha: texto "1 de 17" + cuatro botones (primero, anterior, siguiente, último) con flechas.
- Botones grises, deshabilitados con menor opacidad en los extremos.

### 7.3 `Composer` (escribir en el tablón)

- Caja gris con borde; avatar del usuario actual (~32 px) a la izquierda; textarea de una línea "Escribe aquí…" que se expande al enfocar y muestra botón de enviar.

### 7.4 `WallPost` / `FeedItem`

Patrón _media object_: avatar a la izquierda, contenido a la derecha.

```
[avatar 40px]  Autor (enlace, negrita)  13 de Dic de 2009, a las 02:12     Responder
               Texto del mensaje…
               💬 2 comentarios · 🏷 1 foto etiquetada        (FeedItem)
               [miniatura]
───────────────────────────────────────────────────────── separador 1 px
```

- Autor en azul negrita 13 px; fecha en gris 11 px (formato largo en el tablón, relativo "hace 43 minutos" en el feed).
- En el feed, el texto del estado va en la misma línea que el nombre.
- Metadatos en verde con icono (comentarios, entradas, fotos etiquetadas, amigos).
- Vista previa del último comentario indentada, en gris, con "y 1 más".
- Avatar doble (superpuesto) cuando el mensaje es una respuesta.

```css
.media {
  display: flex;
  gap: 10px;
  padding: 12px 0;
  border-bottom: 1px solid var(--c-border);
}
.media__avatar {
  flex: 0 0 40px;
}
.media__body {
  flex: 1;
  min-width: 0;
}
```

### 7.5 `Avatar`

| Tamaño   | Uso                      |
| -------- | ------------------------ |
| 24 px    | Status bar, dock de chat |
| 32 px    | Composer                 |
| 40 px    | Tablón, feed             |
| 50–55 px | Rejillas de amigos       |
| ~75 px   | Rejillas de fotos        |
| ~200 px  | Foto de perfil           |

Siempre con borde de 1 px gris y fondo blanco. Indicador de conexión: círculo verde de 8 px en la esquina superior derecha.

### 7.6 Badges y contadores

- Badge de notificación en el header: pastilla blanca/azul con número, junto al enlace.
- Contadores inline entre paréntesis en gris: `(10)`.
- Contadores con icono verde en rejillas de amigos: `💬 5`.

### 7.7 Botones

| Tipo               | Uso                                              | Estilo                                                                                   |
| ------------------ | ------------------------------------------------ | ---------------------------------------------------------------------------------------- |
| Primario           | Subir fotos (header)                             | Fondo azul oscuro, texto blanco, icono                                                   |
| Secundario         | Mensaje privado, Invitar, Cambiar, Buscar amigos | Degradado gris claro → blanco, borde gris, texto gris oscuro, icono opcional, radio 3 px |
| Acción de cabecera | Editar mi perfil, Mis vídeos, Ver álbumes        | Secundario con icono a la izquierda                                                      |
| Icono              | Compartir, añadir, paginador                     | Cuadrado ~20 px, secundario                                                              |

### 7.8 Menú contextual sobre usuario

Al pasar o hacer clic sobre un amigo se abre un menú flotante con: **Bloquear**, **Mensaje privado**, **Ver su perfil**. Caja blanca con sombra, iconos a la izquierda.

### 7.9 Desplegables

"Vídeos ▾", "Álbumes (3) ▾" e idioma del footer usan el mismo componente `Dropdown`: disparador con triángulo, panel blanco con borde y sombra.

---

## 8. Chat

1. **Lista de contactos**: se abre desde "Chat (n)" en el header como panel desplegable alineado a la derecha, o como módulo fijo en la columna derecha de Inicio. Cada fila: punto verde + nombre. Pie: "Ajustes".
2. **Ventana de conversación**: flotante, anclada abajo a la izquierda (~230 px de ancho). Cabecera azul con punto de estado + nombre del contacto + cerrar; cuerpo con mensajes en formato `Nombre: texto` (nombre en negrita); input en la parte inferior.
3. **Dock inferior**: barra fija al pie de la ventana con los avatares de las conversaciones abiertas o minimizadas.

```css
.chat-dock {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 32px;
  z-index: 100;
}
.chat-window {
  position: fixed;
  bottom: 32px;
  width: 230px;
}
```

---

## 9. Footer

```
© Tuentidad 2026   [Idioma ▾]                Publicidad  Empleo  Blog  Condiciones de uso  Móvil  Ayuda
```

- Línea superior de 1 px, texto gris 11 px.
- Selector de idioma desplegable hacia arriba: English, Català, Galego, Euskara, Castellano (el actual en gris, no clicable).

---

## 10. Tokens de diseño (estimados)

```css
:root {
  /* Marca */
  --c-brand: #4a7db5; /* barra superior (parte alta del degradado) */
  --c-brand-dark: #2f5f94; /* pestaña activa, botón primario */
  --c-link: #2b5f94; /* enlaces y nombres */
  --c-header-bg: #dce8f3; /* cabecera de módulo en variante boxed */

  /* Neutros */
  --c-text: #333333;
  --c-text-muted: #888888; /* fechas, contadores */
  --c-border: #d8d8d8;
  --c-surface: #ffffff;
  --c-surface-alt: #f2f2f2; /* status box, composer */

  /* Estados */
  --c-online: #6bbf3a; /* punto de conexión */
  --c-notify: #4c9a2a; /* notificaciones y metadatos verdes */

  /* Tipografía */
  --font-base: "Lucida Grande", "Helvetica Neue", Arial, sans-serif;
  --fs-xs: 11px; /* fechas, metadatos */
  --fs-sm: 12px; /* texto general */
  --fs-md: 13px; /* mensajes, nombres */
  --fs-lg: 17px; /* títulos de módulo (variante plain) */
  --fs-xl: 24px; /* nombre en el perfil */

  /* Espaciado y forma */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --radius: 3px;
}
```

> Recomendación: muestrear los colores definitivos con el diseño de marca de Tuentidad y conservar solo la _relación_ entre ellos (azul de marca, enlaces azules, verde para actividad, grises de soporte).

---

## 11. Adaptación responsive

La interfaz original no era responsive. Propuesta para Tuentidad:

| Rango      | Disposición                                                                                                                                                                  |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ≥ 1000 px  | Tres columnas (240 / flexible / 190)                                                                                                                                         |
| 760–999 px | Dos columnas: izquierda + central; los módulos de la derecha pasan al final de la columna izquierda                                                                          |
| < 760 px   | Una columna: orden _ProfileHeader → StatusBox → contenido central → columna izquierda → columna derecha_. Navegación principal en menú hamburguesa; chat a pantalla completa |

```css
@media (max-width: 999px) {
  .layout {
    grid-template-columns: 240px minmax(0, 1fr);
    grid-template-areas: "left main" "right main";
  }
}
@media (max-width: 759px) {
  .layout {
    grid-template-columns: 1fr;
    grid-template-areas: "main" "left" "right";
  }
}
```

En móvil la foto de perfil se reduce a ~96 px y se coloca junto al nombre, dentro de `ProfileHeader`.

---

## 12. Accesibilidad y semántica

- Columnas como `<aside>` / `<main>`; cada módulo como `<section>` con `<h2>`.
- El indicador de conexión no debe depender solo del color: añadir texto oculto ("conectado").
- Los botones de icono (compartir, paginador) necesitan `aria-label`.
- Fechas en `<time datetime="…">` aunque se muestren en formato relativo.
- Contraste mínimo AA: revisar los grises de metadatos sobre blanco.

---

## 13. Árbol de componentes sugerido

```
App
├── Topbar (Logo, MainNav, Badge, SearchBox, Button, ChatToggle, UserMenu)
├── StatusBar (Avatar, StatusInput)
├── Layout
│   ├── LeftColumn  → ProfilePhoto | UserCard | ProfileNetworks | ProfileInfo | InviteFriends | SponsoredEvents | Calendar
│   ├── MainColumn  → ProfileHeader | StatusBox | PersonalSpace(MediaItem, Pager) | PhotoGrid | Wall(Composer, WallPost[]) | ActivityFeed(FeedItem[])
│   └── RightColumn → PhotosSidebar | FriendsGrid | AddFriends | ChatList
├── Footer (LanguageDropdown, FooterLinks)
└── ChatDock (ChatWindow[])

Primitivos compartidos: Module, Avatar, Button, Dropdown, Pager, MediaObject, ContextMenu, Badge, Counter
```

---

## 14. Checklist de implementación

- [ ] Tokens de §10 definidos como variables CSS.
- [ ] Componente `Module` con variantes `plain` y `boxed` (elegir una por defecto).
- [ ] Layout de tres columnas con sus dos puntos de corte.
- [ ] Topbar sticky con pestaña activa y badge.
- [ ] Primitivos: `Avatar` (tamaños e indicador), `Button`, `Dropdown`, `Pager`, `MediaObject`.
- [ ] Plantillas: Inicio, Perfil ajeno, Perfil propio.
- [ ] Chat: lista, ventana flotante y dock.
- [ ] Footer con selector de idioma.
- [ ] Revisión de accesibilidad (§12).
