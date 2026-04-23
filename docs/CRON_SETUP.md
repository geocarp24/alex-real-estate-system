# Fer SMS/Email Cron Setup — Hostinger cPanel

**Por qué este doc existe:** el workflow `.github/workflows/deploy-hostinger.yml` intenta instalar los cron jobs via SSH (`crontab -l … | crontab -`), pero **Hostinger shared hosting no permite que crons SSH persistan** — hay que configurarlos manualmente via el cPanel UI.

Sin estos crons configurados, los contactos quedan atascados en "To Be Contacted" y ningún SMS/Email se envía automáticamente.

## Los 4 crons que DEBEN existir

Abre **Hostinger hPanel → Advanced → Cron Jobs → Create a new cron job**.

### 1. First Contact — cada 15 minutos
```
Minute:          */15
Hour:            *
Day:             *
Month:           *
Weekday:         *
Command:         curl -s --max-time 120 https://pinnaclegroupwi.com/Tools/fer_first_contact.php > /dev/null 2>&1
```

Qué hace: procesa leads con `Stage="To Be Contacted"` o `Stage="Contacted"` step<4. Envía primer SMS (y los 3 follow-ups SMS a otros teléfonos). La ventana 9am-7pm CST Mon-Sat está dentro del PHP, así que correr 24/7 es seguro — fuera de ventana el script retorna `skipped: outside_hours` sin costo.

### 2. Seguimiento — daily 9:30 AM CST (14:30 UTC no-DST / 15:30 UTC DST)
```
Minute:          30
Hour:            14
Day:             *
Month:           *
Weekday:         *
Command:         curl -s --max-time 120 https://pinnaclegroupwi.com/Tools/fer_seguimiento.php > /dev/null 2>&1
```

Qué hace: drip de 24 touches × 12 meses. SMS todos los steps + Email pasos pares. Schedule: step 0→2 días, 1→4, 2-4→7, 5-14→14, 15-19→21, 20-23→30. Después de step 24 → Stage=Dead.

### 3. Stale Contacts — daily 8:00 AM CST (14:00 UTC)
```
Minute:          0
Hour:            14
Day:             *
Month:           *
Weekday:         *
Command:         curl -s --max-time 120 https://pinnaclegroupwi.com/Tools/fer_stale_cron.php > /dev/null 2>&1
```

### 4. Morning Brief — daily 8:30 AM CST (14:30 UTC)
```
Minute:          30
Hour:            14
Day:             *
Month:           *
Weekday:         *
Command:         curl -s --max-time 60 https://pinnaclegroupwi.com/Tools/fer_morning_brief.php > /dev/null 2>&1
```

> **Nota horario:** Hostinger usa UTC. CST = UTC-6 (no-DST) o CDT = UTC-5 (DST, mar 9 – nov 1). El formato `14 UTC` equivale a 8am CST en invierno y 9am CDT en verano. Si quieres que sean exactas 9:30 AM CT sin importar la estación, usa dos crons (uno invierno `15:30 UTC`, uno verano comentado) y cambia cada 6 meses — o acepta ±1h drift con DST.

## Cómo verificar que están corriendo

Después de guardar los crons, espera 15 min y ejecuta este diagnóstico local:

```bash
curl -s 'https://pinnaclegroupwi.com/Tools/fer_agent.log' | tail -30 | grep -E 'fc_sms_sent|seg_sms_sent'
```

Deberías ver eventos `fc_sms_sent` cada 15 minutos durante la ventana 9am-7pm CST Mon-Sat. Si no aparecen, reinvoca manualmente para confirmar que PHP funciona:

```bash
curl --max-time 120 https://pinnaclegroupwi.com/Tools/fer_first_contact.php
```

Si el curl manual responde JSON con `"sent": N` y los logs muestran `fc_sms_sent`, entonces **el código está OK y el cron simplemente no está configurado o no se dispara**. Regresa al cPanel y revisa que el cron esté **enabled (toggle verde)** y que el comando sea el correcto.

## Config en el código PHP

Archivos clave — parámetros que controlan el comportamiento:

| Archivo | Constante | Valor actual | Qué controla |
|---|---|---|---|
| `fer_first_contact.php` | `FC_MAX_PER_RUN` | 6 | Máx contactos procesados por invocación |
| `fer_first_contact.php` | `FC_SMS_DELAY_SECONDS` | 5 | Pacing entre SMS (evita timeout del cron) |
| `fer_first_contact.php` | `FC_HOURS_BETWEEN` | 24 | Horas entre steps 1-4 |
| `fer_seguimiento.php` | `SEG_MAX_PER_RUN` | 8 | Máx contactos procesados por invocación |
| `fer_seguimiento.php` | `SEG_SMS_DELAY_SECONDS` | 5 | Pacing entre SMS |

**Pacing reducido de 15s → 5s el 2026-04-23** para garantizar que cada invocación completa en <50s antes de que el cron HTTP invoker haga timeout a 60s. Ambos scripts también tienen `ignore_user_abort(true)` para sobrevivir cierre de conexión.

## Qué hacer si los crons siguen sin correr después de configurarlos

1. **Verificar plan Hostinger**: los planes Single / Premium Web tienen límite de crons. El plan Business y Cloud permiten sin límite. Si Jorge está en Single, puede ser que se hayan saturado.
2. **Verificar ruta de curl**: algunos planes Hostinger requieren `/usr/bin/curl` en lugar de `curl`. Prueba el full path si el curl simple falla.
3. **Verificar con `php -f` en vez de curl**: como workaround, Hostinger permite invocar PHP directamente: `/usr/bin/php /home/u433637438/domains/pinnaclegroupwi.com/public_html/Tools/fer_first_contact.php`
4. **Logs de cron ejecución**: hPanel → Cron Jobs → View cron job logs (si el plan lo soporta).
