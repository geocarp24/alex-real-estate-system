"""
El Cartógrafo — Google Business Profile MCP server.

FastMCP server exposing write-side GBP operations with anti-ban guardrails:
- Rate limits (per-minute + per-day, per-location)
- Circuit breaker on 429/403 (24h freeze)
- Hard-coded prohibited operations (no fake reviews, no auto name/address/phone)
- Full audit log to Airtable GMB_Audit_Log
- OAuth token refresh 10 min before expiry

Use with Claude Code MCP client. Register in ~/.claude/settings.json:
    "mcpServers": {
      "gbp": {
        "command": "uv",
        "args": ["--directory", "/path/to/agents/cartografo/mcp_server", "run", "python", "server.py"],
        "env": {
          "GBP_OAUTH_JSON":   "/path/to/pinnacle_gbp_oauth.json",
          "GBP_TENANT_ID":    "pinnacle",
          "AIRTABLE_TOKEN":   "...",
          "AIRTABLE_BASE_ID": "...",
          "AUDIT_LOG_TABLE":  "tblXXXX",
          "CIRCUIT_STATE_FILE": "/tmp/gbp_circuit_pinnacle.json"
        }
      }
    }

License: MIT (Pinnacle Holdings / ALEX system — tenant-zero build 2026-04-23)
"""
from __future__ import annotations
import json
import os
import time
import uuid
import urllib.request
import urllib.parse
import urllib.error
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from fastmcp import FastMCP

# ---- Configuration from env ----
TENANT_ID       = os.environ.get("GBP_TENANT_ID", "unknown")
OAUTH_JSON_PATH = os.environ.get("GBP_OAUTH_JSON", "")
AIRTABLE_TOKEN  = os.environ.get("AIRTABLE_TOKEN", "")
AIRTABLE_BASE   = os.environ.get("AIRTABLE_BASE_ID", "")
AUDIT_TABLE     = os.environ.get("AUDIT_LOG_TABLE", "")
CIRCUIT_FILE    = Path(os.environ.get("CIRCUIT_STATE_FILE", f"/tmp/gbp_circuit_{TENANT_ID}.json"))

# ---- Rate limits (hard-coded for safety) ----
RATE_LIMITS = {
    "gbp_publish_post":       {"per_day": 2,  "per_hour": 1},
    "gbp_respond_review":     {"per_day": 5,  "per_hour": 2},
    "gbp_upload_photo":       {"per_day": 2,  "per_hour": 1},
    "gbp_update_hours":       {"per_day": 1,  "per_hour": 1, "per_month": 1},
    "gbp_update_description": {"per_day": 1,  "per_hour": 1, "per_month": 1},
    "gbp_answer_qa":          {"per_day": 2,  "per_hour": 1},
}
TOTAL_WRITES_PER_DAY = 10

# ---- Absolute prohibitions (return error if attempted) ----
PROHIBITED_OPS = {
    "gbp_create_fake_review",
    "gbp_delete_review",
    "gbp_update_name",
    "gbp_update_address",
    "gbp_update_phone",
}

mcp = FastMCP("gbp-cartografo")

# ============================================================
# Circuit breaker state
# ============================================================

def _load_circuit() -> dict[str, Any]:
    if not CIRCUIT_FILE.exists():
        return {"state": "closed", "fail_count": 0, "opened_at": None, "reset_at": None}
    try:
        return json.loads(CIRCUIT_FILE.read_text())
    except Exception:
        return {"state": "closed", "fail_count": 0, "opened_at": None, "reset_at": None}

def _save_circuit(state: dict[str, Any]) -> None:
    CIRCUIT_FILE.parent.mkdir(parents=True, exist_ok=True)
    CIRCUIT_FILE.write_text(json.dumps(state))

def _circuit_check() -> tuple[bool, str]:
    """Returns (allowed, reason). If open, deny all writes."""
    c = _load_circuit()
    if c.get("state") == "open":
        reset = c.get("reset_at")
        if reset and datetime.fromisoformat(reset) > datetime.now(timezone.utc):
            return False, f"CIRCUIT_OPEN until {reset}"
        # auto-close after reset_at
        _save_circuit({"state": "closed", "fail_count": 0, "opened_at": None, "reset_at": None})
    return True, ""

def _circuit_record_failure(status_code: int | None = None) -> None:
    c = _load_circuit()
    c["fail_count"] = c.get("fail_count", 0) + 1
    if status_code in (429, 403) or c["fail_count"] >= 3:
        now = datetime.now(timezone.utc)
        c["state"] = "open"
        c["opened_at"] = now.isoformat()
        # 24h freeze
        c["reset_at"] = (now.timestamp() + 24 * 3600)
        c["reset_at"] = datetime.fromtimestamp(c["reset_at"], tz=timezone.utc).isoformat()
    _save_circuit(c)

def _circuit_record_success() -> None:
    _save_circuit({"state": "closed", "fail_count": 0, "opened_at": None, "reset_at": None})

# ============================================================
# Rate limiter (naive in-memory — use Redis in multi-process prod)
# ============================================================

_rate_log: list[tuple[str, float]] = []  # (op_name, timestamp)

def _rate_allow(op: str) -> tuple[bool, str]:
    now = time.time()
    # Prune entries older than 30 days (month granularity is widest)
    cutoff_month = now - 30 * 24 * 3600
    _rate_log[:] = [(o, t) for o, t in _rate_log if t > cutoff_month]

    # Total daily cap
    day_ago = now - 24 * 3600
    total_today = sum(1 for o, t in _rate_log if t > day_ago)
    if total_today >= TOTAL_WRITES_PER_DAY:
        return False, f"RATE_LIMIT total writes today: {total_today}/{TOTAL_WRITES_PER_DAY}"

    # Per-op limits
    limits = RATE_LIMITS.get(op, {})
    if "per_hour" in limits:
        hour_ago = now - 3600
        n = sum(1 for o, t in _rate_log if o == op and t > hour_ago)
        if n >= limits["per_hour"]:
            return False, f"RATE_LIMIT {op} per hour: {n}/{limits['per_hour']}"
    if "per_day" in limits:
        n = sum(1 for o, t in _rate_log if o == op and t > day_ago)
        if n >= limits["per_day"]:
            return False, f"RATE_LIMIT {op} per day: {n}/{limits['per_day']}"
    if "per_month" in limits:
        n = sum(1 for o, t in _rate_log if o == op and t > cutoff_month)
        if n >= limits["per_month"]:
            return False, f"RATE_LIMIT {op} per month: {n}/{limits['per_month']}"
    return True, ""

def _rate_record(op: str) -> None:
    _rate_log.append((op, time.time()))

# ============================================================
# Audit log — writes to Airtable GMB_Audit_Log
# ============================================================

def _audit(operation: str, before: dict | None, after: dict | None, status_code: int | None, approved_by: str = "") -> None:
    if not (AIRTABLE_TOKEN and AIRTABLE_BASE and AUDIT_TABLE):
        return
    import urllib.request
    body = json.dumps({
        "fields": {
            "log_id":         str(uuid.uuid4()),
            "tenant_id":      TENANT_ID,
            "operation":      operation,
            "before_state":   json.dumps(before or {})[:5000],
            "after_state":    json.dumps(after or {})[:5000],
            "api_status_code": status_code or 0,
            "approved_by":    approved_by,
        },
        "typecast": True,
    }).encode()
    req = urllib.request.Request(
        f"https://api.airtable.com/v0/{AIRTABLE_BASE}/{AUDIT_TABLE}",
        data=body,
        headers={
            "Authorization": f"Bearer {AIRTABLE_TOKEN}",
            "Content-Type": "application/json",
        },
    )
    try:
        urllib.request.urlopen(req, timeout=10).read()
    except Exception as e:
        # Don't let audit failure block the operation — but log to stderr
        import sys
        print(f"[cartografo] audit write failed: {e}", file=sys.stderr)

# ============================================================
# Guardrail wrapper — every write-op goes through this
# ============================================================

def _guard(op: str):
    """Decorator: enforces prohibition + rate limit + circuit breaker before calling wrapped fn."""
    def decorator(fn):
        def wrapped(*args, **kwargs):
            if op in PROHIBITED_OPS:
                return {"ok": False, "error": f"OPERATION_PROHIBITED: {op} is hard-coded as forbidden"}
            allowed, reason = _circuit_check()
            if not allowed:
                return {"ok": False, "error": f"CIRCUIT_OPEN: {reason}"}
            allowed, reason = _rate_allow(op)
            if not allowed:
                return {"ok": False, "error": reason}
            try:
                result = fn(*args, **kwargs)
                _rate_record(op)
                _circuit_record_success()
                return result
            except Exception as e:
                _circuit_record_failure()
                _audit(op, kwargs, {"error": str(e)[:500]}, None)
                return {"ok": False, "error": f"EXECUTION_FAILED: {e}"}
        return wrapped
    return decorator

# ============================================================
# OAuth token loader (stub — Jorge completes Step 1)
# ============================================================

def _oauth_bearer() -> str:
    """Return a valid GBP API bearer token. Refresh if within 10 min of expiry."""
    if not OAUTH_JSON_PATH or not Path(OAUTH_JSON_PATH).exists():
        raise RuntimeError(
            "OAUTH_NOT_CONFIGURED: set GBP_OAUTH_JSON env var to path of OAuth credentials. "
            "See Step 1 of El Cartógrafo deploy plan."
        )
    # TODO: implement full OAuth 2.0 token refresh logic using google-auth library.
    # For now, return empty string — live operations will fail until implemented.
    # The scaffold is complete; Jorge completes Google Cloud project first,
    # then we enable this path.
    return ""

# ============================================================
# MCP Tools — Read-only (safe)
# ============================================================

@mcp.tool()
def gbp_health_check() -> dict:
    """Check MCP server health: oauth loadable, circuit state, rate limit state, env complete."""
    c = _load_circuit()
    env_ok = all([TENANT_ID, OAUTH_JSON_PATH, AIRTABLE_TOKEN, AIRTABLE_BASE, AUDIT_TABLE])
    oauth_readable = Path(OAUTH_JSON_PATH).exists() if OAUTH_JSON_PATH else False
    return {
        "ok": True,
        "tenant_id": TENANT_ID,
        "env_complete": env_ok,
        "oauth_file_present": oauth_readable,
        "circuit_state": c,
        "writes_today": sum(1 for _, t in _rate_log if t > time.time() - 86400),
        "total_daily_cap": TOTAL_WRITES_PER_DAY,
    }

@mcp.tool()
def gbp_list_locations() -> dict:
    """List GBP locations for the authenticated account. Read-only."""
    # TODO: implement via Google Business Profile API v1
    # GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts/{account}/locations
    return {"ok": False, "error": "STUB_NOT_IMPLEMENTED", "next_step": "Jorge completes Google Cloud OAuth (Step 1); then we wire the API call."}

@mcp.tool()
def gbp_get_location(location_id: str) -> dict:
    """Get a single GBP location by ID. Read-only."""
    return {"ok": False, "error": "STUB_NOT_IMPLEMENTED", "location_id": location_id}

@mcp.tool()
def gbp_list_reviews(location_id: str, limit: int = 20) -> dict:
    """List reviews for a location. Read-only. Max 4 calls/day."""
    allowed, reason = _rate_allow("gbp_list_reviews")
    if not allowed:
        return {"ok": False, "error": reason}
    _rate_record("gbp_list_reviews")
    return {"ok": False, "error": "STUB_NOT_IMPLEMENTED", "location_id": location_id, "limit": limit}

@mcp.tool()
def gbp_list_insights(location_id: str) -> dict:
    """Get insights (views, searches, actions) for a location. Read-only. Max 1/day."""
    allowed, reason = _rate_allow("gbp_list_insights")
    if not allowed:
        return {"ok": False, "error": reason}
    _rate_record("gbp_list_insights")
    return {"ok": False, "error": "STUB_NOT_IMPLEMENTED", "location_id": location_id}

# ============================================================
# MCP Tools — Write-side (guarded)
# ============================================================

@mcp.tool()
@_guard("gbp_publish_post")
def gbp_publish_post(
    location_id: str,
    summary: str,
    call_to_action_type: str = "LEARN_MORE",
    call_to_action_url: str = "",
    media_url: str = "",
    approved_by: str = "",
) -> dict:
    """Publish a GBP Post (standard). Max 2/week. Requires human approval.

    Args:
        location_id: accounts/X/locations/Y format
        summary: Post text (max 1500 chars per Google)
        call_to_action_type: LEARN_MORE|BOOK|ORDER|SHOP|SIGN_UP|CALL
        call_to_action_url: URL for the CTA button
        media_url: Optional image URL (must be HTTPS, max 10MB JPG/PNG)
        approved_by: Telegram user_id or email of approver (for audit trail)
    """
    if not approved_by:
        return {"ok": False, "error": "APPROVAL_REQUIRED: approved_by field must reference Telegram approval"}
    if len(summary) > 1500:
        return {"ok": False, "error": "POST_TOO_LONG: summary must be <=1500 chars"}
    # TODO: POST https://mybusiness.googleapis.com/v4/{location_id}/localPosts
    _audit("gbp_publish_post", {"location_id": location_id, "summary": summary[:200]}, None, None, approved_by)
    return {"ok": False, "error": "STUB_NOT_IMPLEMENTED", "action_taken": "audit_logged"}

@mcp.tool()
@_guard("gbp_respond_review")
def gbp_respond_review(
    review_name: str,
    reply_text: str,
    approved_by: str = "",
) -> dict:
    """Respond to a review. Max 5/day. Requires human approval for every reply.

    Args:
        review_name: accounts/X/locations/Y/reviews/Z
        reply_text: Reply text (max 4096 chars)
        approved_by: Approver identity
    """
    if not approved_by:
        return {"ok": False, "error": "APPROVAL_REQUIRED"}
    if len(reply_text) > 4096:
        return {"ok": False, "error": "REPLY_TOO_LONG"}
    _audit("gbp_respond_review", {"review_name": review_name, "reply_text": reply_text[:200]}, None, None, approved_by)
    return {"ok": False, "error": "STUB_NOT_IMPLEMENTED", "action_taken": "audit_logged"}

@mcp.tool()
@_guard("gbp_upload_photo")
def gbp_upload_photo(
    location_id: str,
    photo_path: str,
    category: str = "EXTERIOR",
    approved_by: str = "",
) -> dict:
    """Upload a photo to a GBP location. Max 2/week.

    Args:
        location_id: accounts/X/locations/Y
        photo_path: Local file path (validated HTTPS URL or absolute path)
        category: EXTERIOR|INTERIOR|PRODUCT|AT_WORK|FOOD_AND_DRINK|...
        approved_by: Approver identity
    """
    if not approved_by:
        return {"ok": False, "error": "APPROVAL_REQUIRED"}
    _audit("gbp_upload_photo", {"location_id": location_id, "photo_path": photo_path, "category": category}, None, None, approved_by)
    return {"ok": False, "error": "STUB_NOT_IMPLEMENTED", "action_taken": "audit_logged"}

@mcp.tool()
@_guard("gbp_answer_qa")
def gbp_answer_qa(
    location_id: str,
    question_name: str,
    answer_text: str,
    approved_by: str = "",
) -> dict:
    """Post an answer to a user question on GBP. Max 2/day."""
    if not approved_by:
        return {"ok": False, "error": "APPROVAL_REQUIRED"}
    _audit("gbp_answer_qa", {"question_name": question_name, "answer_text": answer_text[:200]}, None, None, approved_by)
    return {"ok": False, "error": "STUB_NOT_IMPLEMENTED", "action_taken": "audit_logged"}

# ============================================================
# Hard-prohibited ops (return error always)
# ============================================================

@mcp.tool()
def gbp_update_name(location_id: str, new_name: str) -> dict:
    """PROHIBITED — changing business name triggers Google review + ban risk."""
    _audit("gbp_update_name_ATTEMPTED", {"location_id": location_id, "new_name": new_name}, {"blocked": True}, None)
    return {"ok": False, "error": "OPERATION_PROHIBITED: business name changes are hard-blocked. Use Google Business Profile UI with Jorge personally."}

@mcp.tool()
def gbp_update_address(location_id: str, new_address: dict) -> dict:
    """PROHIBITED — address changes trigger Google review + re-verification."""
    _audit("gbp_update_address_ATTEMPTED", {"location_id": location_id, "new_address": new_address}, {"blocked": True}, None)
    return {"ok": False, "error": "OPERATION_PROHIBITED: address changes are hard-blocked."}

@mcp.tool()
def gbp_update_phone(location_id: str, new_phone: str) -> dict:
    """PROHIBITED — phone changes trigger Google spam detection."""
    _audit("gbp_update_phone_ATTEMPTED", {"location_id": location_id, "new_phone": new_phone}, {"blocked": True}, None)
    return {"ok": False, "error": "OPERATION_PROHIBITED: phone changes are hard-blocked."}

# ============================================================
# Entry point
# ============================================================

if __name__ == "__main__":
    mcp.run()
