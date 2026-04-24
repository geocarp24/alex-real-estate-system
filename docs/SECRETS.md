# SECRETS — Gestión de credenciales

> NUNCA commitear `.env.local`. NUNCA pegar keys reales en código, docs, o PR.
> Si una key se commitea por error → rotarla **inmediatamente**, notificar al Jefe, registrar en `memoria_ALex.md`.

---

## Mapa env var → servicio → dónde obtenerla

| Variable | Servicio | Dónde obtenerla |
|---|---|---|
| `REPLICATE_API_TOKEN` | Replicate (Fooocus, Wan 2.1, Kling, SDXL) | `replicate.com/account/api-tokens` |
| `GEMINI_API_KEY` | Google AI Studio (nano-banana, Veo, Gemini) — cuenta `admin@geocarpentry.com`, key name `pinnacle-alex-banana`, project `1027245729665` | `aistudio.google.com/apikey` |
| `ELEVENLABS_API_KEY` | ElevenLabs (voice — usado por remotion-ads) | `elevenlabs.io/app/settings/api-keys` |
| `HEYGEN_API_KEY` | HeyGen (avatar Jorge + voice clone) | `app.heygen.com/settings/api` |
| `META_APP_ID` / `META_APP_SECRET` | Meta for Developers — app "Pinnacle Social Publisher" | `developers.facebook.com` → My Apps → App Settings → Basic |
| `META_SYSTEM_USER_TOKEN` | Meta Business Center System User token (never-expires) | `business.facebook.com` → Settings → Users → System Users → Generate Token |
| `META_FB_PAGE_ID` | Pinnacle Holdings Page ID | Ya conocido: `965320503341457` |
| `META_IG_BUSINESS_ACCOUNT_ID` | Instagram Business Account ID (`@pinnacle.groupwi`) | Meta Graph API explorer |
| `AIRTABLE_TOKEN` | Airtable personal access token (Pinnacle base) | `airtable.com/create/tokens` |
| `AIRTABLE_BASE_ID` | Base de CRM Pinnacle | Ya conocido: `appfQbDA750Oihy9J` |
| `AIRTABLE_SM_BASE_ID` | Base de Social Media | Ya conocido: `appU9s3kGkVpdrJkw` |
| `TELEGRAM_BOT_TOKEN` | Bot `@Ferpinnaclebot` | BotFather en Telegram |
| `TELEGRAM_CHAT_ID` | Chat del Jefe para alerts | Enviar `/start` al bot, leer chat_id del update |
| `QUO_API_KEY` | OpenPhone/Quo (SMS de Fer) | Dashboard OpenPhone → API |
| `ANTHROPIC_API_KEY` | Claude API (Fer + Creativo runner) | `console.anthropic.com/settings/keys` |
| `DATAFORSEO_LOGIN` / `DATAFORSEO_PASSWORD` | DataForSEO (Posicionador — opcional) | `app.dataforseo.com` |

---

## Dónde viven (por entorno)

| Entorno | Mecanismo | Archivo/lugar |
|---|---|---|
| **Desarrollo local** (laptop Jorge) | `.env.local` en la raíz del repo | `.env.local` (gitignored) — cargado por script/agente al arrancar |
| **GitHub Actions workflows** | Repo secrets | `gh secret set <NAME> --body "..."` (por repo) |
| **VPS producción** (bot Telegram, crons Fer) | `.env` o `config.php` en el VPS | SSH + upload manual o via `deploy-hostinger.yml` |
| **Hostinger webhooks** (Fer, webform) | `config.php` (via `define()`) | Upload vía GitHub Actions `deploy-hostinger.yml` |

---

## Cómo agregar una key nueva

### 1. Dev local (nueva laptop o fresh clone)
```
cp .env.example .env.local
# Editar .env.local con los valores reales
```

### 2. GitHub Actions (para workflows)
```
gh secret set REPLICATE_API_TOKEN --repo geocarp24/alex-real-estate-system --body "r8_xxxxx..."
gh secret list --repo geocarp24/alex-real-estate-system   # verificar
```

### 3. Referenciar en un workflow
```yaml
env:
  REPLICATE_API_TOKEN: ${{ secrets.REPLICATE_API_TOKEN }}
```

### 4. Cargar en scripts Node/Bun/Python localmente
```javascript
// Node/Bun
import 'dotenv/config';
const token = process.env.REPLICATE_API_TOKEN;
```
```python
# Python
from dotenv import load_dotenv; load_dotenv(".env.local")
import os; token = os.environ["REPLICATE_API_TOKEN"]
```

---

## Reglas críticas

1. **Si una key aparece en un commit:** rotarla INMEDIATAMENTE en el proveedor, crear nueva, actualizar `.env.local` + GitHub Secrets + VPS, hacer `git filter-repo` o similar para sacarla del historial, notificar al Jefe.
2. **NO compartir keys por canales inseguros** (Slack público, email sin cifrar, screenshots).
3. **Rotar credenciales** cada 90 días mínimo, o al salir alguien del equipo.
4. **Scope mínimo:** tokens con los permisos exactos necesarios, nunca "all scopes".
5. **Registrar rotaciones** en `memoria_ALex.md` con fecha para auditoría.
6. **Nunca** imprimir keys en outputs de ALEX/sub-agentes. En logs, redactar a `***REDACTED***` o primeros 6 chars + `...`.
7. **`.gitignore` ya protege** `.env`, `.env.*`, `client_secret*.json`, `*.secret.json`, tokens de Google OAuth. Confirmado 2026-04-23.

---

*Última actualización: 2026-04-23 — ALEX. Aprobado por Jorge Cruz.*
