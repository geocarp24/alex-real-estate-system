# FASE 2 — Publicador Social Directo (Sin Blotato)

## Qué es la Fase 2

Reemplazamos Blotato. En lugar de depender de una herramienta externa para publicar en redes,
conectamos el sistema ALEX directo a Facebook e Instagram usando la API oficial de Meta (Graph API).
El resultado: publicamos posts, imágenes y videos desde ALEX, sin intermediarios, sin costo mensual por Blotato.

**Meta de producción:** 3 posts por día entre Facebook + Instagram.

---

## Pipeline Completo

```
┌────────────────────────┐
│ Agente Social Media    │ ← genera IDEAS (concepto, copy borrador, tema)
│ (usa NotebookLM KB)    │
└──────────┬─────────────┘
           ▼
┌────────────────────────┐
│ Agente Oráculo         │ ← APRUEBA o RECHAZA (gate de calidad/marca)
└──────────┬─────────────┘
           │ approved
           ▼
┌────────────────────────┐
│ Herramientas creación  │ ← imágenes / reels / videos
│ (Replicate + Nano      │   Solo corren si Oráculo aprobó.
│  Banana via Gemini)    │
└──────────┬─────────────┘
           ▼
┌────────────────────────┐
│ Organizador/Publicador │ ← schedule + publish
│ (Meta Graph API)       │
└──────────┬─────────────┘
           ▼
      Facebook + Instagram
      Meta: 3 posts/día
```

---

## Las 4 Piezas del Sistema

### 1. Agente Social Media
- **Qué hace:** Genera ideas de contenido. Solo produce concepto — no genera media.
- **Output:** Tema del post + copy borrador + tipo de media sugerido (foto, video, carrusel).
- **Fuente de contexto:** NotebookLM ("Pinnacle WI Knowledge Base") — servicios, proyectos,
  tono de marca, zonas de trabajo de GEO Carpentry y Pinnacle Wisconsin.
- **No hace:** No genera imágenes ni videos. No publica nada.
- **Estado:** Por construir (Fase 2.4).

### 2. Agente Oráculo — Gate de Calidad
- **Qué hace:** Revisa el concepto del Agente Social Media y decide: **Approve** o **Reject**.
- **Por qué importa:** Replicate y Nano Banana cuestan créditos reales.
  El Oráculo filtra ANTES de gastarlos. Solo se genera media cuando el concepto pasó el filtro.
- **Output:** `approved` + razón breve, o `rejected` + razón. Nada más.
- **Pregunta abierta:** ¿El Oráculo usa reglas hard-coded o es un LLM con prompt de review?
  (Ver sección Preguntas Abiertas)
- **Estado:** Por construir (Fase 2.4).

### 3. Herramientas de Creación (tools, no agente)
- **Qué son:** Funciones que el pipeline llama cuando el Oráculo aprobó.
  No son un agente independiente — son tools ejecutadas en secuencia.
- **Replicate** (token `pinnacle-alex`, usuario `geocarp24`) →
  imágenes y videos variados con modelos open-source (Flux, SDXL, etc.).
- **Nano Banana / Gemini API** (cuenta `admin@geocarpentry.com`, Pro) →
  imágenes premium. ⚠️ Usar ESTA cuenta, no `geocarpentryllc@gmail.com` (Free).
- **Estado:** APIs configuradas. Código pendiente (Fase 2.4).

### 4. Organizador + Publicador
- **Organizador:** arma la cola de posts aprobados y asigna horarios.
  Meta: 3 posts por día, distribuidos a lo largo del día.
- **Publicador:** executor final. Lee la cola y llama a la Meta Graph API para publicar
  en Facebook Pages + Instagram Business. Reporta resultado (éxito / error).
- **Estado:** Por construir (Fase 2.2 el publicador mínimo, Fase 2.3 el organizador).

---

## Fases de Construcción

### Fase 2.1 — Setup de la App de Meta ← ESTAMOS AQUÍ
- Crear app "Pinnacle Social Publisher" en Meta for Developers ✅
- App ID: `4233439163564604` | Business ID: `800555019765952` ✅
- Activar Use Cases: páginas de Facebook + Instagram Business
- Configurar OAuth y obtener Page Access Token
- Primera llamada de prueba a la Graph API

### Fase 2.2 — Publicador Mínimo
- Publicar un post de texto solo en una Facebook Page real
- Probar el pipeline de punta a punta: `.env` → Graph API → post visible en FB
- Sin Oráculo, sin Organizador, sin Creativo — solo el executor

### Fase 2.3 — Organizador
- Cola de posts con meta de 3/día
- Asignación de horarios
- Registro de historial (qué se publicó, cuándo, resultado)
- Decisión pendiente: Airtable (visual, ya conectado) o SQLite local (más rápido)

### Fase 2.4 — Agente Social Media + Oráculo + Herramientas
- Agente Social Media: ideas de contenido usando NotebookLM como KB
- Agente Oráculo: gate de calidad antes de gastar créditos en media
- Herramientas: Replicate + Nano Banana conectadas al pipeline
- Pipeline completo: idea → aprobación → media → cola → publicación

### Fase 2.5 — Polish + App Review de Meta
- Publicar Política de Privacidad (requisito de Meta para App Review)
- Solicitar App Review → la app queda "Live" (puede tocar páginas de terceros)
- Manejo de errores, logs, alertas por Telegram si falla una publicación
- Ajustes finales de horarios y mix de contenido

---

## Integraciones Confirmadas

> Cuentas y tokens configurados por el Jefe fuera del repo.
> No hay código aún — entran en Fase 2.4.

| Servicio | Cuenta / Usuario | Rol |
|----------|-----------------|-----|
| NotebookLM | `geocarpentryllc@gmail.com` | KB "Pinnacle WI" — contexto para el Agente Social Media |
| Replicate | `geocarp24` / token `pinnacle-alex` | Imágenes y videos variados (Flux, SDXL, etc.) |
| Gemini API (Nano Banana) | `admin@geocarpentry.com` (**Pro**) | Imágenes premium — ⚠️ NO usar la cuenta Free |
| Meta Graph API | App ID `4233439163564604` | Publicación en FB Pages + Instagram Business |

---

## Preguntas Abiertas (el Jefe responde cuando pueda)

Estas decisiones afectan el diseño. Quedan anotadas para no olvidarlas.

1. **¿Oráculo con reglas o con LLM?**
   ¿El Oráculo filtra con criterios hard-coded (ej. "rechazar si no menciona marca o zona")
   o es un segundo LLM con un prompt de reviewer de calidad/marca?

2. **¿Publicación 100% automática o el Jefe aprueba el post final?**
   ¿Todo corre auto (Social Media → Oráculo → media → publicación sin intervención),
   o el Jefe tiene un paso de aprobación final antes de que el Publicador ejecute?

3. **¿Qué mix de contenido para los 3 posts/día?**
   ¿Balance fijo (ej. 1 listing + 1 educativo + 1 community)?
   ¿O el Organizador decide según lo que haya en cola?
   ¿Qué tipos de contenido queremos: listings, behind-the-scenes, tips, promos, community?

4. **¿Dónde vive la cola del Organizador?**
   Airtable (ya conectado al CRM, el Jefe puede verlo en el navegador)
   o SQLite local (más rápido, sin costo, sin interfaz visual automática).

5. **¿FB + IG con el mismo contenido, o adaptado a cada red?**
   ¿Publicamos el mismo post simultáneo en ambas redes,
   o el Agente Social Media adapta el copy y el formato según la plataforma?

---

*Documento creado: 2026-04-23 | Última actualización: 2026-04-23*
*Autor: ALEX — Sistema Multi-Agente de Inversión Inmobiliaria + Marketing*
