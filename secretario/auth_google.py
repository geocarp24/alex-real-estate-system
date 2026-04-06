"""
Script de Autorización OAuth — Google Calendar
Ejecutar DESDE EL VPS via SSH:
  cd /opt/alex-bot && source venv/bin/activate
  python3 secretario/auth_google.py
"""
import json
import sys
from pathlib import Path

try:
    from google_auth_oauthlib.flow import InstalledAppFlow
except ImportError:
    print("ERROR: Instala dependencias:")
    print("  pip install google-auth google-auth-oauthlib google-api-python-client")
    sys.exit(1)

SCOPES    = ["https://www.googleapis.com/auth/calendar"]
CREDS_DIR = Path(__file__).parent / "google_creds"
CREDS_FILE = CREDS_DIR / "credentials.json"
TOKEN_FILE  = CREDS_DIR / "token.json"

CREDS_DIR.mkdir(parents=True, exist_ok=True)

if not CREDS_FILE.exists():
    print(f"ERROR: No encontré {CREDS_FILE}")
    sys.exit(1)

print("\n=== AUTORIZACIÓN GOOGLE CALENDAR — PINNACLE ALEX ===\n")

flow = InstalledAppFlow.from_client_secrets_file(str(CREDS_FILE), SCOPES)
flow.redirect_uri = "urn:ietf:wg:oauth:2.0:oob"

auth_url, _ = flow.authorization_url(prompt="consent", access_type="offline")

print("1. Abre este link en tu navegador (en tu computadora):")
print(f"\n   {auth_url}\n")
print("2. Inicia sesión con la cuenta Google de Pinnacle")
print("3. Autoriza el acceso al Calendario")
print("4. Google te mostrará un código — cópialo\n")

code = input("5. Pega el código aquí y presiona Enter: ").strip()

try:
    flow.fetch_token(code=code)
    creds = flow.credentials
    TOKEN_FILE.write_text(creds.to_json())
    print(f"\n✅ Token guardado en {TOKEN_FILE}")
    print("✅ Google Calendar conectado exitosamente!")

    # Verificar conexión
    from googleapiclient.discovery import build
    service = build("calendar", "v3", credentials=creds)
    result  = service.calendarList().list().execute()
    cals    = result.get("items", [])
    print(f"\nCalendarios accesibles: {len(cals)}")
    for c in cals:
        print(f"  - {c.get('summary', '?')} ({c.get('id', '?')})")

except Exception as e:
    print(f"\n❌ Error: {e}")
    print("Vuelve a intentarlo o contacta a ALEX.")
    sys.exit(1)
