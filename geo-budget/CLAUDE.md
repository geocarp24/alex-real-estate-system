# CLAUDE.md — Geo Carpentry Budget Builder

> Lee este archivo al inicio de cada sesión en este proyecto.
> Última actualización: 2026-03-30

---

## 🧠 IDENTIDAD DEL PROYECTO

**Nombre:** Geo Carpentry Budget Builder
**Empresa:** Geo Carpentry (negocio de construcción/carpintería)
**Dueño:** El Jefe
**URL en producción:** `https://pinnaclegroupwi.com/GeoBudget/`
**Repo GitHub:** `geocarp24/alex-real-estate-system` (subcarpeta `geo-budget/`)

---

## 🎯 QUÉ HACE ESTA APP

Aplicación web que:
1. El usuario sube un **plano arquitectónico en PDF**
2. La app lo envía a la **API de Claude (claude-opus-4-6)**
3. Claude analiza el plano y genera un **presupuesto de construcción detallado** con quantity takeoff
4. El presupuesto incluye: materiales, mano de obra, por división CSI (01–17)
5. Pricing basado en **Wisconsin 2026**

---

## 🏗️ ARQUITECTURA TÉCNICA

```
Browser (index.html)
    │ PDF → Base64
    │ POST con X-Budget-Token header
    ▼
geo-budget/api/analyze.php  ← PHP en Hostinger
    │
    │ Llama Claude API (claude-opus-4-6, max_tokens: 8192)
    │ Prompt: quantity takeoff, divisiones CSI 01-17
    ▼
Respuesta JSON → renderizado en el browser
```

**Stack:**
- Frontend: HTML/CSS/JS (vanilla, sin framework)
- Backend: PHP 8.x en Hostinger
- AI: Claude API (Anthropic) — `claude-opus-4-6`
- Auth: Token en header `X-Budget-Token`
- Deploy: GitHub Actions via SSH/SCP a Hostinger

---

## 📁 ESTRUCTURA DE ARCHIVOS

```
geo-budget/
├── index.html          ← Frontend completo
├── logo.png            ← Logo de Geo Carpentry
├── .htaccess           ← Configuración Apache
└── api/
    ├── analyze.php     ← Backend principal (proxy Claude API)
    └── config.php      ← API keys (generado por GitHub Actions, NO en repo)
```

---

## 🚀 DEPLOY

**Trigger:** Push a `master` con cambios en `geo-budget/**`
**GitHub Action:** `.github/workflows/deploy-geo-budget.yml`
**Destino:** `/home/u433637438/domains/pinnaclegroupwi.com/public_html/GeoBudget/`
**config.php** es generado automáticamente por el workflow con los secrets:
- `ANTHROPIC_KEY`
- `GEO_BUDGET_TOKEN` (token de auth del cliente)

---

## 🔑 SECRETOS (NO incluir en código)

```
ANTHROPIC_KEY      → Se inyecta via GitHub Actions secret
GEO_BUDGET_TOKEN   → Token de autenticación para la app
SSH credentials    → SSH_HOST, SSH_USERNAME, SSH_PASSWORD, SSH_PORT
```

---

## 📐 DIVISIONES CSI (01–17)

| # | División |
|---|----------|
| 01 | General Conditions and Permits |
| 02 | Site Work and Excavation |
| 03 | Concrete and Foundation |
| 04 | Framing and Lumber |
| 05 | Roofing |
| 06 | Exterior Windows, Doors and Siding |
| 07 | Insulation |
| 08 | Drywall |
| 09 | Interior Millwork and Trim |
| 10 | Cabinets and Countertops |
| 11 | Flooring |
| 12 | Plumbing |
| 13 | HVAC |
| 14 | Electrical |
| 15 | Painting and Finishes |
| 16 | Flatwork |
| 17 | Cleanup |

---

## 🛠️ SKILLS RECOMENDADOS PARA ESTE PROYECTO

Los siguientes skills están disponibles en `.claude/skills/` del repo:

| Skill | Cuándo usarlo |
|-------|---------------|
| `senior-backend` | Mejorar `analyze.php`, optimizar backend PHP |
| `senior-frontend` | Mejorar UI/UX del `index.html` |
| `claude-api` | Modificar el prompt de Claude, ajustar parámetros |
| `spec-driven-workflow` | Definir nuevas features antes de implementar |
| `code-reviewer` | Revisar calidad del código antes de merge |
| `api-design-reviewer` | Revisar endpoints y seguridad de la API |
| `performance-profiler` | Optimizar tiempos de respuesta |
| `a11y-audit` | Mejorar accesibilidad del frontend |
| `security-pen-testing` | Auditar seguridad del token de auth |
| `docker-development` | Si se migra a contenedores en el futuro |
| `ui-design-system` | Crear design system consistente |
| `playwright-pro` | Testing automatizado del flujo PDF → presupuesto |

---

## 📋 TAREAS PENDIENTES / ROADMAP

*(Actualizar aquí conforme se trabajen features)*

- [ ] Mejorar UI/UX del presupuesto generado (tabla más visual)
- [ ] Agregar opción de exportar a PDF
- [ ] Agregar login de usuarios
- [ ] Guardar historial de presupuestos
- [ ] Multi-idioma (español/inglés)

---

## ⚠️ REGLAS DE DESARROLLO

1. **Nunca** hardcodear `ANTHROPIC_KEY` ni `GEO_BUDGET_TOKEN` en el código
2. `config.php` siempre se genera via GitHub Actions — nunca subir al repo
3. El modelo de Claude es `claude-opus-4-6` — no downgrade sin aprobación del Jefe
4. Los precios del presupuesto son para **Wisconsin 2026** — ajustar si cambia el mercado
5. Siempre probar localmente antes de push a master (el push dispara deploy automático)

---

*Proyecto activo — Geo Carpentry / Pinnacle Holdings Group LLC*
