"""
ALEX — Telegram Bot (Full Capabilities)
Real Estate Investment AI Assistant

Capacidades completas:
- Sub-agentes: El Scout, El Matemático, El Fact-Checker, Tracy
- Airtable: lectura y escritura completa (Contacts, Leads, Deals, Notes & Activity)
- Web fetch: El Scout puede obtener datos reales de mercado
- Memoria compartida: memoria_ALex.md + telegram_memory.md
- Multimedia: texto, voz (Whisper), fotos (Claude Vision), videos
"""

import os
import asyncio
import base64
import json
import logging
import tempfile
import time
from datetime import datetime
from pathlib import Path
from functools import partial

try:
    import requests as http_requests
except ImportError:
    http_requests = None

from telegram import Update
from telegram.ext import (
    Application, MessageHandler, CommandHandler, filters, ContextTypes,
)
import anthropic

# ─────────────────────────────────────────────
# CONFIGURACIÓN
# ─────────────────────────────────────────────
TELEGRAM_TOKEN   = "8157575601:AAHmAo0OQroOUdXCnXZEjVh4hJkt0emx5_c"
ANTHROPIC_KEY    = "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"
CLAUDE_MODEL     = "claude-sonnet-4-6"
MAX_HISTORY      = 40

AIRTABLE_TOKEN   = "patQXGBEGdmbhGRfi.81e226fee4638f95bba27a57003465dd930d9e977d8b4dc7ac372c1b60dd087b"
AIRTABLE_BASE_ID = "appfQbDA750Oihy9J"
AIRTABLE_BASE_URL = f"https://api.airtable.com/v0/{AIRTABLE_BASE_ID}"
TRACERFY_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbl90eXBlIjoiYWNjZXNzIiwiZXhwIjozMjQyNTY2NDc4LCJpYXQiOjE3NzM3NjY0NzgsImp0aSI6IjFkNmMwZTc5YjRjZDRmZGY5YTUzNmQ4NTAzYjFiNTY2IiwidXNlcl9pZCI6NTc2MX0.P7H9nO6KFP-2UfSDl33RX4yxOislHV-v7V2vbPlJIWg"

TABLE_IDS = {
    "Contacts":         "tblacvw0Ss770x8l5",
    "Leads":            "tblxZz2EWIglOLnEd",
    "Deals":            "tbliaEKxBHKBx7ZK2",
    "Notes & Activity": "tbleOBXJl7sDhwj5w",
    "Tracy":            "tbl6CJm4kYspOuTDB",
}

# Directorios del proyecto
PROJECT_DIR     = Path(__file__).parent.parent
CLAUDE_MD       = PROJECT_DIR / "CLAUDE.md"
MEMORIA_ALEX    = PROJECT_DIR / "memoria_ALex.md"
TELEGRAM_MEM    = PROJECT_DIR / "telegram_bot" / "telegram_memory.md"
SESSIONS_DIR    = PROJECT_DIR / "telegram_bot" / "sessions"
AGENTS_DIR      = PROJECT_DIR / "agents"
PROTOCOLO_SEG   = PROJECT_DIR / "agents" / "protocolo_seguro.md"
COLA_MENSAJES   = PROJECT_DIR / "agents" / "cola_mensajes.md"
ALERT_SCRIPT    = PROJECT_DIR / "agents" / "alerta_telegram.sh"
OWNER_CHAT_ID   = "8402370952"

SESSIONS_DIR.mkdir(exist_ok=True)

# ─────────────────────────────────────────────
# LOGGING & CLIENTE
# ─────────────────────────────────────────────
logging.basicConfig(
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    level=logging.INFO
)
logger = logging.getLogger(__name__)

client = anthropic.Anthropic(api_key=ANTHROPIC_KEY)
conversation_history: dict[int, list] = {}
whisper_model = None


# ─────────────────────────────────────────────
# AGENT PROMPTS
# ─────────────────────────────────────────────

def load_agent_prompt(agent_name: str) -> str:
    path = AGENTS_DIR / f"{agent_name}.md"
    if path.exists():
        return path.read_text(encoding="utf-8")
    return f"Eres el sub-agente {agent_name} del equipo ALEX de inversión inmobiliaria. Sigue las instrucciones del Orquestador."


# ─────────────────────────────────────────────
# TOOL DEFINITIONS — Lo que ALEX puede invocar
# ─────────────────────────────────────────────

TOOLS = [
    {
        "name": "invoke_scout",
        "description": (
            "Invoca a El Scout para investigar el mercado de una propiedad o zona. "
            "Devuelve JSON con datos de mercado, comparables, estimación de rentas y riesgos. "
            "Úsalo SIEMPRE que el usuario pida analizar una propiedad o zona de inversión."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "property_data": {
                    "type": "string",
                    "description": "Datos completos de la propiedad: dirección, zip code, tipo, precio listado, tamaño estimado, condición"
                },
                "strategy": {
                    "type": "string",
                    "description": "Estrategia de inversión: Fix & Flip, Buy & Hold, BRRRR, Wholesale, Multifamily"
                }
            },
            "required": ["property_data", "strategy"]
        }
    },
    {
        "name": "invoke_matematico",
        "description": (
            "Invoca a El Matemático para el underwriting financiero completo. "
            "Calcula ARV, rehab, holding costs, profit, ROI, cashflow, cap rate y stress tests. "
            "Úsalo después de El Scout (o en paralelo si ya tienes suficientes datos)."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "property_data": {
                    "type": "string",
                    "description": "Datos de la propiedad incluyendo precio de compra y características"
                },
                "scout_json": {
                    "type": "string",
                    "description": "JSON output de El Scout (puede estar vacío si no está disponible)"
                },
                "strategy": {
                    "type": "string",
                    "description": "Estrategia de inversión"
                }
            },
            "required": ["property_data", "strategy"]
        }
    },
    {
        "name": "invoke_fact_checker",
        "description": (
            "Invoca a El Fact-Checker para auditar el deal y asignar Confidence Score (1-10). "
            "Detecta sesgos optimistas, valida comps y determina el veredicto final. "
            "Úsalo SIEMPRE después de El Scout y El Matemático."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "property_data": {"type": "string"},
                "scout_json": {
                    "type": "string",
                    "description": "JSON completo de El Scout"
                },
                "matematico_json": {
                    "type": "string",
                    "description": "JSON completo de El Matemático"
                }
            },
            "required": ["property_data", "scout_json", "matematico_json"]
        }
    },
    {
        "name": "invoke_tracy",
        "description": (
            "Invoca a Tracy para skip tracing de una dirección de propiedad. "
            "Busca al dueño y sus familiares en Tracerfy y escribe los contactos directamente en Airtable (tabla Contacts). "
            "Úsalo cuando el usuario pida skip trace o buscar el dueño de una propiedad."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "address":  {"type": "string", "description": "Calle y número (e.g. '123 Main St')"},
                "city":     {"type": "string", "description": "Ciudad"},
                "state":    {"type": "string", "description": "Abreviatura del estado (e.g. 'WI')"},
                "zip_code": {"type": "string", "description": "Código postal"}
            },
            "required": ["address"]
        }
    },
    {
        "name": "airtable_list",
        "description": "Lee registros de una tabla de Airtable. Úsalo para buscar leads, deals, contactos o actividades.",
        "input_schema": {
            "type": "object",
            "properties": {
                "table": {
                    "type": "string",
                    "enum": ["Contacts", "Leads", "Deals", "Notes & Activity"],
                    "description": "Nombre de la tabla"
                },
                "filter_formula": {
                    "type": "string",
                    "description": "Fórmula de filtro Airtable (opcional), e.g.: {Stage}='New Lead'"
                },
                "max_records": {
                    "type": "integer",
                    "description": "Máximo de registros a retornar (default: 20)"
                }
            },
            "required": ["table"]
        }
    },
    {
        "name": "airtable_create",
        "description": "Crea un registro nuevo en Airtable. Úsalo para añadir deals, leads, contactos o notas de actividad.",
        "input_schema": {
            "type": "object",
            "properties": {
                "table": {
                    "type": "string",
                    "enum": ["Contacts", "Leads", "Deals", "Notes & Activity"]
                },
                "fields": {
                    "type": "object",
                    "description": "Campos del registro. Los nombres deben coincidir exactamente con las columnas de Airtable."
                }
            },
            "required": ["table", "fields"]
        }
    },
    {
        "name": "airtable_update",
        "description": "Actualiza un registro existente en Airtable por su record_id.",
        "input_schema": {
            "type": "object",
            "properties": {
                "table": {
                    "type": "string",
                    "enum": ["Contacts", "Leads", "Deals", "Notes & Activity"]
                },
                "record_id": {
                    "type": "string",
                    "description": "ID del registro Airtable (empieza con 'rec')"
                },
                "fields": {
                    "type": "object",
                    "description": "Campos a actualizar con sus nuevos valores"
                }
            },
            "required": ["table", "record_id", "fields"]
        }
    },
    {
        "name": "read_memoria",
        "description": "Lee la memoria operacional de ALEX (memoria_ALex.md). Contiene deals analizados, zip codes, lecciones aprendidas, flags de riesgo. Úsalo al inicio de cada análisis.",
        "input_schema": {"type": "object", "properties": {}}
    },
    {
        "name": "write_memoria",
        "description": "Escribe nuevos aprendizajes en la memoria operacional de ALEX (memoria_ALex.md). Úsalo después de completar un análisis de deal.",
        "input_schema": {
            "type": "object",
            "properties": {
                "content": {
                    "type": "string",
                    "description": "Contenido a añadir. Incluye fecha YYYY-MM-DD y puntos concisos sobre el deal, lecciones y flags."
                }
            },
            "required": ["content"]
        }
    },
    {
        "name": "web_fetch",
        "description": "Fetches a URL and returns the page content. Use to get real-time market data from Zillow, Redfin, Realtor.com, etc.",
        "input_schema": {
            "type": "object",
            "properties": {
                "url":     {"type": "string", "description": "URL completa a fetchear"},
                "purpose": {"type": "string", "description": "Para qué sirve este fetch (logging)"}
            },
            "required": ["url"]
        }
    }
]

# Herramientas disponibles para El Scout (solo web_fetch)
SCOUT_TOOLS = [t for t in TOOLS if t["name"] == "web_fetch"]

PROGRESS_MESSAGES = {
    "invoke_scout":       "🔍 *El Scout* investigando el mercado...",
    "invoke_matematico":  "🧮 *El Matemático* calculando el underwriting...",
    "invoke_fact_checker":"🔎 *El Fact-Checker* auditando el deal...",
    "invoke_tracy":       "👤 *Tracy* buscando al propietario en Tracerfy...",
    "airtable_list":      "📋 Consultando Airtable...",
    "airtable_create":    "💾 Guardando registro en Airtable...",
    "airtable_update":    "✏️ Actualizando registro en Airtable...",
    "read_memoria":       "🧠 Leyendo memoria operacional...",
    "write_memoria":      "💾 Guardando aprendizajes en memoria...",
    "web_fetch":          "🌐 Obteniendo datos de la web...",
}


# ─────────────────────────────────────────────
# TOOL IMPLEMENTATION FUNCTIONS (SYNC)
# ─────────────────────────────────────────────

def _airtable_headers() -> dict:
    return {
        "Authorization": f"Bearer {AIRTABLE_TOKEN}",
        "Content-Type": "application/json"
    }


def _tool_airtable_list(table: str, filter_formula: str = None, max_records: int = 20) -> str:
    if not http_requests:
        return "Error: librería 'requests' no instalada. Ejecuta: pip install requests"
    table_id = TABLE_IDS.get(table)
    if not table_id:
        return json.dumps({"error": f"Tabla '{table}' no encontrada. Tablas disponibles: {list(TABLE_IDS.keys())}"})
    params = {"maxRecords": str(max_records)}
    if filter_formula:
        params["filterByFormula"] = filter_formula
    try:
        resp = http_requests.get(
            f"{AIRTABLE_BASE_URL}/{table_id}",
            headers=_airtable_headers(),
            params=params,
            timeout=30
        )
        data = resp.json()
        if "error" in data:
            return json.dumps({"error": data["error"], "message": data.get("message", "")})
        records = data.get("records", [])
        return json.dumps({"table": table, "count": len(records), "records": records}, ensure_ascii=False)
    except Exception as e:
        return json.dumps({"error": str(e)})


def _tool_airtable_create(table: str, fields: dict) -> str:
    if not http_requests:
        return "Error: librería 'requests' no instalada."
    table_id = TABLE_IDS.get(table)
    if not table_id:
        return json.dumps({"error": f"Tabla '{table}' no encontrada."})
    try:
        resp = http_requests.post(
            f"{AIRTABLE_BASE_URL}/{table_id}",
            headers=_airtable_headers(),
            json={"fields": fields},
            timeout=30
        )
        data = resp.json()
        if "error" in data:
            return json.dumps({"error": data["error"], "message": data.get("message", "")})
        return json.dumps({
            "status": "created",
            "record_id": data.get("id"),
            "fields": data.get("fields", {})
        }, ensure_ascii=False)
    except Exception as e:
        return json.dumps({"error": str(e)})


def _tool_airtable_update(table: str, record_id: str, fields: dict) -> str:
    if not http_requests:
        return "Error: librería 'requests' no instalada."
    table_id = TABLE_IDS.get(table)
    if not table_id:
        return json.dumps({"error": f"Tabla '{table}' no encontrada."})
    try:
        resp = http_requests.patch(
            f"{AIRTABLE_BASE_URL}/{table_id}/{record_id}",
            headers=_airtable_headers(),
            json={"fields": fields},
            timeout=30
        )
        data = resp.json()
        if "error" in data:
            return json.dumps({"error": data["error"], "message": data.get("message", "")})
        return json.dumps({"status": "updated", "record_id": data.get("id")}, ensure_ascii=False)
    except Exception as e:
        return json.dumps({"error": str(e)})


def _tool_web_fetch(url: str, purpose: str = "") -> str:
    if not http_requests:
        return "Error: librería 'requests' no instalada."
    try:
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        }
        resp = http_requests.get(url, headers=headers, timeout=20)
        content = resp.text
        if len(content) > 10000:
            content = content[:10000] + "\n...[contenido truncado a 10,000 caracteres]"
        return content
    except Exception as e:
        return f"Error fetching {url}: {str(e)}"


def _tool_read_memoria() -> str:
    if MEMORIA_ALEX.exists():
        return MEMORIA_ALEX.read_text(encoding="utf-8")
    return "No hay memoria operacional registrada aún."


def _tool_write_memoria(content: str) -> str:
    try:
        existing = MEMORIA_ALEX.read_text(encoding="utf-8") if MEMORIA_ALEX.exists() else ""
        updated = existing + "\n\n" + content if existing.strip() else content
        MEMORIA_ALEX.write_text(updated, encoding="utf-8")
        return "✅ Memoria operacional actualizada correctamente."
    except Exception as e:
        return f"Error escribiendo memoria: {str(e)}"


def _run_subagent_sync(system_prompt: str, user_message: str, tools=None) -> str:
    """
    Runs a sub-agent as a separate synchronous Claude API call.
    Supports a nested tool use loop for tools like web_fetch.
    """
    messages = [{"role": "user", "content": user_message}]
    max_iters = 12

    for _ in range(max_iters):
        kwargs = {
            "model": CLAUDE_MODEL,
            "max_tokens": 4096,
            "system": system_prompt,
            "messages": messages
        }
        if tools:
            kwargs["tools"] = tools

        response = client.messages.create(**kwargs)

        if response.stop_reason == "end_turn":
            for block in response.content:
                if hasattr(block, "text"):
                    return block.text
            return "Sub-agent returned no text."

        elif response.stop_reason == "tool_use":
            messages.append({"role": "assistant", "content": response.content})
            tool_results = []
            for block in response.content:
                if hasattr(block, "type") and block.type == "tool_use":
                    if block.name == "web_fetch":
                        result = _tool_web_fetch(
                            block.input.get("url", ""),
                            block.input.get("purpose", "")
                        )
                    else:
                        result = f"Tool '{block.name}' not available inside sub-agent."
                    tool_results.append({
                        "type": "tool_result",
                        "tool_use_id": block.id,
                        "content": result
                    })
            messages.append({"role": "user", "content": tool_results})
        else:
            break

    return "Sub-agent reached max iterations without a final response."


def _tool_invoke_scout(property_data: str, strategy: str) -> str:
    system_prompt = load_agent_prompt("scout")
    user_msg = (
        f"Analiza esta propiedad:\n\n{property_data}\n\n"
        f"Estrategia objetivo: {strategy}\n\n"
        "Investiga el mercado activamente usando web_fetch para obtener datos reales. "
        "Devuelve únicamente el JSON estricto de tu análisis."
    )
    logger.info(f"Invoking El Scout for: {property_data[:80]}...")
    return _run_subagent_sync(system_prompt, user_msg, tools=SCOUT_TOOLS)


def _tool_invoke_matematico(property_data: str, strategy: str, scout_json: str = "") -> str:
    system_prompt = load_agent_prompt("matematico")
    scout_section = f"\n\nDatos de mercado del Scout:\n{scout_json}" if scout_json else "\n\nDatos del Scout: No disponibles — usa estimaciones conservadoras basadas en el mercado de Wisconsin."
    user_msg = (
        f"Calcula el underwriting financiero para esta propiedad:\n\n{property_data}\n\n"
        f"Estrategia: {strategy}{scout_section}\n\n"
        "Devuelve únicamente el JSON estricto de tu análisis."
    )
    logger.info("Invoking El Matemático...")
    return _run_subagent_sync(system_prompt, user_msg)


def _tool_invoke_fact_checker(property_data: str, scout_json: str, matematico_json: str) -> str:
    system_prompt = load_agent_prompt("fact-checker")
    user_msg = (
        f"Audita este deal:\n\nPROPIEDAD:\n{property_data}\n\n"
        f"JSON DE EL SCOUT:\n{scout_json}\n\n"
        f"JSON DE EL MATEMÁTICO:\n{matematico_json}\n\n"
        "Devuelve únicamente el JSON estricto de tu auditoría con el Confidence Score."
    )
    logger.info("Invoking El Fact-Checker...")
    return _run_subagent_sync(system_prompt, user_msg)


def _tool_invoke_tracy(address: str, city: str = "", state: str = "", zip_code: str = "") -> str:
    """
    Full Tracy implementation: check dup → Tracy log (pending) → CSV → Tracerfy → poll → update Tracy → write Contacts.
    """
    if not http_requests:
        return json.dumps({"tracy_results": {"status": "failed", "errors": ["librería 'requests' no instalada"]}})

    full_address = ", ".join(filter(None, [address, city, state, zip_code]))
    logger.info(f"Tracy skip tracing: {full_address}")
    tracy_table_id = TABLE_IDS["Tracy"]
    now_iso = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.000Z") if True else ""

    # import timezone locally if not available
    try:
        from datetime import timezone as tz
        now_iso = datetime.now(tz.utc).strftime("%Y-%m-%dT%H:%M:%S.000Z")
    except Exception:
        now_iso = datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%S.000Z")

    try:
        at_headers = {
            "Authorization": f"Bearer {AIRTABLE_TOKEN}",
            "Content-Type": "application/json",
        }

        # ── PASO 0: Verificar duplicados ──────────────────────────
        formula = f"AND(LOWER({{address}})=LOWER('{address}'),{{status}}='success')"
        dup_resp = http_requests.get(
            f"{AIRTABLE_BASE_URL}/{tracy_table_id}",
            headers=at_headers,
            params={"filterByFormula": formula, "maxRecords": 1},
            timeout=20
        ).json()
        dup_records = dup_resp.get("records", [])
        if dup_records:
            prev = dup_records[0].get("fields", {})
            return json.dumps({
                "tracy_results": {
                    "status": "duplicate",
                    "property_address": full_address,
                    "tracy_record_id": dup_records[0].get("id"),
                    "notes": f"Ya rastreada el {prev.get('fecha_rastreo','?')}. Resultado previo: {prev.get('resultado','?')}",
                    "errors": [], "contacts_found": [], "total_contacts_found": 0, "total_written_to_airtable": 0,
                }
            }, ensure_ascii=False)

        # ── PASO 1: Crear registro pending en Tracy ───────────────
        tracy_fields = {
            "address": address, "fecha_rastreo": now_iso,
            "status": "pending", "notas": "Rastreo iniciado por ALEX (Telegram)",
        }
        if city:     tracy_fields["city"]  = city
        if state:    tracy_fields["state"] = state
        if zip_code: tracy_fields["zip"]   = zip_code

        tracy_create = http_requests.post(
            f"{AIRTABLE_BASE_URL}/{tracy_table_id}",
            headers=at_headers, json={"fields": tracy_fields}, timeout=20
        ).json()
        tracy_record_id = tracy_create.get("id")
        if not tracy_record_id:
            logger.warning(f"Tracy: no se pudo crear registro pending: {tracy_create}")

        # ── PASO 2+3: CSV + POST to Tracerfy ─────────────────────
        csv_content = "address,city,state,zip,first_name,last_name,mail_address,mail_city,mail_state\n"
        csv_content += f'"{address}","{city}","{state}","{zip_code}","","","{address}","{city}","{state}"'

        tracerfy_headers = {"Authorization": f"Bearer {TRACERFY_API_KEY}"}
        files = {"csv_file": ("tracy_input.csv", csv_content.encode("utf-8"), "text/csv")}
        data  = {
            "address_column":      "address",
            "city_column":         "city",
            "state_column":        "state",
            "first_name_column":   "first_name",
            "last_name_column":    "last_name",
            "mail_address_column": "mail_address",
            "mail_city_column":    "mail_city",
            "mail_state_column":   "mail_state",
        }

        resp = http_requests.post(
            "https://tracerfy.com/v1/api/trace/",
            headers=tracerfy_headers,
            files=files,
            data=data,
            timeout=30
        )
        trace_data = resp.json()

        if "queue_id" not in trace_data:
            error_msg = f"Tracerfy response sin queue_id: {trace_data}"
            if tracy_record_id:
                http_requests.patch(
                    f"{AIRTABLE_BASE_URL}/{tracy_table_id}/{tracy_record_id}",
                    headers=at_headers,
                    json={"fields": {"status": "error", "resultado": error_msg}},
                    timeout=20
                )
            return json.dumps({
                "tracy_results": {
                    "status": "failed", "property_address": full_address,
                    "tracy_record_id": tracy_record_id,
                    "errors": [error_msg]
                }
            })

        queue_id = trace_data["queue_id"]
        logger.info(f"Tracy queue_id: {queue_id} — polling...")

        # Poll until completed (max 10 attempts × 15s = 2.5 min)
        result_data = None
        for attempt in range(10):
            time.sleep(15)
            poll = http_requests.get(
                f"https://tracerfy.com/v1/api/queue/{queue_id}",
                headers=tracerfy_headers,
                timeout=30
            )
            poll_data = poll.json()
            # El endpoint devuelve array cuando está listo
            if isinstance(poll_data, list):
                logger.info(f"Tracy poll attempt {attempt+1}: completed — {len(poll_data)} record(s)")
                result_data = {"status": "completed", "records": poll_data}
                break
            logger.info(f"Tracy poll attempt {attempt+1}: status={poll_data.get('status')}")
            if poll_data.get("status") not in ("pending", "processing", None):
                result_data = poll_data
                break

        if not result_data:
            if tracy_record_id:
                http_requests.patch(
                    f"{AIRTABLE_BASE_URL}/{tracy_table_id}/{tracy_record_id}",
                    headers=at_headers,
                    json={"fields": {"status": "error", "resultado": "Timeout — 10 intentos sin respuesta"}},
                    timeout=20
                )
            return json.dumps({
                "tracy_results": {
                    "status": "timeout",
                    "queue_id": queue_id,
                    "property_address": full_address,
                    "tracy_record_id": tracy_record_id,
                    "errors": ["Tracerfy timeout after 2.5 minutes"]
                }
            })

        # Extract contacts
        contacts = []
        records = result_data.get("records", result_data.get("results", []))
        if isinstance(records, list):
            for record in records:
                owner_name = (
                    record.get("name") or
                    f"{record.get('first_name', '')} {record.get('last_name', '')}".strip()
                )
                phones = [
                    record.get(k) for k in [
                        "primary_phone", "mobile_1", "mobile_2", "mobile_3",
                        "mobile_4", "mobile_5", "landline_1", "landline_2", "landline_3"
                    ] if record.get(k)
                ]
                emails = [
                    record.get(k) for k in ["email_1", "email_2", "email_3", "email_4", "email_5"]
                    if record.get(k)
                ]
                if owner_name:
                    contacts.append({
                        "name": owner_name,
                        "phone": phones[0] if phones else None,
                        "extra_phones": phones[1:],
                        "email": emails[0] if emails else None,
                        "extra_emails": emails[1:],
                        "address": record.get("mailing_address") or record.get("owner_address"),
                        "role": "Owner"
                    })

                for relative in record.get("relatives", record.get("associated_people", [])):
                    rel_name = (
                        relative.get("name") or
                        f"{relative.get('first_name', '')} {relative.get('last_name', '')}".strip()
                    )
                    rel_phones = [
                        relative.get(k) for k in ["phone", "mobile_1", "primary_phone"] if relative.get(k)
                    ]
                    rel_emails = [
                        relative.get(k) for k in ["email", "email_1"] if relative.get(k)
                    ]
                    if rel_name:
                        contacts.append({
                            "name": rel_name,
                            "phone": rel_phones[0] if rel_phones else None,
                            "extra_phones": [],
                            "email": rel_emails[0] if rel_emails else None,
                            "extra_emails": [],
                            "role": "Relative"
                        })

        def _to_e164_int(phone_str):
            if not phone_str: return None
            digits = "".join(c for c in str(phone_str) if c.isdigit())
            if not digits: return None
            if len(digits) == 10:   digits = "1" + digits
            elif len(digits) == 11 and digits.startswith("1"): pass
            else: return None
            return int(digits)

        # Write to Airtable
        airtable_results = []
        for contact in contacts:
            fields = {
                "Full Name":    contact["name"],
                "Category":     "Lead",
                "Stage":        "To Be Contacted",
                "Lead Source":  "Skip Trace - Tracy",
                "Owner Address": full_address,
            }
            # Teléfonos E.164 como entero
            all_phones = [contact.get("phone")] + contact.get("extra_phones", [])
            for key, ph in zip(["Phone1","Phone2","Phone3","Phone4"], all_phones):
                val = _to_e164_int(ph)
                if val: fields[key] = val
            if contact.get("phone_type"):
                fields["Phone1 Type"] = contact["phone_type"]
            # Emails
            all_emails = [contact.get("email")] + contact.get("extra_emails", [])
            for key, em in zip(["Email1","Email2","Email3"], all_emails):
                if em: fields[key] = em
            # Dirección postal
            if contact.get("mail_address"): fields["Mail Address"] = contact["mail_address"]
            if contact.get("mail_city"):    fields["Mail City"]    = contact["mail_city"]
            if contact.get("mail_state"):   fields["Mail State"]   = contact["mail_state"]
            if contact.get("mail_zip"):     fields["Mail Zip"]     = contact["mail_zip"]
            if contact.get("tracerfy_id"):  fields["Tracerfy ID"]  = int(contact["tracerfy_id"])

            at_result = _tool_airtable_create("Contacts", fields)
            try:
                at_data = json.loads(at_result)
            except Exception:
                at_data = {}

            airtable_results.append({
                "name":              contact["name"],
                "phone":             contact.get("phone"),
                "email":             contact.get("email"),
                "role":              contact["role"],
                "airtable_record_id": at_data.get("record_id", ""),
                "airtable_status":   "written" if at_data.get("record_id") else "failed"
            })

        written = sum(1 for r in airtable_results if r["airtable_status"] == "written")
        summary_names = ", ".join(
            f"{r['name']} ({r.get('phone') or 'sin tel'})"
            for r in airtable_results if r["airtable_status"] == "written"
        )
        resultado_str = f"{written} contacto(s): {summary_names}" if written else "Sin contactos escritos"
        notas_str = f"Owner + {sum(1 for c in airtable_results if c.get('role')=='Relative')} relative(s). Escritos en Contacts."

        # ── PASO 5: Actualizar Tracy con resultado ────────────────
        if tracy_record_id:
            http_requests.patch(
                f"{AIRTABLE_BASE_URL}/{tracy_table_id}/{tracy_record_id}",
                headers=at_headers,
                json={"fields": {"status": "success", "resultado": resultado_str, "notas": notas_str}},
                timeout=20
            )

        return json.dumps({
            "tracy_results": {
                "property_address":          full_address,
                "trace_date":                datetime.now().strftime("%Y-%m-%d"),
                "queue_id":                  queue_id,
                "tracy_record_id":           tracy_record_id,
                "status":                    "completed",
                "contacts_found":            airtable_results,
                "total_contacts_found":      len(airtable_results),
                "total_written_to_airtable": written,
                "errors":                    [],
                "notes":                     notas_str,
            }
        }, ensure_ascii=False)

    except Exception as e:
        logger.error(f"Tracy error: {e}")
        return json.dumps({
            "tracy_results": {
                "status": "failed",
                "property_address": full_address,
                "errors": [str(e)]
            }
        })


# ─────────────────────────────────────────────
# ASYNC TOOL DISPATCHER
# ─────────────────────────────────────────────

async def _execute_tool(tool_name: str, tool_input: dict) -> str:
    loop = asyncio.get_event_loop()

    if tool_name == "invoke_scout":
        return await loop.run_in_executor(None, lambda: _tool_invoke_scout(
            tool_input.get("property_data", ""),
            tool_input.get("strategy", "Fix & Flip")
        ))
    elif tool_name == "invoke_matematico":
        return await loop.run_in_executor(None, lambda: _tool_invoke_matematico(
            tool_input.get("property_data", ""),
            tool_input.get("strategy", "Fix & Flip"),
            tool_input.get("scout_json", "")
        ))
    elif tool_name == "invoke_fact_checker":
        return await loop.run_in_executor(None, lambda: _tool_invoke_fact_checker(
            tool_input.get("property_data", ""),
            tool_input.get("scout_json", ""),
            tool_input.get("matematico_json", "")
        ))
    elif tool_name == "invoke_tracy":
        return await loop.run_in_executor(None, lambda: _tool_invoke_tracy(
            tool_input.get("address", ""),
            tool_input.get("city", ""),
            tool_input.get("state", ""),
            tool_input.get("zip_code", "")
        ))
    elif tool_name == "airtable_list":
        return await loop.run_in_executor(None, lambda: _tool_airtable_list(
            tool_input.get("table", ""),
            tool_input.get("filter_formula"),
            tool_input.get("max_records", 20)
        ))
    elif tool_name == "airtable_create":
        return await loop.run_in_executor(None, lambda: _tool_airtable_create(
            tool_input.get("table", ""),
            tool_input.get("fields", {})
        ))
    elif tool_name == "airtable_update":
        return await loop.run_in_executor(None, lambda: _tool_airtable_update(
            tool_input.get("table", ""),
            tool_input.get("record_id", ""),
            tool_input.get("fields", {})
        ))
    elif tool_name == "read_memoria":
        return _tool_read_memoria()
    elif tool_name == "write_memoria":
        return _tool_write_memoria(tool_input.get("content", ""))
    elif tool_name == "web_fetch":
        return await loop.run_in_executor(None, lambda: _tool_web_fetch(
            tool_input.get("url", ""),
            tool_input.get("purpose", "")
        ))
    else:
        return f"Tool '{tool_name}' not implemented."


# ─────────────────────────────────────────────
# GESTIÓN DE MEMORIA PERSISTENTE
# ─────────────────────────────────────────────

def session_file(user_id: int) -> Path:
    return SESSIONS_DIR / f"user_{user_id}.json"


def load_history(user_id: int) -> list:
    f = session_file(user_id)
    if f.exists():
        try:
            data = json.loads(f.read_text(encoding="utf-8"))
            return data.get("messages", [])
        except Exception:
            return []
    return []


def save_history(user_id: int, messages: list):
    f = session_file(user_id)
    f.write_text(
        json.dumps({
            "user_id": user_id,
            "messages": messages,
            "updated": datetime.now().isoformat()
        }, ensure_ascii=False, indent=2),
        encoding="utf-8"
    )


def read_telegram_memory() -> str:
    if TELEGRAM_MEM.exists():
        return TELEGRAM_MEM.read_text(encoding="utf-8")
    return ""


def write_telegram_memory(content: str):
    TELEGRAM_MEM.write_text(content, encoding="utf-8")


def append_telegram_memory(entry: str):
    existing = read_telegram_memory()
    updated = existing + "\n\n" + entry if existing else entry
    write_telegram_memory(updated)


def append_memoria_alex(entry: str):
    existing = _tool_read_memoria()
    updated = existing + "\n\n" + entry if existing.strip() else entry
    MEMORIA_ALEX.write_text(updated, encoding="utf-8")


def send_security_alert(level: str, description: str, solutions: str = "Revisar logs del sistema."):
    """Envía alerta de seguridad al Jefe vía Telegram Bot API directamente."""
    import subprocess
    try:
        subprocess.run(
            ["bash", str(ALERT_SCRIPT), level, description, solutions],
            timeout=15, check=False
        )
        logger.warning(f"[SECURITY ALERT] {level}: {description}")
    except Exception as e:
        logger.error(f"[SECURITY ALERT] No se pudo enviar alerta: {e}")


def read_cola_mensajes() -> str:
    if COLA_MENSAJES.exists():
        return COLA_MENSAJES.read_text(encoding="utf-8")
    return ""


def write_cola_mensajes(entry: str):
    """Añade una entrada al canal inter-agente."""
    existing = read_cola_mensajes()
    updated = existing + "\n" + entry if existing.strip() else entry
    COLA_MENSAJES.write_text(updated, encoding="utf-8")


# ─────────────────────────────────────────────
# SYSTEM PROMPT
# ─────────────────────────────────────────────

def build_system_prompt() -> str:
    base = CLAUDE_MD.read_text(encoding="utf-8") if CLAUDE_MD.exists() else ""

    # Protocolo de seguridad
    protocolo = ""
    if PROTOCOLO_SEG.exists():
        protocolo = f"\n\n---\n## PROTOCOLO DE SEGURIDAD ACTIVO\n{PROTOCOLO_SEG.read_text(encoding='utf-8')}"

    # Cola de mensajes inter-agente
    cola = read_cola_mensajes()
    cola_section = ""
    if cola and cola.strip():
        cola_section = f"\n\n---\n## COLA DE MENSAJES INTER-AGENTE (cola_mensajes.md)\n{cola}"

    memoria_alex = ""
    if MEMORIA_ALEX.exists():
        memoria_alex = f"\n\n---\n## MEMORIA OPERACIONAL (memoria_ALex.md)\n{MEMORIA_ALEX.read_text(encoding='utf-8')}"

    telegram_mem = read_telegram_memory()
    memoria_telegram = ""
    if telegram_mem:
        memoria_telegram = f"\n\n---\n## MEMORIA DE CONVERSACIONES PREVIAS (telegram_memory.md)\n{telegram_mem}"

    telegram_note = """
---
## CONTEXTO DE OPERACIÓN: TELEGRAM

Estás operando a través de Telegram con CAPACIDADES COMPLETAS — exactamente igual que en Claude Code.

### Herramientas disponibles:
- **invoke_scout** — Invoca a El Scout para investigar mercados
- **invoke_matematico** — Invoca a El Matemático para underwriting financiero
- **invoke_fact_checker** — Invoca a El Fact-Checker para auditoría del deal
- **invoke_tracy** — Invoca a Tracy para skip tracing y escritura en Airtable
- **airtable_list / airtable_create / airtable_update** — Lectura y escritura directa en Airtable
- **read_memoria / write_memoria** — Leer y actualizar la memoria operacional
- **web_fetch** — Obtener datos en tiempo real de URLs

### Flujo obligatorio para análisis de deals:
1. Lee memoria (read_memoria) → 2. Lanza Scout y Matemático → 3. Lanza Fact-Checker → 4. Consolida reporte → 5. Escribe en memoria

### Instrucciones de comunicación:
- Responde siempre en español a menos que el Jefe escriba en inglés.
- Envía mensajes de progreso MIENTRAS trabajan los sub-agentes (el bot los muestra automáticamente).
- Sé conciso pero completo. Usa emojis con moderación (✅ ❌ 🏠 💰 📊).
- IMPORTANTE: Tienes acceso completo a la memoria — úsala para dar continuidad entre sesiones de Telegram y Claude Code.
"""
    return base + protocolo + cola_section + telegram_note + memoria_alex + memoria_telegram


# ─────────────────────────────────────────────
# ESTADO GLOBAL
# ─────────────────────────────────────────────
logging.basicConfig(
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    level=logging.INFO
)
logger = logging.getLogger(__name__)

client = anthropic.Anthropic(api_key=ANTHROPIC_KEY)
conversation_history: dict[int, list] = {}
whisper_model = None


# ─────────────────────────────────────────────
# WHISPER (lazy load)
# ─────────────────────────────────────────────

def _load_whisper():
    global whisper_model
    if whisper_model is None:
        try:
            from faster_whisper import WhisperModel
            logger.info("Cargando modelo Whisper...")
            whisper_model = WhisperModel("base", device="cpu", compute_type="int8")
            logger.info("Whisper listo.")
        except ImportError:
            logger.error("faster-whisper no instalado.")
    return whisper_model


def _transcribe_sync(file_path: str) -> str:
    model = _load_whisper()
    if model is None:
        return "[Error: faster-whisper no instalado]"
    segments, _ = model.transcribe(file_path, beam_size=5)
    return " ".join(seg.text.strip() for seg in segments)


async def transcribe(file_path: str) -> str:
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(None, partial(_transcribe_sync, file_path))


# ─────────────────────────────────────────────
# CLAUDE API — AGENTIC LOOP CON TOOL USE
# ─────────────────────────────────────────────

def get_history(user_id: int) -> list:
    if user_id not in conversation_history:
        conversation_history[user_id] = load_history(user_id)
    return conversation_history[user_id]


def _sanitize_history(messages: list) -> list:
    """Converts old image blocks to text to avoid API context errors."""
    result = []
    for i, msg in enumerate(messages):
        is_last_user = (i == len(messages) - 1 and msg["role"] == "user")
        content = msg["content"]
        if isinstance(content, list) and not is_last_user:
            text_parts = []
            for block in content:
                if isinstance(block, dict):
                    if block.get("type") == "text":
                        text_parts.append(block["text"])
                    elif block.get("type") == "image":
                        text_parts.append("[imagen enviada previamente]")
            result.append({"role": msg["role"], "content": " ".join(text_parts) or "[mensaje]"})
        else:
            result.append(msg)
    return result


async def ask_claude(user_id: int, content: list, progress_callback=None) -> str:
    """
    Main Claude interaction with full agentic tool use loop.
    ALEX can invoke sub-agents, Airtable, memory, and web_fetch.
    """
    history = get_history(user_id)
    history.append({"role": "user", "content": content})

    if len(history) > MAX_HISTORY:
        history = history[-MAX_HISTORY:]
        conversation_history[user_id] = history

    try:
        system_prompt = build_system_prompt()
        loop = asyncio.get_event_loop()

        # in-flight messages (includes tool_use/tool_result blocks, not stored in history)
        safe_messages = _sanitize_history(history)

        max_iterations = 20

        for iteration in range(max_iterations):
            def _call():
                return client.messages.create(
                    model=CLAUDE_MODEL,
                    max_tokens=4096,
                    system=system_prompt,
                    messages=safe_messages,
                    tools=TOOLS
                )

            response = await loop.run_in_executor(None, _call)

            if response.stop_reason == "end_turn":
                assistant_text = ""
                for block in response.content:
                    if hasattr(block, "text"):
                        assistant_text += block.text

                # Store only clean text in persistent history
                history.append({"role": "assistant", "content": assistant_text})
                conversation_history[user_id] = history
                save_history(user_id, history)
                return assistant_text

            elif response.stop_reason == "tool_use":
                # Add assistant response (with tool_use blocks) to in-flight messages
                safe_messages.append({"role": "assistant", "content": response.content})

                tool_results = []
                for block in response.content:
                    if hasattr(block, "type") and block.type == "tool_use":
                        # Send progress notification to Telegram
                        if progress_callback:
                            progress_msg = PROGRESS_MESSAGES.get(block.name, f"⚙️ Ejecutando {block.name}...")
                            try:
                                await progress_callback(progress_msg)
                            except Exception:
                                pass

                        logger.info(f"Tool call: {block.name} | Input: {str(block.input)[:120]}")
                        result = await _execute_tool(block.name, block.input)
                        logger.info(f"Tool result: {block.name} → {str(result)[:120]}")

                        tool_results.append({
                            "type": "tool_result",
                            "tool_use_id": block.id,
                            "content": result
                        })

                safe_messages.append({"role": "user", "content": tool_results})

            else:
                # max_tokens or unexpected stop
                break

        return "⚠️ ALEX alcanzó el límite de iteraciones. Intenta con una solicitud más específica."

    except Exception as e:
        logger.error(f"Error Claude API: {e}", exc_info=True)
        # Remove the user message on error to keep history clean
        if history and history[-1]["role"] == "user":
            history.pop()
        return f"❌ Error al conectar con ALEX: {str(e)}"


# ─────────────────────────────────────────────
# MEMORY SUMMARY HELPERS
# ─────────────────────────────────────────────

async def generate_memory_summary(user_id: int) -> str:
    history = get_history(user_id)
    if len(history) < 4:
        return ""

    summary_prompt = """Basándote en esta conversación, genera un resumen CONCISO para la memoria persistente de ALEX.

Incluye SOLO lo que sea relevante para futuras conversaciones:
- Propiedades o zonas discutidas (dirección, precio, estrategia, veredicto)
- Decisiones tomadas o deals en progreso
- Preferencias o instrucciones especiales del Jefe
- Contactos encontrados o acciones pendientes
- Cualquier contexto importante

Formato: fecha actual + puntos concisos. Máximo 200 palabras. Sin encabezados innecesarios."""

    try:
        loop = asyncio.get_event_loop()
        text_messages = []
        for msg in history[-30:]:
            content = msg["content"]
            if isinstance(content, str):
                text_messages.append(msg)
            elif isinstance(content, list):
                texts = [b["text"] for b in content if isinstance(b, dict) and b.get("type") == "text"]
                if texts:
                    text_messages.append({"role": msg["role"], "content": " ".join(texts)})

        def _call():
            return client.messages.create(
                model=CLAUDE_MODEL,
                max_tokens=400,
                messages=text_messages + [{"role": "user", "content": summary_prompt}]
            )

        response = await loop.run_in_executor(None, _call)
        return response.content[0].text
    except Exception as e:
        logger.error(f"Error generando resumen: {e}")
        return ""


async def generate_deal_notes(user_id: int) -> str:
    history = get_history(user_id)
    if len(history) < 4:
        return ""

    deal_prompt = """Revisa esta conversación. Si se analizaron propiedades o deals inmobiliarios, extrae las notas clave para el archivo memoria_ALex.md.

Si NO hubo análisis de propiedades o deals, responde exactamente: NO_DEALS

Si SÍ hubo deals, responde SOLO con las notas en este formato:
### [Fecha] — Sesión Telegram
- Propiedad: [dirección/zip si disponible]
- Estrategia: [Fix&Flip/BRRRR/Buy&Hold/etc]
- Veredicto: [Proceed/Discard/Gather More Data]
- Notas clave: [máximo 3 puntos concisos]
- Pendientes: [si aplica]

Máximo 150 palabras. Solo información factual."""

    try:
        loop = asyncio.get_event_loop()
        text_messages = []
        for msg in history[-30:]:
            content = msg["content"]
            if isinstance(content, str):
                text_messages.append(msg)
            elif isinstance(content, list):
                texts = [b["text"] for b in content if isinstance(b, dict) and b.get("type") == "text"]
                if texts:
                    text_messages.append({"role": msg["role"], "content": " ".join(texts)})

        def _call():
            return client.messages.create(
                model=CLAUDE_MODEL,
                max_tokens=300,
                messages=text_messages + [{"role": "user", "content": deal_prompt}]
            )

        response = await loop.run_in_executor(None, _call)
        result = response.content[0].text.strip()
        return "" if result == "NO_DEALS" else result
    except Exception as e:
        logger.error(f"Error generando notas de deal: {e}")
        return ""


# ─────────────────────────────────────────────
# HANDLERS — COMANDOS
# ─────────────────────────────────────────────

async def cmd_start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    history = get_history(user_id)
    sessions_count = len([m for m in history if m["role"] == "assistant"])

    await update.message.chat.send_action("typing")

    async def progress(msg):
        await update.message.reply_text(msg, parse_mode="Markdown")

    if sessions_count > 0:
        greeting = (
            f"Retoma la conversación conmigo. Tenemos {sessions_count} intercambios previos. "
            "Salúdame como ALEX, menciona brevemente la memoria de sesiones anteriores y pregunta en qué puedo ayudar hoy."
        )
    else:
        greeting = "Inicia sesión. Salúdame como el Jefe y preséntate como ALEX con todas tus capacidades."

    response = await ask_claude(user_id, [{"type": "text", "text": greeting}], progress_callback=progress)
    await update.message.reply_text(response)


async def cmd_reset(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    await update.message.reply_text("💾 Guardando resumen de sesión en memoria...")

    summary, deal_notes = await asyncio.gather(
        generate_memory_summary(user_id),
        generate_deal_notes(user_id)
    )

    date_str = datetime.now().strftime("%Y-%m-%d %H:%M")
    saved = []

    if summary:
        entry = f"### Sesión {date_str}\n{summary}"
        append_telegram_memory(entry)
        saved.append("telegram_memory.md")

    if deal_notes:
        append_memoria_alex(deal_notes)
        saved.append("memoria_ALex.md")

    if saved:
        await update.message.reply_text(f"✅ Memoria guardada en: {', '.join(saved)}\nHistorial limpiado.")
    else:
        await update.message.reply_text("✅ Historial limpiado (sin contenido suficiente para resumir).")

    conversation_history[user_id] = []
    save_history(user_id, [])


async def cmd_memoria(update: Update, context: ContextTypes.DEFAULT_TYPE):
    mem = read_telegram_memory()
    if mem:
        if len(mem) > 4000:
            mem = mem[:4000] + "\n...[truncado]"
        await update.message.reply_text(f"🧠 *Memoria de ALEX (Telegram):*\n\n{mem}", parse_mode="Markdown")
    else:
        await update.message.reply_text("No hay memoria de sesiones previas aún. Se guarda al usar /reset.")


async def cmd_guardar(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    await update.message.reply_text("💾 Guardando resumen en memoria...")

    summary, deal_notes = await asyncio.gather(
        generate_memory_summary(user_id),
        generate_deal_notes(user_id)
    )

    date_str = datetime.now().strftime("%Y-%m-%d %H:%M")
    saved = []

    if summary:
        entry = f"### Sesión {date_str}\n{summary}"
        append_telegram_memory(entry)
        saved.append("telegram_memory.md")

    if deal_notes:
        append_memoria_alex(deal_notes)
        saved.append("memoria_ALex.md")

    if saved:
        display = summary or deal_notes
        msg = f"✅ Guardado en: {', '.join(saved)}\n\n_{display}_"
        await update.message.reply_text(msg, parse_mode="Markdown")
    else:
        await update.message.reply_text("No hay suficiente conversación para resumir aún.")


async def cmd_historial(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    history = get_history(user_id)
    count = len(history)
    user_msgs = len([m for m in history if m["role"] == "user"])
    await update.message.reply_text(
        f"📊 *Historial activo:*\n"
        f"- Total mensajes: {count}\n"
        f"- Tus mensajes: {user_msgs}\n"
        f"- Respuestas de ALEX: {count - user_msgs}\n\n"
        f"Usa /guardar para guardar un resumen en memoria.\n"
        f"Usa /reset para guardar y limpiar el historial.",
        parse_mode="Markdown"
    )


async def cmd_capacidades(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        "🤖 *ALEX — Capacidades en Telegram:*\n\n"
        "🔍 *Sub-agentes:*\n"
        "• El Scout — Investigación de mercado con datos reales\n"
        "• El Matemático — Underwriting financiero completo\n"
        "• El Fact-Checker — Auditoría y Confidence Score\n"
        "• Tracy — Skip tracing + escritura en Airtable\n\n"
        "📋 *Airtable (lectura y escritura):*\n"
        "• Contacts, Leads, Deals, Notes & Activity\n\n"
        "🧠 *Memoria compartida con Claude Code:*\n"
        "• memoria\\_ALex.md (deals y lecciones)\n"
        "• telegram\\_memory.md (sesiones de Telegram)\n\n"
        "📱 *Multimedia:*\n"
        "• Texto, Voz (transcripción automática), Fotos, Videos\n\n"
        "⚡ *Comandos:*\n"
        "/start /reset /guardar /memoria /historial /capacidades",
        parse_mode="Markdown"
    )


# ─────────────────────────────────────────────
# HANDLERS — MENSAJES
# ─────────────────────────────────────────────

async def handle_text(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    text = update.message.text
    await update.message.chat.send_action("typing")

    async def progress(msg):
        await update.message.reply_text(msg, parse_mode="Markdown")

    response = await ask_claude(user_id, [{"type": "text", "text": text}], progress_callback=progress)
    for i in range(0, len(response), 4000):
        await update.message.reply_text(response[i:i+4000])


async def handle_voice(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    await update.message.chat.send_action("typing")

    voice_file = await update.message.voice.get_file()
    with tempfile.NamedTemporaryFile(suffix=".ogg", delete=False) as tmp:
        tmp_path = tmp.name

    await voice_file.download_to_drive(tmp_path)
    await update.message.reply_text("🎤 Transcribiendo...")
    transcript = await transcribe(tmp_path)
    os.unlink(tmp_path)

    if not transcript.strip():
        await update.message.reply_text("❌ No pude transcribir el audio.")
        return

    await update.message.reply_text(f"🎤 _{transcript}_", parse_mode="Markdown")
    await update.message.chat.send_action("typing")

    async def progress(msg):
        await update.message.reply_text(msg, parse_mode="Markdown")

    response = await ask_claude(user_id, [{"type": "text", "text": transcript}], progress_callback=progress)
    for i in range(0, len(response), 4000):
        await update.message.reply_text(response[i:i+4000])


async def handle_photo(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    caption = update.message.caption or "Analiza esta imagen. Dime qué ves y si es relevante para inversión inmobiliaria."
    await update.message.chat.send_action("typing")

    photo = update.message.photo[-1]
    photo_file = await photo.get_file()
    with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as tmp:
        tmp_path = tmp.name

    await photo_file.download_to_drive(tmp_path)
    with open(tmp_path, "rb") as f:
        image_data = base64.b64encode(f.read()).decode()
    os.unlink(tmp_path)

    async def progress(msg):
        await update.message.reply_text(msg, parse_mode="Markdown")

    content = [
        {"type": "image", "source": {"type": "base64", "media_type": "image/jpeg", "data": image_data}},
        {"type": "text", "text": caption}
    ]
    response = await ask_claude(user_id, content, progress_callback=progress)
    for i in range(0, len(response), 4000):
        await update.message.reply_text(response[i:i+4000])


async def handle_video(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    caption = update.message.caption or "Analiza este video."
    await update.message.chat.send_action("typing")

    async def progress(msg):
        await update.message.reply_text(msg, parse_mode="Markdown")

    if update.message.video.thumbnail:
        thumb_file = await update.message.video.thumbnail.get_file()
        with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as tmp:
            tmp_path = tmp.name
        await thumb_file.download_to_drive(tmp_path)
        with open(tmp_path, "rb") as f:
            image_data = base64.b64encode(f.read()).decode()
        os.unlink(tmp_path)
        content = [
            {"type": "image", "source": {"type": "base64", "media_type": "image/jpeg", "data": image_data}},
            {"type": "text", "text": f"[VIDEO — thumbnail] {caption}"}
        ]
    else:
        content = [{"type": "text", "text": f"[VIDEO sin thumbnail] {caption}"}]

    response = await ask_claude(user_id, content, progress_callback=progress)
    for i in range(0, len(response), 4000):
        await update.message.reply_text(response[i:i+4000])


async def handle_document(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    file_name = update.message.document.file_name or "documento"
    caption = update.message.caption or f"Recibí el documento: {file_name}"
    await update.message.chat.send_action("typing")

    async def progress(msg):
        await update.message.reply_text(msg, parse_mode="Markdown")

    response = await ask_claude(user_id, [{"type": "text", "text": caption}], progress_callback=progress)
    await update.message.reply_text(response)


async def handle_unknown(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text("No reconozco ese tipo de mensaje. Envíame texto, voz, foto o video.")


# ─────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────

async def main():
    if not http_requests:
        logger.warning("⚠️  Librería 'requests' no instalada. Airtable y Tracy no funcionarán. Ejecuta: pip install requests")

    logger.info("Iniciando ALEX Bot — Capacidades Completas...")

    app = Application.builder().token(TELEGRAM_TOKEN).build()

    # Comandos
    app.add_handler(CommandHandler("start",       cmd_start))
    app.add_handler(CommandHandler("reset",       cmd_reset))
    app.add_handler(CommandHandler("memoria",     cmd_memoria))
    app.add_handler(CommandHandler("guardar",     cmd_guardar))
    app.add_handler(CommandHandler("historial",   cmd_historial))
    app.add_handler(CommandHandler("capacidades", cmd_capacidades))

    # Mensajes
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_text))
    app.add_handler(MessageHandler(filters.VOICE,        handle_voice))
    app.add_handler(MessageHandler(filters.PHOTO,        handle_photo))
    app.add_handler(MessageHandler(filters.VIDEO,        handle_video))
    app.add_handler(MessageHandler(filters.Document.ALL, handle_document))
    app.add_handler(MessageHandler(filters.ALL,          handle_unknown))

    print("=" * 60)
    print("  ALEX Bot — Capacidades Completas")
    print("  Sub-agentes: Scout | Matemático | Fact-Checker | Tracy")
    print("  Airtable: Contacts | Leads | Deals | Notes & Activity")
    print("  Memoria: compartida con Claude Code")
    print("  /start /reset /guardar /memoria /historial /capacidades")
    print("  Ctrl+C para detener")
    print("=" * 60)

    async with app:
        await app.initialize()
        await app.start()
        await app.updater.start_polling(drop_pending_updates=True)
        try:
            await asyncio.Event().wait()
        except (KeyboardInterrupt, SystemExit):
            pass
        finally:
            await app.updater.stop()
            await app.stop()
            await app.shutdown()


if __name__ == "__main__":
    asyncio.run(main())
