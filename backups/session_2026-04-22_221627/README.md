# Session Backup — 2026-04-22 22:16:27 UTC

Snapshot of every file touched (or critically referenced) during the
2026-04-22 maratón-session that closed Pinnacle Holdings' public stack:
webform, chatbot, contact redesign, email-reply fix, full docs.

## Contents

```
hostinger/
  agents/
    pinnacle_form/       # 4 static assets (css/i18n/screens/core)
    pinnacle_chat/       # widget css + js
    pinnacle_public.php  # backend (~600 lines): 8 actions
  mu-plugins/
    pinnacle-chat-loader.php
secretario/
  email_monitor.py       # 3-tier reply-recipient fix
workflows/
  deploy-hostinger.yml   # adds MU-plugins SCP step
  deploy-vps-bot.yml     # adds secretario/ + restart secretario-email
docs/                    # ARCHITECTURE, AGENT_REGISTRY, TASK_MATRIX,
                         # COST_OPTIMIZATION, SCALABILITY, COMMERCIALIZATION
memoria/
  memoria_ALex.md
  agents_memoria_alex.md
  telegram_memory.md
PROTOCOLO_EJECUCION.md   # 7-phase mandatory protocol
```

## Why this snapshot

The Pinnacle public stack (form + chat + contact + email reply) is now
in production and the documentation for it lives across half a dozen files.
This bundle is the single recoverable artifact for the day.

## Restore

```
cp -r hostinger/agents/pinnacle_form /home/user/alex-real-estate-system/hostinger/agents/
cp -r hostinger/agents/pinnacle_chat /home/user/alex-real-estate-system/hostinger/agents/
cp hostinger/agents/pinnacle_public.php /home/user/alex-real-estate-system/hostinger/agents/
cp hostinger/mu-plugins/pinnacle-chat-loader.php /home/user/alex-real-estate-system/hostinger/mu-plugins/
cp secretario/email_monitor.py /home/user/alex-real-estate-system/secretario/
cp workflows/*.yml /home/user/alex-real-estate-system/.github/workflows/
cp -r docs /home/user/alex-real-estate-system/
```

Then push to `origin/master` to redeploy via GitHub Actions.

## Companion backups (page-level, in `backups/wp_pinnacle/`)

- `2026-04-22_171341/` — full WP page+option dump pre-session
- `cta_fix_2026-04-22_213500/` — about/services CTA before+after
- `contact_five_ways_2026-04-22_214000/` — contact page before+after
