import requests
import csv
import json

# === CONFIGURACIÓN ===
TRACERFY_API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0b2tlbl90eXBlIjoiYWNjZXNzIiwiZXhwIjozMjQyNTY2NDc4LCJpYXQiOjE3NzM3NjY0NzgsImp0aSI6IjFkNmMwZTc5YjRjZDRmZGY5YTUzNmQ4NTAzYjFiNTY2IiwidXNlcl9pZCI6NTc2MX0.P7H9nO6KFP-2UfSDl33RX4yxOislHV-v7V2vbPlJIWg"
AIRTABLE_TOKEN = "patQXGBEGdmbhGRfi.81e226fee4638f95bba27a57003465dd930d9e977d8b4dc7ac372c1b60dd087b"
AIRTABLE_BASE_ID = "appfQbDA750Oihy9J"
AIRTABLE_CONTACTS_TABLE = "tblacvw0Ss770x8l5"

# === PASO 1: Enviar CSV a Tracerfy ===
def send_to_tracerfy(csv_path):
    url = "https://api.tracerfy.com/v1/skiptrace/upload"
    headers = {"Authorization": f"Bearer {TRACERFY_API_KEY}"}
    files = {"file": open(csv_path, "rb")}
    response = requests.post(url, headers=headers, files=files)
    response.raise_for_status()
    return response.json()

# === PASO 2: Procesar respuesta de Tracerfy ===
def extract_contacts(tracerfy_response):
    # Ajusta según el formato real de la respuesta de Tracerfy
    contacts = []
    for result in tracerfy_response.get("results", []):
        contact = {
            "Full Name": result.get("name"),
            "Phone": result.get("phone"),
            "Email": result.get("email"),
            "Category": "Skip Trace",
            "Owner Address": result.get("address"),
            "City": result.get("city"),
            "State": result.get("state"),
            "Zip": result.get("zip")
        }
        contacts.append(contact)
    return contacts

# === PASO 3: Registrar contactos en Airtable ===
def add_contact_to_airtable(contact):
    url = f"https://api.airtable.com/v0/{AIRTABLE_BASE_ID}/{AIRTABLE_CONTACTS_TABLE}"
    headers = {
        "Authorization": f"Bearer {AIRTABLE_TOKEN}",
        "Content-Type": "application/json"
    }
    data = {"fields": contact}
    response = requests.post(url, headers=headers, data=json.dumps(data))
    response.raise_for_status()
    return response.json()

if __name__ == "__main__":
    csv_path = "tracy_trace_input.csv"
    print("Enviando dirección a Tracerfy...")
    tracerfy_response = send_to_tracerfy(csv_path)
    print("Respuesta de Tracerfy recibida.")
    contacts = extract_contacts(tracerfy_response)
    print(f"Contactos encontrados: {len(contacts)}")
    for contact in contacts:
        print(f"Agregando contacto: {contact['Full Name']}...")
        result = add_contact_to_airtable(contact)
        print(f"Contacto agregado con ID: {result.get('id')}")
    print("Proceso de skip trace y registro en Airtable completado.")
