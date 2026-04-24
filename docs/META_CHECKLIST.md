# Checklist: Configuración App de Meta — Pinnacle Social Publisher

## Estado Actual de la App

| Campo | Valor |
|-------|-------|
| Nombre | Pinnacle Social Publisher |
| App ID | `4233439163564604` |
| Business ID | `800555019765952` |
| Estado | Unpublished (modo desarrollo) |
| Plataforma | Meta for Developers |

---

## Permisos que Necesitamos Activar (Use Cases)

### Use Case 1: "Manage everything on your Page"
Permite leer y publicar en Facebook Pages.

| Permiso | Para qué sirve |
|---------|---------------|
| `pages_show_list` | Ver qué páginas administra el Jefe |
| `pages_manage_posts` | Crear y publicar posts en la página |
| `pages_read_engagement` | Leer likes, comentarios, estadísticas |
| `pages_manage_metadata` | Ver la info básica de la página |

### Use Case 2: "Access the Instagram API with Instagram Login"
Permite publicar en Instagram Business.

| Permiso | Para qué sirve |
|---------|---------------|
| `instagram_business_basic` | Acceso básico a la cuenta de Instagram |
| `instagram_business_content_publish` | Publicar posts e historias en Instagram |

---

## Requisitos Previos (Confirmar antes de seguir)

- [ ] **Página de Facebook de GEO Carpentry** — confirmar cuál es la página exacta que vamos a conectar
- [ ] **Cuenta de Instagram en modo Business o Creator** — el Jefe confirmó que está vinculada a la página de FB ✅
- [ ] **Política de Privacidad publicada en algún URL** — necesaria para el App Review final (Fase 2.5, no urgente ahora)

---

## Variables de Entorno Que Vamos a Necesitar

Estas variables van en un archivo `.env` **local** — **nunca se comparten por chat, nunca se suben al repo.**

```
# ── META / FACEBOOK + INSTAGRAM ──────────────────────────────────────────
META_APP_ID=4233439163564604        # App ID de Pinnacle Social Publisher
META_APP_SECRET=...                 # El Jefe lo pega solo — nunca al chat
META_PAGE_ACCESS_TOKEN=...          # Token para publicar en la Facebook Page
META_PAGE_ID=...                    # ID numérico de la Facebook Page de GEO Carpentry
META_IG_BUSINESS_ID=...             # ID numérico de la cuenta de Instagram Business

# ── NOTEBOOKLM ───────────────────────────────────────────────────────────
NOTEBOOKLM_NOTEBOOK_ID=...          # ID del notebook "Pinnacle WI Knowledge Base"
                                    # Cuenta: geocarpentryllc@gmail.com

# ── REPLICATE ────────────────────────────────────────────────────────────
REPLICATE_API_TOKEN=r8_Z3w...       # Token "pinnacle-alex" — usuario geocarp24
                                    # El Jefe pega el token completo localmente

# ── GEMINI API (NANO BANANA) ──────────────────────────────────────────────
GEMINI_API_KEY=...                  # USAR cuenta admin@geocarpentry.com (Pro)
                                    # NO usar geocarpentryllc@gmail.com (Free tier)
```

> **Regla de oro:** Ningún secreto va al chat ni al repo. El Jefe los copia directamente en `.env` en su máquina.

---

## Alerta: Dos Cuentas de Google — No Confundirse

| Cuenta | Tier | Uso |
|--------|------|-----|
| `geocarpentryllc@gmail.com` | Free | NotebookLM, secundaria. NO usar para Gemini en producción. |
| `admin@geocarpentry.com` | **Pro** | **Gemini API / Nano Banana. ESTA es la de producción.** |

La API key de Gemini que va en `.env` debe ser la de `admin@geocarpentry.com`.

---

## Orden Recomendado: Lo que Hacemos Juntos la Próxima Sesión

### Paso 1 — Activar Use Cases en Meta Developers
1. Ir a `developers.facebook.com` → App "Pinnacle Social Publisher"
2. Sidebar: **Use Cases** → Add use case
3. Agregar "Manage everything on your Page" → guardar
4. Agregar "Access the Instagram API with Instagram Login" → guardar

### Paso 2 — Obtener el App Secret
1. En la app de Meta → **Settings** → **Basic**
2. Copiar el App Secret
3. Pegarlo en el archivo `.env` local (no compartir)

### Paso 3 — Generar un Token de Prueba (Page Access Token)
1. En Meta Developers → **Tools** → **Graph API Explorer**
2. Seleccionar la app "Pinnacle Social Publisher"
3. Elegir la Facebook Page de GEO Carpentry
4. Generar token con los permisos de página
5. Copiar el token → pegar en `.env` como `META_PAGE_ACCESS_TOKEN`

### Paso 4 — Obtener el Page ID y el Instagram Business ID
1. Ir a la Facebook Page de GEO Carpentry → **About** → copiar el Page ID numérico
2. En Graph API Explorer: hacer llamada `GET /me/accounts` para confirmar el Page ID
3. Hacer llamada `GET /{page-id}?fields=instagram_business_account` para obtener el IG Business ID
4. Pegar ambos en `.env`

### Paso 5 — Primera Prueba (¡el momento de verdad!)
- Con todos los valores en `.env`, corremos el Publicador mínimo (Fase 2.2)
- Publicamos un post de texto de prueba en la página de GEO Carpentry
- Si aparece en Facebook → Fase 2.1 completada ✅

---

## Qué NO Hacemos Todavía

- **App Review de Meta:** viene al final (Fase 2.5). En modo desarrollo podemos probar todo sin App Review.
- **Publicar en cuentas que no son del Jefe:** en modo dev, la app solo puede tocar páginas del propio usuario.
- **Conectar el Agente Creativo:** eso viene en Fase 2.4, cuando el Publicador ya funcione solo.
- **Subir credenciales al repo:** nunca, sin excepción.

---

*Documento creado: 2026-04-23*
*Estado: Estamos en Fase 2.1 — configurando la app en Meta for Developers*
