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
NotebookLM (Pinnacle WI KB)        ──► contexto / knowledge base
Replicate   (pinnacle-alex token)  ──► imágenes y videos variados
Nano Banana (admin@geocarpentry)   ──► imágenes premium (Gemini API Pro)
                    │
                    ▼
             Agente Creativo
             (texto + imagen)
                    │
                    ▼
             Organizador
             (cola / calendario)
                    │
                    ▼
             Publicador
                    │
                    ▼
          Meta Graph API
           /            \
     Facebook          Instagram
     Page(s)           Business Account
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
- Conectar NotebookLM como fuente de contexto del negocio
- Conectar Replicate (`pinnacle-alex`) para generación de imágenes
- Conectar Nano Banana / Gemini API (cuenta `admin@geocarpentry.com`) para imágenes premium
- El Jefe da un tema → el Creativo genera texto + imagen → el Organizador lo encola → el Publicador lo publica

### Fase 2.5 — Polish + App Review de Meta
- Publicar la Política de Privacidad (requisito de Meta)
- Solicitar App Review para salir del modo desarrollo
- La app queda "Live" — puede publicar en cualquier página sin modo dev
- Ajustes finales, manejo de errores, logs

---

---

## Integraciones Confirmadas del Agente Creativo

> Estas cuentas y tokens ya existen — el Jefe los configuró fuera del repo.
> No hay código aún, pero las credenciales están listas para cuando lleguemos a Fase 2.4.

### NotebookLM (Google)
- **Cuenta:** `geocarpentryllc@gmail.com`
- **Notebook activo:** "Pinnacle WI Knowledge Base" (10 sources cargados)
- **Rol en el sistema:** Fuente de conocimiento del negocio. El Creativo consulta este notebook
  para generar posts con contexto real de Pinnacle Wisconsin (servicios, zonas, proyectos, tono).
- **Estado:** Cuenta configurada. Código pendiente (Fase 2.4).

### Replicate
- **Usuario:** `geocarp24`
- **Token:** `pinnacle-alex` (prefijo `r8_Z3w...`) — el valor completo va en `.env` local.
- **Rol en el sistema:** Generación de imágenes y videos variados con modelos open-source.
  Más flexible que una API única — permite probar distintos modelos (Flux, SDXL, etc.).
- **Estado:** Cuenta configurada. Código pendiente (Fase 2.4).

### Nano Banana — Gemini API (Google)
- **IMPORTANTE — dos cuentas, usar la correcta:**
  - `geocarpentryllc@gmail.com` → Free tier. Tiene API key pero es la secundaria. No usar en producción.
  - `admin@geocarpentry.com` → **cuenta Pro con Nano Banana. ESTA es la de producción.**
- **Rol en el sistema:** Generación de imágenes premium. Complementa a Replicate para posts de mayor calidad.
- **Estado:** Cuenta Pro activa. API key pendiente de copiar en `.env`. Código pendiente (Fase 2.4).

### Meta for Developers
- **App:** Pinnacle Social Publisher
- **App ID:** `4233439163564604`
- **Business ID:** `800555019765952`
- **Estado:** App creada, Unpublished (modo desarrollo). Configurando permisos — Fase 2.1 en curso.

---

*Documento creado: 2026-04-23 | Última actualización: 2026-04-23*
*Autor: ALEX — Sistema Multi-Agente de Inversión Inmobiliaria + Marketing*
