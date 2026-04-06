"""
El Secretario — Monitor de Email
Pinnacle Holdings Group | deals@pinnaclegroupwi.com

Conecta via IMAP, lee emails no leídos, clasifica con Claude,
notifica a Jorge por Telegram, y sube leads a Airtable.

Corre como servicio systemd cada 5 minutos.
"""

import imaplib
import smtplib
import email
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from email.header import decode_header
import json
import os
import sys
import time
import sqlite3
import logging
from datetime import datetime
from pathlib import Path

# Ajustar path para importar desde el proyecto
PROJECT_DIR = Path(__file__).parent.parent
sys.path.insert(0, str(PROJECT_DIR))

try:
    from dotenv import load_dotenv
    load_dotenv(PROJECT_DIR / ".env")
except ImportError:
    pass

import anthropic
import requests

# ─────────────────────────────────────────────
# CONFIGURACIÓN
# ─────────────────────────────────────────────
EMAIL_ADDRESS  = os.getenv("SECRETARIO_EMAIL",    "deals@pinnaclegroupwi.com")
EMAIL_PASSWORD = os.getenv("SECRETARIO_PASSWORD", "4523Jics!$")
IMAP_HOST      = os.getenv("IMAP_HOST",           "imap.hostinger.com")
IMAP_PORT      = int(os.getenv("IMAP_PORT",       "993"))
SMTP_HOST      = os.getenv("SMTP_HOST",           "smtp.hostinger.com")
SMTP_PORT      = int(os.getenv("SMTP_PORT",       "465"))

TELEGRAM_TOKEN   = os.getenv("TELEGRAM_TOKEN", "8157575601:AAHmAo0OQroOUdXCnXZEjVh4hJkt0emx5_c")
OWNER_CHAT_ID    = os.getenv("TELEGRAM_CHAT_ID", "8402370952")
ANTHROPIC_KEY    = os.getenv("ANTHROPIC_KEY")
CLAUDE_MODEL     = "claude-sonnet-4-6"

AIRTABLE_TOKEN   = os.getenv("AIRTABLE_TOKEN", "patQXGBEGdmbhGRfi.81e226fee4638f95bba27a57003465dd930d9e977d8b4dc7ac372c1b60dd087b")
AIRTABLE_BASE_ID = "appfQbDA750Oihy9J"
AIRTABLE_LEADS   = "tblxZz2EWIglOLnEd"
AIRTABLE_CONTACTS= "tblacvw0Ss770x8l5"

DB_PATH = PROJECT_DIR / "secretario" / "emails.db"

# Logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [SECRETARIO] %(levelname)s — %(message)s",
    handlers=[
        logging.FileHandler(PROJECT_DIR / "agents" / "secretario.log"),
        logging.StreamHandler()
    ]
)
log = logging.getLogger(__name__)

# ─────────────────────────────────────────────
# BASE DE DATOS LOCAL (SQLite)
# ─────────────────────────────────────────────
def init_db():
    """Inicializa la base de datos local para tracking de emails procesados."""
    conn = sqlite3.connect(str(DB_PATH))
    c = conn.cursor()
    c.execute("""
        CREATE TABLE IF NOT EXISTS emails_procesados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id TEXT UNIQUE,
            fecha TEXT,
            remitente TEXT,
            asunto TEXT,
            categoria TEXT,
            resumen TEXT,
            respuesta_sugerida TEXT,
            telegram_notificado INTEGER DEFAULT 0,
            airtable_record_id TEXT,
            respondido INTEGER DEFAULT 0,
            respuesta_enviada TEXT
        )
    """)
    conn.commit()
    conn.close()

def email_ya_procesado(message_id: str) -> bool:
    conn = sqlite3.connect(str(DB_PATH))
    c = conn.cursor()
    c.execute("SELECT id FROM emails_procesados WHERE message_id = ?", (message_id,))
    result = c.fetchone()
    conn.close()
    return result is not None

def guardar_email(data: dict) -> int:
    conn = sqlite3.connect(str(DB_PATH))
    c = conn.cursor()
    c.execute("""
        INSERT OR IGNORE INTO emails_procesados
        (message_id, fecha, remitente, asunto, categoria, resumen, respuesta_sugerida)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    """, (
        data["message_id"], data["fecha"], data["remitente"],
        data["asunto"], data["categoria"], data["resumen"],
        data.get("respuesta_sugerida", "")
    ))
    email_id = c.lastrowid
    conn.commit()
    conn.close()
    return email_id

def marcar_notificado(message_id: str):
    conn = sqlite3.connect(str(DB_PATH))
    c = conn.cursor()
    c.execute("UPDATE emails_procesados SET telegram_notificado=1 WHERE message_id=?", (message_id,))
    conn.commit()
    conn.close()

def guardar_airtable_id(message_id: str, record_id: str):
    conn = sqlite3.connect(str(DB_PATH))
    c = conn.cursor()
    c.execute("UPDATE emails_procesados SET airtable_record_id=? WHERE message_id=?", (record_id, message_id))
    conn.commit()
    conn.close()

def get_email_by_db_id(db_id: int) -> dict | None:
    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    c = conn.cursor()
    c.execute("SELECT * FROM emails_procesados WHERE id=?", (db_id,))
    row = c.fetchone()
    conn.close()
    return dict(row) if row else None

def marcar_respondido(db_id: int, texto_enviado: str):
    conn = sqlite3.connect(str(DB_PATH))
    c = conn.cursor()
    c.execute("UPDATE emails_procesados SET respondido=1, respuesta_enviada=? WHERE id=?",
              (texto_enviado, db_id))
    conn.commit()
    conn.close()

def get_ultimos_emails(n: int = 5) -> list:
    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    c = conn.cursor()
    c.execute("""
        SELECT * FROM emails_procesados
        WHERE categoria != 'SPAM'
        ORDER BY fecha DESC LIMIT ?
    """, (n,))
    rows = c.fetchall()
    conn.close()
    return [dict(r) for r in rows]

# ─────────────────────────────────────────────
# IMAP — LEER EMAILS
# ─────────────────────────────────────────────
def decode_str(s):
    """Decodifica headers de email (maneja encoded-words)."""
    if s is None:
        return ""
    decoded_parts = decode_header(s)
    result = []
    for part, charset in decoded_parts:
        if isinstance(part, bytes):
            try:
                result.append(part.decode(charset or "utf-8", errors="replace"))
            except Exception:
                result.append(part.decode("utf-8", errors="replace"))
        else:
            result.append(str(part))
    return " ".join(result)

def get_email_body(msg) -> str:
    """Extrae el cuerpo de texto del email."""
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ct = part.get_content_type()
            cd = str(part.get("Content-Disposition", ""))
            if ct == "text/plain" and "attachment" not in cd:
                try:
                    charset = part.get_content_charset() or "utf-8"
                    body += part.get_payload(decode=True).decode(charset, errors="replace")
                except Exception:
                    pass
    else:
        try:
            charset = msg.get_content_charset() or "utf-8"
            body = msg.get_payload(decode=True).decode(charset, errors="replace")
        except Exception:
            body = str(msg.get_payload())
    return body[:3000]  # Limitar a 3000 chars para Claude

def leer_emails_no_leidos() -> list:
    """Conecta via IMAP y retorna lista de emails no leídos."""
    emails = []
    try:
        mail = imaplib.IMAP4_SSL(IMAP_HOST, IMAP_PORT)
        mail.login(EMAIL_ADDRESS, EMAIL_PASSWORD)
        mail.select("INBOX")

        # Buscar emails no leídos
        _, data = mail.search(None, "UNSEEN")
        ids = data[0].split()

        log.info(f"Emails no leídos encontrados: {len(ids)}")

        for num in ids[-20:]:  # Máximo 20 por ciclo
            _, data = mail.fetch(num, "(RFC822)")
            raw = data[0][1]
            msg = email.message_from_bytes(raw)

            message_id = msg.get("Message-ID", f"no-id-{num.decode()}")
            remitente   = decode_str(msg.get("From", ""))
            asunto      = decode_str(msg.get("Subject", "(sin asunto)"))
            fecha       = msg.get("Date", "")
            cuerpo      = get_email_body(msg)

            emails.append({
                "message_id": message_id,
                "remitente":  remitente,
                "asunto":     asunto,
                "fecha":      fecha,
                "cuerpo":     cuerpo,
                "imap_num":   num.decode()
            })

        mail.logout()
    except Exception as e:
        log.error(f"Error IMAP: {e}")

    return emails

# ─────────────────────────────────────────────
# CLAUDE — CLASIFICAR Y REDACTAR
# ─────────────────────────────────────────────
def clasificar_email(remitente: str, asunto: str, cuerpo: str) -> dict:
    """Usa Claude para clasificar el email y redactar respuesta sugerida."""
    if not ANTHROPIC_KEY:
        log.error("ANTHROPIC_KEY no configurada")
        return {"categoria": "RUTINARIO", "resumen": asunto, "respuesta_sugerida": ""}

    client = anthropic.Anthropic(api_key=ANTHROPIC_KEY)

    prompt = f"""Eres El Secretario de Pinnacle Holdings Group, empresa de inversión inmobiliaria en Wisconsin.

Analiza este email recibido en deals@pinnaclegroupwi.com y responde en JSON.

EMAIL:
De: {remitente}
Asunto: {asunto}
Cuerpo: {cuerpo}

Clasifica en UNA categoría:
- LEAD: dueño o representante queriendo vender propiedad, wholesaler con deal, agente con off-market listing
- URGENTE: foreclosure inminente (<30 días), fecha límite hoy/mañana, respuesta requerida urgente
- RUTINARIO: seguimiento, preguntas generales, confirmaciones, información de mercado
- SPAM: marketing no solicitado, newsletters, phishing, scam

Responde SOLO en JSON válido:
{{
  "categoria": "LEAD|URGENTE|RUTINARIO|SPAM",
  "resumen": "2-3 oraciones resumiendo el email en español",
  "nombre_remitente": "nombre extraído del email o empresa",
  "telefono": "teléfono si aparece en el email, o null",
  "direccion_propiedad": "dirección de propiedad si se menciona, o null",
  "respuesta_sugerida": "texto de respuesta en inglés, profesional, máximo 150 palabras. Vacío si es SPAM.",
  "prioridad": "alta|media|baja"
}}"""

    try:
        response = client.messages.create(
            model=CLAUDE_MODEL,
            max_tokens=800,
            messages=[{"role": "user", "content": prompt}]
        )
        text = response.content[0].text.strip()
        # Limpiar posible markdown
        if text.startswith("```"):
            text = text.split("```")[1]
            if text.startswith("json"):
                text = text[4:]
        return json.loads(text)
    except Exception as e:
        log.error(f"Error Claude clasificación: {e}")
        return {
            "categoria": "RUTINARIO",
            "resumen": f"Email de {remitente}: {asunto}",
            "nombre_remitente": "",
            "telefono": None,
            "direccion_propiedad": None,
            "respuesta_sugerida": "",
            "prioridad": "baja"
        }

# ─────────────────────────────────────────────
# AIRTABLE — CREAR LEAD
# ─────────────────────────────────────────────
def crear_lead_airtable(email_data: dict, clasificacion: dict) -> str | None:
    """Crea registro en Airtable tabla Leads. Retorna record_id o None."""
    headers = {
        "Authorization": f"Bearer {AIRTABLE_TOKEN}",
        "Content-Type": "application/json"
    }

    nombre = clasificacion.get("nombre_remitente", "")
    partes = nombre.split(" ", 1) if nombre else ["", ""]
    first_name = partes[0]
    last_name   = partes[1] if len(partes) > 1 else ""

    # Extraer email del campo remitente (formato: "Nombre <email@domain.com>")
    remitente_raw = email_data.get("remitente", "")
    email_addr = ""
    if "<" in remitente_raw and ">" in remitente_raw:
        email_addr = remitente_raw.split("<")[1].split(">")[0].strip()
    elif "@" in remitente_raw:
        email_addr = remitente_raw.strip()

    fields = {
        "First Name":  first_name,
        "Last Name":   last_name,
        "Email":       email_addr,
        "Stage":       "New Lead",
        "Source":      "Email - deals@pinnaclegroupwi.com",
        "Notes":       f"[El Secretario] {clasificacion.get('resumen', '')}\n\nAsunto original: {email_data.get('asunto', '')}",
    }

    if clasificacion.get("telefono"):
        fields["Phone"] = clasificacion["telefono"]
    if clasificacion.get("direccion_propiedad"):
        fields["Address"] = clasificacion["direccion_propiedad"]

    # Remover campos vacíos
    fields = {k: v for k, v in fields.items() if v}

    try:
        r = requests.post(
            f"https://api.airtable.com/v0/{AIRTABLE_BASE_ID}/{AIRTABLE_LEADS}",
            headers=headers,
            json={"fields": fields},
            timeout=15
        )
        if r.status_code == 200:
            record_id = r.json().get("id")
            log.info(f"Lead creado en Airtable: {record_id}")
            return record_id
        else:
            log.error(f"Airtable error {r.status_code}: {r.text[:200]}")
            return None
    except Exception as e:
        log.error(f"Airtable request error: {e}")
        return None

# ─────────────────────────────────────────────
# TELEGRAM — NOTIFICAR
# ─────────────────────────────────────────────
def enviar_telegram(mensaje: str, parse_mode: str = "Markdown") -> bool:
    """Envía mensaje a Jorge via Telegram."""
    try:
        r = requests.post(
            f"https://api.telegram.org/bot{TELEGRAM_TOKEN}/sendMessage",
            json={
                "chat_id": OWNER_CHAT_ID,
                "text": mensaje,
                "parse_mode": parse_mode
            },
            timeout=15
        )
        return r.status_code == 200
    except Exception as e:
        log.error(f"Error Telegram: {e}")
        return False

def formatear_notificacion(email_data: dict, clasificacion: dict, db_id: int, airtable_ok: bool) -> str:
    """Formatea mensaje de Telegram para Jorge."""
    cat = clasificacion.get("categoria", "RUTINARIO")
    emojis = {"LEAD": "🏠", "URGENTE": "🚨", "RUTINARIO": "📋", "SPAM": "🗑️"}
    emoji = emojis.get(cat, "📧")

    resumen = clasificacion.get("resumen", "")
    respuesta = clasificacion.get("respuesta_sugerida", "")
    airtable_status = "✅ Lead creado en Airtable" if airtable_ok else ""

    msg = f"""{emoji} *{cat}* — EMAIL NUEVO

*De:* {email_data.get('remitente', '')[:80]}
*Asunto:* {email_data.get('asunto', '')[:100]}

*Resumen:*
{resumen}
"""

    if airtable_status:
        msg += f"\n{airtable_status}"

    if respuesta and cat != "SPAM":
        msg += f"""

💬 *Respuesta sugerida:*
_{respuesta[:400]}_

Para responder: `/responder {db_id}`
Para responder con otro texto: `/responder {db_id} tu mensaje aquí`"""

    return msg

# ─────────────────────────────────────────────
# SMTP — ENVIAR RESPUESTA
# ─────────────────────────────────────────────
def enviar_respuesta_email(destinatario: str, asunto: str, cuerpo: str) -> bool:
    """Envía email de respuesta via SMTP."""
    try:
        msg = MIMEMultipart()
        msg["From"]    = EMAIL_ADDRESS
        msg["To"]      = destinatario
        msg["Subject"] = f"Re: {asunto}" if not asunto.startswith("Re:") else asunto

        firma = """

Best regards,

Jorge
Pinnacle Holdings Group
deals@pinnaclegroupwi.com
Wisconsin Real Estate Investors"""

        msg.attach(MIMEText(cuerpo + firma, "plain"))

        with smtplib.SMTP_SSL(SMTP_HOST, SMTP_PORT) as server:
            server.login(EMAIL_ADDRESS, EMAIL_PASSWORD)
            server.send_message(msg)

        log.info(f"Email enviado a {destinatario}")
        return True
    except Exception as e:
        log.error(f"Error SMTP al enviar a {destinatario}: {e}")
        return False

# ─────────────────────────────────────────────
# CICLO PRINCIPAL
# ─────────────────────────────────────────────
def procesar_emails():
    """Ciclo principal: lee, clasifica, notifica y registra emails."""
    log.info("=== Iniciando ciclo de revisión de emails ===")
    init_db()

    emails = leer_emails_no_leidos()
    if not emails:
        log.info("Sin emails nuevos.")
        return

    procesados = 0
    for em in emails:
        msg_id = em["message_id"]

        if email_ya_procesado(msg_id):
            log.info(f"Ya procesado: {em['asunto'][:60]}")
            continue

        log.info(f"Clasificando: {em['asunto'][:60]} | De: {em['remitente'][:50]}")

        # Clasificar con Claude
        clas = clasificar_email(em["remitente"], em["asunto"], em["cuerpo"])
        cat  = clas.get("categoria", "RUTINARIO")

        log.info(f"Categoría: {cat} | Prioridad: {clas.get('prioridad','?')}")

        # Guardar en DB local
        db_id = guardar_email({
            "message_id":       msg_id,
            "fecha":            em["fecha"],
            "remitente":        em["remitente"],
            "asunto":           em["asunto"],
            "categoria":        cat,
            "resumen":          clas.get("resumen", ""),
            "respuesta_sugerida": clas.get("respuesta_sugerida", "")
        })

        # Si es SPAM, skip
        if cat == "SPAM":
            log.info(f"SPAM ignorado: {em['asunto'][:60]}")
            continue

        # Si es LEAD → crear en Airtable
        airtable_ok = False
        if cat == "LEAD":
            record_id = crear_lead_airtable(em, clas)
            if record_id:
                guardar_airtable_id(msg_id, record_id)
                airtable_ok = True

        # Notificar a Jorge por Telegram (LEAD y URGENTE siempre; RUTINARIO solo si hay respuesta sugerida)
        if cat in ("LEAD", "URGENTE") or (cat == "RUTINARIO" and clas.get("respuesta_sugerida")):
            notif = formatear_notificacion(em, clas, db_id, airtable_ok)
            ok = enviar_telegram(notif)
            if ok:
                marcar_notificado(msg_id)
                log.info(f"Notificación enviada a Jorge: {cat}")

        procesados += 1
        time.sleep(1)  # Pequeña pausa entre procesados

    log.info(f"Ciclo completado. Procesados: {procesados}/{len(emails)}")

# ─────────────────────────────────────────────
# RESPONDER EMAIL (llamado desde bot Telegram)
# ─────────────────────────────────────────────
def responder_email_aprobado(db_id: int, texto_personalizado: str | None = None) -> tuple[bool, str]:
    """
    Envía respuesta a un email con aprobación del Jefe.
    db_id: ID del email en la DB local
    texto_personalizado: si None, usa la respuesta sugerida por Claude
    """
    em = get_email_by_db_id(db_id)
    if not em:
        return False, f"No encontré email con ID {db_id}"

    if em.get("respondido"):
        return False, f"Este email ya fue respondido el {em.get('fecha')}"

    # Email destino — extraer del campo remitente
    remitente_raw = em.get("remitente", "")
    email_dest = ""
    if "<" in remitente_raw and ">" in remitente_raw:
        email_dest = remitente_raw.split("<")[1].split(">")[0].strip()
    elif "@" in remitente_raw:
        email_dest = remitente_raw.strip()

    if not email_dest:
        return False, f"No pude extraer email destino de: {remitente_raw}"

    cuerpo = texto_personalizado if texto_personalizado else em.get("respuesta_sugerida", "")
    if not cuerpo:
        return False, "No hay texto de respuesta. Usa: /responder {db_id} tu mensaje"

    ok = enviar_respuesta_email(email_dest, em.get("asunto", ""), cuerpo)
    if ok:
        marcar_respondido(db_id, cuerpo)
        return True, f"Email enviado a {email_dest}"
    else:
        return False, f"Error al enviar a {email_dest}. Revisa credenciales SMTP."


# ─────────────────────────────────────────────
# ENTRY POINT
# ─────────────────────────────────────────────
if __name__ == "__main__":
    # Modo: monitoreo continuo o un solo ciclo
    if "--daemon" in sys.argv:
        log.info("Modo daemon — revisión cada 5 minutos")
        while True:
            try:
                procesar_emails()
            except Exception as e:
                log.error(f"Error en ciclo principal: {e}")
            time.sleep(300)  # 5 minutos
    else:
        # Un solo ciclo (para cron o systemd)
        procesar_emails()
