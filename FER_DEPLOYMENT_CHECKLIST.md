# Fer v5 — Deployment Checklist

Sigue estos pasos en orden. No te saltes validaciones.

---

## 1. GitHub Secrets (hazlo antes de merge)

Ve a: `https://github.com/geocarp24/alex-real-estate-system/settings/secrets/actions`

Añade estos 5 secrets (los otros 2 —`AIRTABLE_TOKEN`, `TRACERFY_TOKEN`— ya existen):

| Name | Fuente |
|---|---|
| `ANTHROPIC_API_KEY` | `console.anthropic.com` → API Keys → Create |
| `MAKE_API_TOKEN` | `856a1ce2-6239-4859-8e8f-b799c29a32c7` (el que ya me diste) |
| `QUO_API_KEY` | Panel Quo/OpenPhone → API → Key actual |
| `TELEGRAM_BOT_TOKEN` | `8157575601:AAHmAo0OQroOUdXCnXZEjVh4hJkt0emx5_c` |
| `TELEGRAM_CHAT_ID` | `8402370952` |

**Verificación:** la lista debería mostrar 7 secrets totales.

---

## 2. Merge a master → auto-deploy

```
git checkout master
git merge claude/check-escalation-rules-Nk7kd
git push origin master
```

El workflow `deploy-hostinger.yml` se dispara automáticamente y:
1. Sube `hostinger/tools/*` (incluye `fer_agent.php` + todo `lib/`) a `public_html/Tools/` vía SCP.
2. Reescribe `Tools/config.php` con los 7 defines.
3. Tarda ~1-2 min. Verifica en la pestaña **Actions** de GitHub.

**Test rápido tras el deploy:**
```
curl https://pinnaclegroupwi.com/Tools/fer_agent.php
# Debe responder: {"ok":true,"service":"fer_agent","version":5}
```

---

## 3. Verifica campos Airtable (tabla Contacts)

Los campos que Fer lee/escribe, todos deben existir en la base `appfQbDA750Oihy9J`, tabla `tblacvw0Ss770x8l5`:

- [ ] `Phone1`, `Phone2`, `Phone3`, `Phone4` (existentes)
- [ ] `Stage` (existente — `fldj0XPPR1LUk4Ydp`)
- [ ] `Last contact date` (existente — `fldNHfz1RISdAnf4I`)
- [ ] `Negotiation notes` (existente — `fldsiQ32VTT2NMT4W`)
- [ ] `Lenguage` (existente — typo intencional)
- [ ] `Full Name` (si existe, Fer lo usa para saludar)
- [ ] `Property Address` o `Address` (uno de los dos)
- [ ] `City`

Si falta `Full Name` / `Property Address` / `City` — Fer degrada a fallback ("there", "your property", "Green Bay"). No rompe.

---

## 4. Verifica DataStore Make

- [ ] DataStore **Fer Conversations** (id `91636`) existe y tiene structure `338257`
- [ ] Estructura mínima de la structure (campos):
  - `phone` (text)
  - `contactId` (text)
  - `history` (long text)
  - `messageCount` (number)
  - `isOwner` (text)
  - `motivation` (text)
  - `timeline` (text)
  - `urgency` (text)
  - `language` (text)
  - `lastUpdated` (text)

Si la structure no tiene todos esos campos, los writes fallan parcialmente pero no rompen el flow (Fer seguirá respondiendo, solo perderá memoria entre turnos).

---

## 5. Cambiar webhook de Quo

Esto es el corte final. Hasta que no hagas este paso, Make sigue siendo el destino.

1. Entra a Quo/OpenPhone → **Settings** → **Integrations** → **Webhooks**
2. Encuentra el webhook apuntando a `https://hook.us2.make.com/ouck89d0cd7e7u2ia3vn94qas54rskdy`
3. Cambia la URL a: `https://pinnaclegroupwi.com/Tools/fer_agent.php`
4. Eventos suscritos: **solo** `message.received` (desactiva el resto)
5. Save

**Test en vivo:** desde otro teléfono manda un SMS a `(920) 777-9886`. Fer debe responder en <5 segundos.

---

## 6. Monitor primeras 24h

Log file en el servidor: `/home/u433637438/domains/pinnaclegroupwi.com/public_html/Tools/fer_agent.log`

```bash
ssh user@pinnaclegroupwi.com
tail -f ~/domains/pinnaclegroupwi.com/public_html/Tools/fer_agent.log
```

Cada línea es JSON estructurado: `ts`, `level`, `event`, `context`. Busca:

| Evento | Interpretación |
|---|---|
| `webhook_received` | Quo disparó el webhook correctamente |
| `dedup_duplicate` | Quo reintentó — Fer ignoró, ok |
| `claude_ok` | Claude respondió, incluye tokens + cache stats |
| `claude_http_error` | Revisar `ANTHROPIC_API_KEY` y saldo |
| `sms_sent` | SMS salió — revisar `to` y longitud |
| `sms_failed` | Revisar `QUO_API_KEY` y formato `from`/`to` |
| `telegram_sent` | Jorge recibió la alerta |
| `airtable_http_error` | Revisar permisos del PAT en la base |
| `datastore_http_error` | Revisar `MAKE_API_TOKEN` |

---

## 7. Qué NO se tocó (intencional)

- El Make scenario `4738270` sigue ahí, pero **sin webhook de entrada de Quo**. Puedes desactivarlo o dejarlo inactivo como backup histórico.
- Make sigue siendo útil para: First Contact SMS outbound, Seguimiento Engine, cualquier flujo que no responda en tiempo real.
- El `config.php` del servidor ahora tiene 7 defines — si el auto-deploy se corre sin todos los secrets, los defines quedarán en string vacío y los módulos logearán `*_missing_token` con degradación segura.

---

## 8. Rollback en caso de emergencia

```
# En Quo, revierte el webhook a la URL vieja de Make:
https://hook.us2.make.com/ouck89d0cd7e7u2ia3vn94qas54rskdy
```

El PHP del servidor no daña nada si no se llama — solo queda idle. No hay que borrar archivos.

---

## 9. Post-deploy — limpieza de seguridad pendiente (aparte)

Independiente de Fer:

1. **Rotar** el `AIRTABLE_TOKEN` actual — está en el repo (`hostinger/tools/config.php` hoy), potencialmente expuesto vía GitHub público.
2. Eliminar la línea `define('AIRTABLE_TOKEN', ...)` hardcoded del `config.php` del repo. El workflow ya lo reinyecta desde secrets; el archivo local ya no necesita el valor literal.
3. Considerar rotar el Telegram bot token — también estaba en el v4 en texto plano.

Abrimos tarea separada cuando termines con Fer en producción.
