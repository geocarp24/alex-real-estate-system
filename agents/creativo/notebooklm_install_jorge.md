# NotebookLM — Instalación paso a paso en tu laptop

> Guía para Jorge. Lenguaje simple. Todo lo que tenés que hacer está acá.
> Tiempo estimado: 15-20 minutos la primera vez. Después no volvés a tocar nada.

---

## Antes de arrancar — requisitos

**1. Chrome instalado en tu laptop** con tu cuenta de Google logueada.

- Abrí Chrome.
- Si arriba a la derecha ves tu foto de perfil de Google → estás logueado ✅
- Si no, hacé click en el ícono de persona y logueate. Usá la cuenta que querés que use NotebookLM (puede ser tu personal o `deals@pinnaclegroupwi.com` — la que tengas más docs guardados).
- Probá abrir https://notebooklm.google.com/ — tiene que abrir sin pedirte login otra vez. Si te pide login, logueate primero ahí.

**2. Bun instalado (runtime de JavaScript — liviano, tipo Node pero más nuevo).**

- Abrí la Terminal (Mac: Cmd+Espacio, escribí "Terminal". Windows: Win+R, escribí "powershell").
- Pegá este comando y Enter:

  **En Mac / Linux:**
  ```
  curl -fsSL https://bun.sh/install | bash
  ```

  **En Windows (PowerShell):**
  ```
  powershell -c "irm bun.sh/install.ps1 | iex"
  ```

- Cuando termine, cerrá la Terminal y volvé a abrirla (para que tome el Bun).
- Verificá que quedó instalado escribiendo:
  ```
  bun --version
  ```
  Tiene que mostrar algo como `1.3.11` o superior. ✅

**3. Git instalado** (viene con macOS por default; Windows bajar de https://git-scm.com/).

- Verificá:
  ```
  git --version
  ```
  Si sale un número, listo.

---

## Los 3 comandos para instalar NotebookLM en tu laptop

Abrí la Terminal donde quieras que viva NotebookLM. Recomendación: una carpeta tipo `~/pinnacle-tools/`.

**Comando 1 — Clonar el skill de nuestro repo:**

```
mkdir -p ~/pinnacle-tools && cd ~/pinnacle-tools && git clone https://github.com/proyecto26/notebooklm-ai-plugin.git notebooklm && cd notebooklm
```

Esto crea la carpeta `~/pinnacle-tools/notebooklm/` con todo adentro.

**Comando 2 — Instalar las dependencias:**

```
bun install
```

Tarda 1-2 minutos. Va a salir una barra de progreso. Cuando termine y vuelvas a ver el prompt de la terminal, ya está.

**Comando 3 — Primer login / extracción de cookies:**

```
bun run auth
```

**Qué va a pasar:**
- El script abre una ventana de Chrome automáticamente (con un perfil especial para NotebookLM).
- Te va a pedir que te loguees en tu cuenta Google dentro de esa ventana.
- Una vez logueado, te redirige a NotebookLM.google.com.
- El script automáticamente agarra las cookies de tu sesión y las guarda localmente en `./cookies.json` (archivo local en tu laptop, no se comparte con nadie).
- Cuando termine, vas a ver un mensaje tipo `✅ Auth OK — cookies saved`.

**Si algo falla en este paso:** sacame screenshot del error y lo resolvemos juntos. Lo más común es que falte loguearse antes en Chrome regular.

---

## Primer test — ¿funciona?

Después de los 3 comandos, probá esto desde la misma carpeta `~/pinnacle-tools/notebooklm/`:

```
bun run chat "Hola, ¿qué notebooks tengo creados?"
```

Si responde con la lista de tus notebooks de NotebookLM (aunque esté vacía) → **funciona** ✅

Si dice "unauthorized" o similar → repetir el paso 3 (auth) porque las cookies no quedaron bien guardadas.

---

## Crear el primer notebook de Pinnacle

**Opción fácil — desde el navegador directo:**

1. Abrí https://notebooklm.google.com/ en tu Chrome.
2. Click en "Create new notebook".
3. Nombre: `Pinnacle WI — Distressed Homeowner Knowledge Base`.
4. Subí las 10 fuentes de la lista `agents/creativo/notebooklm_sources.md` (te paso el zip con el material interno Pinnacle separado).

**Opción script — desde la terminal:**

```
bun run create-notebook "Pinnacle WI — Distressed Homeowner Knowledge Base"
```

(Te devuelve un `notebook_id` — copialo y pegámelo para conectarlo al Creativo.)

---

## Qué me tenés que pasar cuando termines

1. **Confirmación de que los 3 comandos corrieron OK** — un "listo, anduvo".
2. **El `notebook_id`** del notebook de Pinnacle que creaste (sale en la URL de NotebookLM cuando lo abrís: `notebooklm.google.com/notebook/XXXXXXXX`).
3. **Si algo falló** — screenshot del error + en qué paso estabas.

Con eso yo configuro el handoff entre el Creativo y NotebookLM y ya te queda operativo.

---

## Preguntas frecuentes

**¿Se instala en VPS o en mi laptop?**
En TU laptop. NotebookLM requiere Chrome con tu sesión Google activa. En VPS no funciona.

**¿Qué pasa si apago la laptop?**
Si estás usando la **Opción A (manual)** — nada, generás contenido cuando prendés la laptop. Si después migramos a **Opción B (semi-auto con cron local)** — el cron solo corre cuando la laptop está prendida. Perfecto para horario laboral.

**¿Mis cookies de Google se mandan a algún servidor?**
No. Quedan en `~/pinnacle-tools/notebooklm/cookies.json` local. El skill no hace telemetría ni manda data afuera de tu máquina. Código fuente: MIT, revisable en GitHub.

**¿Cuesta algo?**
Nada. NotebookLM tiene plan free que nos alcanza (3 audios/videos por día, 50 chats por día, 50 fuentes por notebook). Si necesitamos más, el plan Plus es $19.99/mes — decidimos dentro de 30 días cuando veamos uso real.

**¿Y si Google cambia algo y se rompe?**
Los mantainers del skill (`proyecto26/notebooklm-ai-plugin`) sacan updates. Con un `cd ~/pinnacle-tools/notebooklm && git pull && bun install` quedás actualizado.

---

*Última actualización: 2026-04-23 — ALEX*
