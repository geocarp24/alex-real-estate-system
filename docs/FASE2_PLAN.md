# FASE 2 — Publicador Social Directo (Sin Blotato)

## Qué es la Fase 2

Reemplazamos Blotato. En lugar de depender de una herramienta externa para publicar en redes,
conectamos el sistema ALEX directo a Facebook e Instagram usando la API oficial de Meta (Graph API).
El resultado: publicamos posts, imágenes y videos desde ALEX, sin intermediarios, sin costo mensual por Blotato.

---

## Las 3 Piezas del Sistema

### 1. Agente Creativo
- **Qué hace:** Genera el contenido del post. Texto, imagen, video (si aplica).
- **Cómo funciona:** Recibe una instrucción ("crea un post de GEO Carpentry sobre decks de madera"),
  consulta el contexto del negocio, genera texto con Claude, genera imagen con Replicate o Nano Banana,
  y devuelve el paquete listo para publicar.
- **Tecnología (tres fuentes de entrada):**
  - **NotebookLM** (`geocarpentryllc@gmail.com`, notebook "Pinnacle WI Knowledge Base") →
    provee contexto del negocio: servicios, zonas, proyectos, tono de marca.
  - **Replicate** (token `pinnacle-alex`, usuario `geocarp24`) →
    generación de imágenes y videos variados vía modelos open-source.
  - **Nano Banana / Gemini API** (cuenta `admin@geocarpentry.com`, Pro) →
    generación de imágenes premium. **Usar esta cuenta, no la de geocarpentryllc.**
- **Estado actual:** Por construir (Fase 2.4).

### 2. Organizador
- **Qué hace:** Decide qué publicar, cuándo y en qué red. Maneja la cola de posts.
- **Funciones:**
  - Calendario de publicaciones
  - Prioridades (¿qué va primero?)
  - Cola: posts listos esperando su turno
  - Registro de lo que ya se publicó
- **Estado actual:** Por construir (Fase 2.3).

### 3. Publicador
- **Qué hace:** Habla con la Graph API de Meta. Recibe la orden del Organizador
  ("publicá este contenido en esta página") y lo ejecuta.
- **Funciones:**
  - Publicar texto, imágenes y videos en Facebook Pages
  - Publicar en Instagram Business
  - Reportar resultado (éxito / error) al Organizador
- **Estado actual:** Por construir (Fase 2.2 — versión mínima primero).

---

## Flujo del Sistema

```
El Jefe
   │
   ▼
ALEX (Orquestador)
   │
   ├──► Agente Creativo
   │         │ genera texto + imagen
   │         ▼
   ├──► Organizador
   │         │ pone en cola, decide cuándo
   │         ▼
   └──► Publicador
              │ llama a la API
              ▼
        Meta Graph API
         /          \
   Facebook         Instagram
   Page(s)          Business Account
```

---

## Airtable como almacén del Organizador

**Pregunta abierta (decidir con el Jefe):**

¿Usamos Airtable como la cola de posts del Organizador, o una base local (SQLite)?

**Opción A — Airtable:**
- Ventaja: el Jefe puede ver y editar la cola desde el navegador, sin código.
- Desventaja: depende de conexión a internet y del plan de Airtable.

**Opción B — SQLite (archivo local):**
- Ventaja: más rápido, sin costo, sin dependencias externas.
- Desventaja: no hay interfaz visual sin construirla.

**Recomendación inicial:** Airtable (ya lo usamos para el CRM, el Jefe ya sabe mirarlo).
Decidir en la sesión de Fase 2.3.

---

## Fases de Construcción

### Fase 2.1 — Setup de la App de Meta ← ESTAMOS AQUÍ
- Crear app "Pinnacle Social Publisher" en Meta for Developers ✅
- Activar los Use Cases correctos (permisos de páginas e Instagram)
- Configurar OAuth (para que Meta nos deje publicar)
- Obtener los tokens de acceso de página (Page Access Token)
- Hacer la primera llamada de prueba a la Graph API

### Fase 2.2 — Publicador Mínimo
- Publicar un post de solo texto en una Facebook Page real
- Objetivo: probar que el pipeline funciona de punta a punta
- Sin agente creativo, sin organizador — solo el Publicador solo

### Fase 2.3 — Organizador
- Cola simple de posts (Airtable o SQLite — decisión pendiente)
- El Publicador lee la cola y publica en orden
- Sin lógica de horarios todavía

### Fase 2.4 — Agente Creativo
- Conectar Claude al flujo para generar el contenido
- El Jefe da un tema → el Creativo genera el post → el Organizador lo encola → el Publicador lo publica

### Fase 2.5 — Polish + App Review de Meta
- Publicar la Política de Privacidad (requisito de Meta)
- Solicitar App Review para salir del modo desarrollo
- La app queda "Live" — puede publicar en cualquier página sin modo dev
- Ajustes finales, manejo de errores, logs

---

---

## Integraciones Pendientes — Mencionadas por el Jefe

> El Jefe mencionó dos herramientas de Google que podrían alimentar el Agente Creativo.
> **No hay código de estas en el repo todavía.** Quedan pendientes de diseñar.

### NotebookLM (Google)
- **Qué es:** Herramienta de Google que lee documentos y genera resúmenes, podcasts, y análisis.
- **Uso potencial en el Creativo:** Alimentar al Agente Creativo con investigación estructurada.
  Ejemplo: el Jefe sube un PDF de tendencias de carpintería → NotebookLM lo procesa →
  el Creativo usa esa info para generar posts más precisos y con datos reales.
- **Estado:** Sin código en el repo. Decisión de diseño pendiente (Fase 2.4).

### Nano Banana / Imagen (Google)
- **Qué es:** Modelo de generación de imágenes de Google (posiblemente Imagen 3 o Gemini Imagen).
- **Uso potencial en el Creativo:** En lugar de usar DALL-E o Midjourney,
  generar imágenes de posts directamente con la API de Google.
- **Estado:** Sin código en el repo. Evaluar en Fase 2.4 cuando lleguemos al Agente Creativo.
  Comparar con alternativas (DALL-E 3, Stable Diffusion, Flux) antes de decidir.

> **Próximo paso:** Cuando el Jefe confirme qué APIs de Google está usando (cuál es exactamente
> "Nano Banana") y si ya tiene acceso/API key, lo integramos al diseño del Agente Creativo.

---

*Documento creado: 2026-04-23*
*Autor: ALEX — Sistema Multi-Agente de Inversión Inmobiliaria + Marketing*
