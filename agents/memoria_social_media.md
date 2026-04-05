# MEMORIA — SOCIAL MEDIA AGENT (Pinnacle Holdings)

> Leído al inicio de cada invocación. Actualizar después de cada sesión.
> Formato de fecha: YYYY-MM-DD

---

## 🧠 ROL Y MISIÓN

Eres el **Social Media Agent**, especialista en contenido digital de Pinnacle Holdings Group LLC.
Eres invocado por ALEX Orquestador. **Solo aceptas órdenes de ALEX.**

---

## 📊 ESTADO DE SISTEMAS — Auditado 2026-04-05

| Sistema | Estado | Detalle |
|---------|--------|---------|
| Airtable conexión | ✅ OK | Token válido, escritura confirmada |
| Airtable Ideas de Contenido | ✅ Escribe | Record test creado rec: recCM80pqccFhVLr2 |
| Airtable Publicaciones | ✅ Lista | Sin registros |
| Airtable Scripts de Video | ✅ Lista | Sin registros |
| Make.com webhook | ✅ HTTP 200 | Acepta payloads, responde "Accepted" |
| Make.com escenario 4636455 | ✅ ACTIVO | Activado por Jorge el 2026-04-05 |
| Facebook Business | ✅ 100% | Listo para publicar |
| Instagram @pinnacle.groupwi | ✅ 100% | Listo para publicar |
| Google Business Profile | ⏳ Verificación | Esperando aprobación video |
| LinkedIn Company Page | ❌ Pendiente | Por crear |
| Canva banners | ⚠️ Parcial | 4 diseños listos, sin foto Jorge |
| Blotato (video IA) | ❌ Pendiente | Configurar + conectar Make |

---

## 🔧 PROBLEMAS ENCONTRADOS Y ESTADO (2026-04-05)

### ✅ Ya arreglados por ALEX:
- Campo ` Hashtags` tenía espacio inicial → renombrado a `Hashtags`
- Campo ` Status` tenía espacio inicial → renombrado a `Status`
- Nombres reales de campos documentados (emoji en Caption EN/ES, etc.)
- Scripts de Video: ID real es `tbli9BsyIwrhwa3aS` (no el documentado antes)
- Flujo completo Airtable probado y confirmado

### ✅ Fixes manuales completados por Jorge el 2026-04-05:
1. `Formato` (Ideas de Contenido) → `Post | Reel | Carrusel | Story` ✅
2. `Tipo` (Ideas de Contenido) → `Educativo | Promocional | Personal` ✅
3. `Formato` (Publicaciones) → corregido ✅
4. Escenario Make ID 4636455 → ACTIVADO ✅

---

## 📋 INSTRUCCIONES PASO A PASO — Activar Make.com

1. Abrir `us2.make.com` e iniciar sesión con `fcmultiser@gmail.com`
2. Ir a **My Scenarios** → buscar "Pinnacle — Social Media Ideas → Airtable" (ID: 4636455)
3. Abrir el escenario — ver el toggle en la esquina superior izquierda
4. Si el toggle está gris (OFF) → click para poner en azul (ON)
5. Hacer click en **"Run once"** para probar
6. Enviar un webhook de prueba desde Telegram: `"prueba webhook social media"`
7. Verificar que se crea registro en Airtable Ideas de Contenido

**También verificar el mapeo del módulo Airtable dentro del escenario:**
- Módulo: Airtable (Create a Record)
- Base: Pinnacle Social Media (`appU9s3kGkVpdrJkw`)
- Table: Ideas de Contenido (`tblAj0Pkj1jW4p5Ld`)
- Mapeo de campos (usar los nombres EXACTOS con emojis):

| Campo Make | Campo Airtable |
|-----------|----------------|
| `titulo` | `Título de Idea` |
| `hook` | `Hook` |
| `caption_en` | `🇺🇸 Caption EN` |
| `caption_es` | `🇲🇽 Caption ES` |
| `hashtags` | `Hashtags` |
| `formato` | `Formato` |
| `plataforma` | `Plataforma` |
| `tipo` | `Tipo` |
| `semana` | `Semana` |

---

## 🧪 CÓMO PROBAR EL FLUJO COMPLETO DESDE TELEGRAM

Cuando el escenario Make esté activo, enviar al bot de Telegram:

> "Genera un post educativo para Facebook sobre foreclosure, semana 2, y guárdalo en Airtable"

El bot invocará `invoke_social_media` con `save_to_airtable=True` y el agente:
1. Generará el contenido completo
2. Hará POST a Airtable directo
3. También enviará al webhook Make.com para automatización

---

## 📋 IDEA RECORDS YA EN AIRTABLE (pendiente limpiar)

| Record ID | Título | Notas |
|-----------|--------|-------|
| recCM80pqccFhVLr2 | TEST ALEX — Verificación flujo completo | ELIMINAR — solo diagnóstico |

---

## 📋 IDEAS GENERADAS (Semanas 1-4) — Pendiente subir a Airtable

| # | Título | Semana | Formato | Status |
|---|--------|--------|---------|--------|
| 1 | S1 - Lanzamiento: ¿Quién es Jorge Cruz? | 1 | Post | Pendiente |
| 2 | S1 - Proceso en 5 pasos | 1 | Carrusel | Pendiente |
| 3 | S1 - Reel Presentación Jorge | 1 | Reel | Pendiente |
| 4 | S2 - Cash Buyer vs Realtor | 2 | Post | Pendiente |
| 5 | S2 - Foreclosure: Tienes Opciones | 2 | Post | Pendiente |
| 6 | S2 - Cualquier Condición | 2 | Post | Pendiente |
| 7 | S3 - Herencia: Heredaste una Propiedad | 3 | Carrusel | Pendiente |
| 8 | S3 - Divorcio: Solución para Propiedades | 3 | Post | Pendiente |
| 9 | S3 - Reel Behind the Scenes | 3 | Reel | Pendiente |
| 10 | S4 - Lead Magnet: ¿Cuánto vale tu casa? | 4 | Post | Pendiente |
| 11 | S4 - FAQ Carrusel | 4 | Carrusel | Pendiente |
| 12 | S4 - Urgencia: Comprando en Green Bay AHORA | 4 | Post | Pendiente |

---

## 📝 LECCIONES APRENDIDAS

| Fecha | Tipo de post | Plataforma | Resultado | Lección |
|-------|-------------|-----------|-----------|---------|
| — | — | — | — | — |

---

*Integrado al sistema ALEX: 2026-04-05*
*Auditado y corregido: 2026-04-05*
