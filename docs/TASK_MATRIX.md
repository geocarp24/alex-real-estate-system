# TASK_MATRIX.md — Qué hace cada agente, handoffs, no-dos

> Tabla maestra de responsabilidades. Dice exactamente qué tarea asignar a qué agente, el handoff al siguiente, y qué cosas un agente NO hace (para evitar solapamientos y confusión).
> Complementa `AGENT_REGISTRY.md` y `ARCHITECTURE.md`.
> Última actualización: 2026-04-22.

---

## 🎯 MATRIZ DE TAREAS POR CATEGORÍA

### 1. CAPTURA DE LEAD

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Recibir lead desde web form | `pinnacle_public.php` (bridge) | `el_polling` → Tracy | Fer NO recibe forms; espera SMS |
| Recibir lead desde email | `secretario` (email monitor) | Airtable + Telegram al Jefe | Fer NO lee emails |
| Recibir lead desde SMS entrante | `fer_agent.php` (webhook Quo) | Fer clasifica → si califica, Deal auto | Secretario NO toca SMS |
| Recibir lead manual del Jefe | ALEX | crea Lead directo en Airtable | Sub-agentes NO escriben leads manuales |
| Validar teléfono (SMS verify) | `pinnacle_public.php` | Genera código 6 dígitos + envía Quo | No delega |

### 2. CALIFICACIÓN DE LEAD (AI Receptionist)

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Primer contacto SMS | `fer_first_contact.php` (cron 15min) | Rotación Phone1→Phone4 → Fer conversa | Tracy NO escribe SMS |
| Conversación bilingüe SMS | **Fer** (Haiku) | Califica con 13 preguntas | ALEX NO escribe SMS; solo orquesta |
| Price discovery (3 strikes) | Fer | Escalation a Telegram si acepta | Matemático NO negocia precio |
| Calcular Fer Score | Fer (prompt-side) | 1-10 + HOT/WARM/COLD → Jefe | Fact-Checker NO califica leads (solo deals) |
| Escalation al Jefe | Fer | Telegram notification con datos completos | Sub-agentes de deal NO escalan |

### 3. SKIP TRACING

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Detectar Lead para skip trace | `el_polling.php` (cron 5min) | Dispara Tracy + marca Stage | ALEX NO hace polling; delega |
| Llamar API Tracerfy | `el_polling.php` (PHP) | `el_chismoso.php` procesa respuesta | Tracy prompt NO llama API; organiza resultado |
| Escribir Contacts en Airtable | `el_chismoso.php` | Contact linked a Lead via Property Address | Tracy prompt NO escribe directo |
| Dedup Contacts | `cleanup_duplicates.php` | One-shot manual con dry_run | No auto |
| Backfill links históricos | `backfill_links.php` | Manual | No auto |

### 4. ANÁLISIS DE DEAL (Fix & Flip / BRRRR / Buy & Hold)

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Parsear request del Jefe | **ALEX** | Identifica estrategia, invoca sub-agentes | Sub-agentes no parsean lenguaje natural |
| Investigar mercado, comps, riesgos | **El Scout** | Devuelve JSON a ALEX | Matemático NO mira mercado |
| Underwriting financiero | **El Matemático** | Devuelve JSON a ALEX | Scout NO calcula ARV/ROI |
| Auditar Scout + Matemático | **El Fact-Checker** | Confidence Score + veredicto | Scout/Matemático NO se auditan a sí mismos |
| Consolidar "Análisis Estándar" | **ALEX** | Presenta al Jefe | Sub-agentes NO hablan con Jefe directamente |
| Registrar lección en memoria | **ALEX** | `memoria_ALex.md` | Sub-agentes tienen su propia memoria (memoria_scout.md, etc.) |

**Handoff orquestado:**
```
Jefe → ALEX
       ├─→ Scout (paralelo) ────┐
       └─→ Matemático (paralelo)┤
                                └─→ Fact-Checker (sequential)
                                                ↓
                                        ALEX consolida → Jefe
```

### 5. SEGUIMIENTO AUTOMATIZADO

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| "Contacted" sin respuesta 5d | `fer_stale_cron.php` (diario 8am) | Stage → "Seguimiento" | ALEX NO mueve stages auto |
| Engine de 24 toques | `fer_seguimiento.php` (diario 9:30am) | SMS + Email según Step | Fer NO ejecuta el engine; es PHP |
| Paso Step 24 → "Dead" | `fer_seguimiento.php` | Stage Dead, cierra ciclo | - |
| Cliente responde mid-follow-up | Fer (webhook Quo) | Retoma conversación sin repetir | - |
| Morning brief diario | `fer_morning_brief.php` (8:30am) | Telegram al Jefe: pipeline + health | - |

### 6. SOCIAL MEDIA PIPELINE

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Generar ideas + captions EN/ES | **Social Media Agent** | Airtable SM base | Creativo NO genera copy |
| Generar carrusel/imagen | **El Creativo** | Blotato template 53cfec04 → visual_url | Director NO hace imágenes |
| Generar Reels/video | **El Director** | Blotato video → visual_url | Creativo NO hace videos |
| Publicar/programar FB+IG | **El Programador** | Blotato API → Blotato_Post_IDs | Social Media Agent NO publica |
| Reportar al Jefe resumen final | **ALEX** | Telegram después de pipeline | Sub-agentes NO reportan directo al Jefe |

**Regla de paralelismo:**
- Creativo y Director corren en paralelo (diferentes recursos)
- Programador SIEMPRE después de ambos
- Todo el pipeline es automático — NUNCA pedir confirmación entre fases

### 7. GESTIÓN DE EMAIL (El Secretario)

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Poll IMAP cada 5 min | `email_monitor.py` (systemd) | Lee inbox | - |
| Clasificar email | **El Secretario** (Haiku) | LEAD / URGENTE / RUTINARIO / SPAM | ALEX NO clasifica emails |
| LEAD → Airtable | El Secretario | Crea Lead + Telegram al Jefe | - |
| URGENTE → Telegram | El Secretario | Notificación inmediata al Jefe | - |
| Redactar respuesta email | El Secretario | Aprobación del Jefe ANTES de enviar | Fer NO redacta emails |
| Tracerfy emails | Regla especial | Auto-archivo → auto-delete 30d | - |

### 8. CALENDARIO (El Planificador)

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Resumen matutino de agenda | **El Planificador** (8am CST) | Telegram al Jefe | Secretario NO lee calendar |
| Recordatorios de cita | `calendar_manager.py` | Telegram 30min antes | - |
| Programar cita desde Telegram | Comando `/cita` | Google Calendar API | - |
| Ver agenda | Comando `/agenda` / `/agenda semana` | Telegram response | - |

### 9. REPORTES Y DASHBOARDS

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Morning brief operativo | `fer_morning_brief.php` | Telegram 8:30am | - |
| Estado del pipeline Airtable | ALEX on-demand | Resumen al Jefe | - |
| Analítica de deals cerrados | ALEX con skill `saas-metrics-coach` | Dashboard mensual | - |
| Monitoreo de sistema (health) | `fer_morning_brief.php` | Telegram si hay rojo | Jefe NO diagnostica manualmente |

### 10. MANTENIMIENTO DEL SISTEMA

| Tarea | Agente primario | Handoff a | Agente NO hace |
|---|---|---|---|
| Deploy de código a Hostinger | GitHub Actions `deploy-hostinger.yml` | Push a master → SCP + config files | ALEX NO deploya directo |
| Backup de site WP | `pinnacle_wp_bridge.php` + script Python | `backups/wp_pinnacle/{timestamp}/` | Manual, antes de cambios destructivos |
| Purgar cache LiteSpeed | Bridge action `purge_cache` | Después de update_post | - |
| Rotar credenciales | Jefe + ALEX | `.env.sandbox` + GitHub Secrets | Auto nunca |
| Actualizar memoria | ALEX al final de cada sesión | `memoria_ALex.md` + commit | Sub-agentes tienen memorias separadas |

---

## 🚫 NO-DOs POR AGENTE (para evitar confusión)

### ALEX
- NO ejecuta skip trace (delega a Tracy/el_polling)
- NO escribe SMS directo a leads (delega a Fer)
- NO publica en Social Media (delega a pipeline)
- NO clasifica emails (delega a Secretario)

### Fer
- NO analiza deals financieramente (ese es Matemático)
- NO hace skip trace (ese es Tracy)
- NO maneja social media
- NO envía emails (solo SMS)

### Scout / Matemático / Fact-Checker
- NO hablan con el cliente final (solo ALEX lo hace)
- NO tocan Airtable directo (devuelven JSON a ALEX)
- NO interactúan entre sí (solo vía ALEX)

### Tracy
- NO conversa con leads (solo rastrea)
- NO califica leads (solo enriquece contacts)
- NO mueve stages (eso lo hace el_polling)

### Secretario
- NO envía respuestas sin aprobación del Jefe
- NO toca SMS (eso es Fer)
- NO publica social media

### Social Media Agent / Creativo / Director / Programador
- NO tocan leads ni deals
- NO hacen análisis financiero
- NO hablan con el Jefe directo (vía ALEX)

### Workers PHP (el_polling, fer_cron, etc.)
- NO tienen AI (son lógica pura)
- NO deciden estrategia (siguen reglas hardcoded)
- SÍ disparan agentes AI cuando corresponde

---

## 🔄 HANDOFFS CRÍTICOS (para multi-tenant futuro)

Estos handoffs necesitan quedar limpios para que el sistema escale a N clientes:

1. **Web form → Lead** — actualmente hardcode a Airtable Pinnacle; multi-tenant requiere `client_id` routing
2. **Fer → Deal creation** — actualmente hardcode a base `appfQbDA750Oihy9J`; multi-tenant requiere base per client
3. **Tracy → Contacts** — mismo issue
4. **Social Media → Blotato** — hardcode a FB 25638 / IG 39285; multi-tenant requiere account mapping per client
5. **Telegram escalation** — hardcode a `TELEGRAM_CHAT_ID` del Jefe; multi-tenant requiere chat_id per client

Estos 5 handoffs son las **líneas de falla** al escalar. Detalle de cómo resolverlos → `docs/SCALABILITY.md`.

---

## 📋 CHECKLIST DE ORDEN POR TAREA

Cuando el Jefe dice "X":

**"Analiza esta propiedad"** → ALEX + Scout + Matemático + Fact-Checker → reporte
**"Skip trace este address"** → ALEX delega a el_polling (o Tracy manual si no hay Lead)
**"Genera contenido SM"** → ALEX → Social Media → Creativo+Director → Programador → resumen
**"Revisa el pipeline"** → ALEX lee Airtable → reporte
**"Corre el morning brief"** → `fer_morning_brief.php` directo
**"Arregla X bug"** → ALEX con skill `focused-fix` + `test-driven-development` + `verification-before-completion`
**"Investiga competencia"** → ALEX con skill `competitive-teardown` + web search
**"Arma proyección financiera"** → ALEX con skill `saas-metrics-coach` + `financial-analyst`

---

## 🔗 REFERENCIAS

- Arquitectura completa → `docs/ARCHITECTURE.md`
- Ficha técnica de cada agente → `docs/AGENT_REGISTRY.md`
- Costos por modelo → `docs/COST_OPTIMIZATION.md`
- Multi-tenant plan → `docs/SCALABILITY.md`
