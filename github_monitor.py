#!/usr/bin/env python3
"""
ALEX GitHub Monitor — Sistema de monitoreo automático 24/7
===========================================================
Corre como servicio systemd (github-monitor.service).
Monitorea task_queue.json en geocarp24/pinnacle-agent-memory cada 30s.

Flujo completo:
  Jorge (Telegram) → ALEX Bot → task_queue.json en GitHub
  → este monitor detecta tarea pendiente
  → ejecuta con Claude Code CLI
  → actualiza estado en GitHub
  → escribe resultado en memoria_ALex.md
  → notifica a Jorge en Telegram

Sin que Jorge abra Claude Code nunca más.
"""

import json
import logging
import os
import subprocess
import sys
import time
import uuid
from datetime import datetime
from pathlib import Path

import requests
from dotenv import load_dotenv

# ─────────────────────────────────────────────
# CONFIG
# ─────────────────────────────────────────────
load_dotenv(Path(__file__).parent / ".env")

PROJECT_DIR    = Path(__file__).parent
LOG_FILE       = PROJECT_DIR / "agents" / "github_monitor.log"

BRIDGE_URL     = os.getenv("BRIDGE_URL", "https://agents.pinnaclegroupwi.com")
ALEX_SECRET    = os.getenv("ALEX_SECRET", "pinnacle2024ALEXsecret99")
TELEGRAM_TOKEN = os.getenv("TELEGRAM_TOKEN", "")
ANTHROPIC_KEY  = os.getenv("ANTHROPIC_KEY", "")
OWNER_CHAT_ID  = "8402370952"

GITHUB_REPO    = "pinnacle-agent-memory"
TASK_QUEUE_FILE = "task_queue.json"
COLA_MD_FILE   = "cola_mensajes.md"

POLL_INTERVAL  = 30    # segundos entre checks
CLAUDE_TIMEOUT = 300   # 5 minutos max por tarea

logging.basicConfig(
    format="%(asctime)s [MONITOR] %(levelname)s — %(message)s",
    level=logging.INFO,
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler(LOG_FILE, encoding="utf-8")
    ]
)
logger = logging.getLogger("github_monitor")


# ─────────────────────────────────────────────
# BRIDGE — leer/escribir GitHub
# ─────────────────────────────────────────────

def bridge_read(file_path: str) -> str | None:
    """Lee un archivo del repo pinnacle-agent-memory via bridge."""
    try:
        r = requests.get(
            f"{BRIDGE_URL}/github_bridge.php",
            params={"repo": GITHUB_REPO, "file": file_path},
            headers={"X-Alex-Secret": ALEX_SECRET},
            timeout=15
        )
        if r.status_code == 200:
            return r.text
        logger.warning(f"Bridge read error {r.status_code}: {file_path}")
        return None
    except Exception as e:
        logger.error(f"Bridge read failed: {e}")
        return None


def bridge_write(file_path: str, content: str, commit_msg: str) -> bool:
    """Escribe un archivo en el repo pinnacle-agent-memory via bridge."""
    try:
        r = requests.post(
            f"{BRIDGE_URL}/github_write.php",
            json={
                "repo":    GITHUB_REPO,
                "file":    file_path,
                "content": content,
                "message": commit_msg
            },
            headers={
                "X-Alex-Secret": ALEX_SECRET,
                "Content-Type":  "application/json"
            },
            timeout=20
        )
        if r.status_code in (200, 201) and r.json().get("success"):
            return True
        logger.warning(f"Bridge write error {r.status_code}: {r.text[:200]}")
        return False
    except Exception as e:
        logger.error(f"Bridge write failed: {e}")
        return False


# ─────────────────────────────────────────────
# TASK QUEUE — leer/actualizar
# ─────────────────────────────────────────────

def load_task_queue() -> list:
    """Lee task_queue.json desde GitHub."""
    raw = bridge_read(TASK_QUEUE_FILE)
    if not raw:
        return []
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        logger.error("task_queue.json inválido — no es JSON")
        return []


def save_task_queue(tasks: list) -> bool:
    """Guarda task_queue.json en GitHub."""
    content = json.dumps(tasks, ensure_ascii=False, indent=2)
    ts = datetime.now().strftime("%Y-%m-%d %H:%M")
    return bridge_write(TASK_QUEUE_FILE, content, f"monitor: update task queue — {ts}")


def update_task_status(tasks: list, task_id: str, status: str,
                       result: str = None, error: str = None) -> list:
    """Actualiza el estado de una tarea en la lista."""
    ts = datetime.now().isoformat()
    for task in tasks:
        if task.get("task_id") == task_id:
            task["status"]     = status
            task["updated_at"] = ts
            if status == "en_proceso":
                task["started_at"] = ts
            if status in ("completado", "error"):
                task["completed_at"] = ts
            if result is not None:
                task["result"] = result
            if error is not None:
                task["error"] = error
    return tasks


# ─────────────────────────────────────────────
# TELEGRAM — notificación directa
# ─────────────────────────────────────────────

def send_telegram(chat_id: str, text: str):
    if not TELEGRAM_TOKEN:
        return
    url = f"https://api.telegram.org/bot{TELEGRAM_TOKEN}/sendMessage"
    for chunk in [text[i:i+4000] for i in range(0, len(text), 4000)]:
        try:
            requests.post(url, json={"chat_id": chat_id, "text": chunk}, timeout=15)
        except Exception as e:
            logger.error(f"Telegram error: {e}")


# ─────────────────────────────────────────────
# MEMORIA — escribir en memoria_ALex.md
# ─────────────────────────────────────────────

def write_to_memoria(task_description: str, result: str):
    """Escribe el resultado de la tarea en memoria_ALex.md local y en GitHub."""
    ts = datetime.now().strftime("%Y-%m-%d %H:%M")
    entry = (
        f"\n\n---\n"
        f"### {ts} — Tarea ejecutada por GitHub Monitor\n"
        f"**Tarea:** {task_description[:200]}\n"
        f"**Resultado:** {result[:500]}\n"
    )
    # Escribir local
    memoria_local = PROJECT_DIR / "memoria_ALex.md"
    if memoria_local.exists():
        existing = memoria_local.read_text(encoding="utf-8")
        memoria_local.write_text(existing + entry, encoding="utf-8")

    # Escribir en GitHub (repo principal)
    try:
        requests.post(
            f"{BRIDGE_URL}/github_write.php",
            json={
                "repo":    "alex-real-estate-system",
                "file":    "memoria_ALex.md",
                "content": (memoria_local.read_text(encoding="utf-8") if memoria_local.exists() else entry),
                "message": f"monitor: task result — {ts}"
            },
            headers={"X-Alex-Secret": ALEX_SECRET, "Content-Type": "application/json"},
            timeout=20
        )
    except Exception as e:
        logger.error(f"Error writing memoria to GitHub: {e}")


# ─────────────────────────────────────────────
# CLAUDE CODE CLI
# ─────────────────────────────────────────────

def run_claude_cli(prompt: str) -> tuple[bool, str]:
    """Ejecuta claude --print con la tarea y retorna (éxito, resultado)."""
    env = {**os.environ, "ANTHROPIC_API_KEY": ANTHROPIC_KEY}
    try:
        result = subprocess.run(
            ["claude", "--print", prompt],
            capture_output=True, text=True,
            timeout=CLAUDE_TIMEOUT,
            cwd=str(PROJECT_DIR),
            env=env
        )
        if result.returncode == 0:
            return True, result.stdout.strip() or "✅ Tarea completada."
        return False, f"Error {result.returncode}: {result.stderr.strip()[:400]}"
    except subprocess.TimeoutExpired:
        return False, f"⏱ Timeout después de {CLAUDE_TIMEOUT//60} minutos."
    except FileNotFoundError:
        return False, "❌ Claude Code CLI no encontrado."
    except Exception as e:
        return False, f"❌ {str(e)}"


# ─────────────────────────────────────────────
# PROCESAR TAREA
# ─────────────────────────────────────────────

def process_task(task: dict, all_tasks: list):
    """
    Ejecuta una tarea pendiente:
    1. Marca como en_proceso en GitHub
    2. Ejecuta Claude Code CLI
    3. Marca como completado/error en GitHub
    4. Escribe resultado en memoria_ALex.md
    5. Notifica a Jorge en Telegram
    """
    task_id     = task["task_id"]
    description = task.get("task", task.get("prompt", "Sin descripción"))
    chat_id     = str(task.get("chat_id", OWNER_CHAT_ID))
    source      = task.get("source", "github")

    logger.info(f"Iniciando tarea {task_id[:8]}: {description[:60]}")

    # 1. Marcar en_proceso en GitHub
    all_tasks = update_task_status(all_tasks, task_id, "en_proceso")
    if not save_task_queue(all_tasks):
        logger.warning(f"No se pudo actualizar estado a en_proceso para {task_id[:8]}")

    # Notificar inicio
    send_telegram(chat_id, f"⚙️ *GitHub Monitor* — Ejecutando tarea:\n_{description[:200]}_")

    # 2. Ejecutar Claude Code
    success, result = run_claude_cli(description)

    # 3. Marcar completado/error en GitHub
    status = "completado" if success else "error"
    all_tasks = update_task_status(
        all_tasks, task_id, status,
        result=result if success else None,
        error=result if not success else None
    )
    if not save_task_queue(all_tasks):
        logger.warning(f"No se pudo actualizar estado a {status} para {task_id[:8]}")

    # 4. Escribir en memoria_ALex.md
    if success:
        write_to_memoria(description, result)

    # 5. Notificar resultado a Jorge en Telegram
    icon = "✅" if success else "❌"
    send_telegram(
        chat_id,
        f"{icon} *Tarea completada por Claude Code*\n\n"
        f"📋 *Tarea:* {description[:150]}\n\n"
        f"📝 *Resultado:*\n{result[:2000]}"
    )

    logger.info(f"Tarea {task_id[:8]} — {'OK' if success else 'ERROR'}")
    return all_tasks


# ─────────────────────────────────────────────
# SCANNER — memoria_ALex.md → task_queue.json
# ─────────────────────────────────────────────

MEMORIA_LOCAL = PROJECT_DIR / "memoria_ALex.md"
PENDING_MARKER   = "**Status:** ⏳ PENDIENTE EJECUCIÓN"
INPROGRESS_MARKER = "**Status:** ⚙️ EN PROCESO"


def scan_memoria_for_tasks():
    """
    Lee memoria_ALex.md buscando bloques con status PENDIENTE EJECUCIÓN.
    Los convierte a entradas en task_queue.json y marca el bloque como EN PROCESO.
    """
    if not MEMORIA_LOCAL.exists():
        return

    content = MEMORIA_LOCAL.read_text(encoding="utf-8")
    if PENDING_MARKER not in content:
        return

    # Buscar bloques de tarea pendiente
    lines  = content.split("\n")
    tasks_found = []
    i = 0

    while i < len(lines):
        if PENDING_MARKER in lines[i]:
            # Retroceder hasta el encabezado ### del bloque
            block_start = i
            for j in range(i, max(0, i - 20), -1):
                if lines[j].startswith("### ") and "TAREA" in lines[j].upper():
                    block_start = j
                    break

            # Extraer instrucciones — líneas entre el header y el próximo ---
            task_lines = []
            for k in range(block_start, min(len(lines), i + 50)):
                if k > block_start and lines[k].strip() == "---":
                    break
                task_lines.append(lines[k])

            task_text = "\n".join(task_lines).strip()

            # Extraer chat_id si está presente (buscar "chat_id:" en el bloque)
            chat_id = OWNER_CHAT_ID
            for tl in task_lines:
                if "chat_id:" in tl.lower():
                    parts = tl.split(":")
                    if len(parts) > 1:
                        chat_id = parts[-1].strip().strip("`").strip()

            task_id = str(uuid.uuid4())
            tasks_found.append({
                "task_id":    task_id,
                "task":       task_text[:2000],
                "chat_id":    chat_id,
                "source":     "memoria_alex",
                "status":     "pendiente",
                "created_at": datetime.now().isoformat(),
                "line_index": i  # para marcar después
            })

            logger.info(f"Tarea encontrada en memoria_ALex.md: {task_id[:8]}")
        i += 1

    if not tasks_found:
        return

    # Marcar tareas como EN PROCESO en memoria_ALex.md
    updated_content = content.replace(PENDING_MARKER, INPROGRESS_MARKER)
    MEMORIA_LOCAL.write_text(updated_content, encoding="utf-8")

    # Agregar a task_queue.json
    existing_tasks = load_task_queue()
    existing_ids = {t.get("task_id") for t in existing_tasks}

    for task in tasks_found:
        task.pop("line_index", None)
        if task["task_id"] not in existing_ids:
            existing_tasks.append(task)

    save_task_queue(existing_tasks)
    logger.info(f"{len(tasks_found)} tarea(s) migradas de memoria_ALex.md → task_queue.json")


# ─────────────────────────────────────────────
# LOOP PRINCIPAL
# ─────────────────────────────────────────────

def main():
    logger.info("=" * 60)
    logger.info("  ALEX GitHub Monitor — Iniciando")
    logger.info(f"  Repo:  geocarp24/{GITHUB_REPO}")
    logger.info(f"  File:  {TASK_QUEUE_FILE}")
    logger.info(f"  Poll:  cada {POLL_INTERVAL}s")
    logger.info(f"  Timeout Claude: {CLAUDE_TIMEOUT}s")
    logger.info("=" * 60)

    # Asegurar que task_queue.json existe en GitHub
    existing = bridge_read(TASK_QUEUE_FILE)
    if existing is None:
        logger.info("Creando task_queue.json vacío en GitHub...")
        bridge_write(TASK_QUEUE_FILE, "[]", "init: task_queue.json — GitHub Monitor")
        time.sleep(5)  # esperar a que GitHub propague el archivo nuevo

    consecutive_errors = 0

    while True:
        try:
            # Escanear memoria_ALex.md y migrar tareas pendientes a task_queue
            scan_memoria_for_tasks()

            tasks = load_task_queue()
            pending = [t for t in tasks if t.get("status") == "pendiente"]

            if pending:
                logger.info(f"{len(pending)} tarea(s) pendiente(s) detectada(s)")
                for task in pending:
                    tasks = process_task(task, tasks)
            else:
                logger.debug("Sin tareas pendientes.")

            consecutive_errors = 0

        except Exception as e:
            consecutive_errors += 1
            logger.error(f"Error en loop ({consecutive_errors} consecutivo): {e}", exc_info=True)

            if consecutive_errors >= 5:
                send_telegram(
                    OWNER_CHAT_ID,
                    f"⚠️ *GitHub Monitor* — {consecutive_errors} errores consecutivos.\n`{str(e)[:200]}`"
                )

        time.sleep(POLL_INTERVAL)


if __name__ == "__main__":
    main()
