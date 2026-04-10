# 🔗 Integración de Smart Escalation en Agentes

## Dónde está el flujo actual

En `/opt/alex-bot/CLAUDE.md`, el flujo es:

```
1. Lee memoria_ALex.md
2. Lanza El Scout (Agent tool)
3. Lanza El Matemático (Agent tool)
4. Lanza El Fact-Checker (Agent tool)
5. Consolida reporte
6. Escribe en memoria_ALex.md
```

---

## Cómo integrar Smart Escalation

### **OPCIÓN A: Integración mínima (Recomendado)**

Solo cambiar la parte de "Lanza El Scout":

```python
# ANTES (sin escalamiento logging)
Agent(
    name="scout",
    model="claude-sonnet-4-6",  # ← Modelo fijo
    prompt=f"{scout_prompt}\n\n{property_data}"
)

# DESPUÉS (con escalamiento + logging)
from agents.model_assignment import get_model_with_escalation_logging

# Obtener modelo con escalamiento automático Y logging
model = get_model_with_escalation_logging(
    agent_name="scout",
    prompt=f"{scout_prompt}\n\n{property_data}",  # ← Analiza complejidad
    task_id=f"scout_{property_id}_{timestamp}",     # ← ID único
    log_to_airtable=True  # ← Registra en Airtable
)

Agent(
    name="scout",
    model=model,  # ← Usa modelo inteligente
    prompt=f"{scout_prompt}\n\n{property_data}"
)
```

---

## Patrón para TODOS los agentes

### **El Scout**
```python
model = get_model_with_escalation_logging(
    agent_name="scout",
    prompt=property_data,
    task_id=f"scout_{deal_id}_{timestamp}",
    log_to_airtable=True
)
Agent(name="scout", model=model, prompt=property_data)
```

### **El Matemático**
```python
model = get_model_with_escalation_logging(
    agent_name="matematico",
    prompt=financial_data,
    task_id=f"math_{deal_id}_{timestamp}",
    log_to_airtable=True
)
Agent(name="matematico", model=model, prompt=financial_data)
```

### **El Fact-Checker**
```python
model = get_model_with_escalation_logging(
    agent_name="fact-checker",
    prompt=combined_data,
    task_id=f"check_{deal_id}_{timestamp}",
    log_to_airtable=True
)
Agent(name="fact-checker", model=model, prompt=combined_data)
```

### **Tracy (Skip Tracing)**
```python
model = get_model_with_escalation_logging(
    agent_name="tracy",
    prompt=address_data,
    task_id=f"tracy_{address}_{timestamp}",
    log_to_airtable=True
)
Agent(name="tracy", model=model, prompt=address_data)
```

### **El Creativo/Director (Social Media)**
```python
model = get_model_with_escalation_logging(
    agent_name="creativo",
    prompt=content_brief,
    task_id=f"creative_{content_id}_{timestamp}",
    log_to_airtable=True
)
Agent(name="creativo", model=model, prompt=content_brief)
```

### **El Programador (Code)**
```python
model = get_model_with_escalation_logging(
    agent_name="programador",
    prompt=code_request,
    task_id=f"code_{feature}_{timestamp}",
    log_to_airtable=True
)
Agent(name="programador", model=model, prompt=code_request)
```

---

## Imports necesarios

En la parte superior del archivo donde invoques agentes:

```python
from agents.model_assignment import get_model_with_escalation_logging
from datetime import datetime

# Si necesitas timestamp
timestamp = datetime.utcnow().isoformat()
```

---

## Flujo mejorado con Smart Escalation

```
1. Lee memoria_ALex.md
2. ┌─ Lanza El Scout
   └─ Analiza complejidad → Escala si necesario
   └─ Registra en Airtable si escaló
3. ┌─ Lanza El Matemático
   └─ Analiza complejidad → Escala si necesario
   └─ Registra en Airtable si escaló
4. ┌─ Lanza El Fact-Checker
   └─ Analiza complejidad → Escala si necesario
   └─ Registra en Airtable si escaló
5. Consolida reporte
6. Escribe en memoria_ALex.md
7. Dashboard muestra: ¿Cuántos escalamientos? ¿Costo total?
```

---

## Qué sucede automáticamente

```
Usuario pide analizar propiedad en Milwaukee
        ↓
Scout inicia con SONNET
        ↓
get_model_with_escalation_logging() analiza:
  - ¿Contiene "complex", "unusual"?
  - ¿Más de 8000 tokens?
  - ¿Múltiples scenarios?
        ↓
SI → Escala a OPUS automáticamente
     Registra en Airtable:
     - scout_milwaukee_001
     - initial_model: sonnet
     - final_model: opus
     - reason: tokens > 8000
     - cost: $0.30
        ↓
NO → Mantiene SONNET
     No registra (no hay escalamiento)
```

---

## Monitoreo

Cada tarea generará un registro en Airtable si se escaló:

```
scout_deal_2026_001   │ scout  │ sonnet → opus │ tokens > 8000
math_deal_2026_001    │ math   │ sonnet → opus │ confidence < 6
check_deal_2026_001   │ check  │ sonnet → sonnet │ (no escaló)
tracy_address_001     │ tracy  │ haiku → haiku │ (no escaló)
```

**Costo semanal:**
- Tareas sin escalamiento: ~$100
- Tareas con escalamiento: ~$200
- **Total: ~$300 (vs $600 si todo fuera Opus)**

---

## Cambios mínimos requeridos

Si solo quieres implementar en UN agente (ej: Scout):

1. Importar al inicio:
```python
from agents.model_assignment import get_model_with_escalation_logging
from datetime import datetime
```

2. Cambiar la invocación:
```python
# Una línea para obtener modelo inteligente
model = get_model_with_escalation_logging(
    agent_name="scout",
    prompt=property_data,
    task_id=f"scout_{property_id}_{datetime.utcnow().isoformat()}",
    log_to_airtable=True
)

# Luego usar como siempre
Agent(name="scout", model=model, prompt=property_data)
```

---

## Casos de uso

### Caso 1: Análisis simple de propiedad
```
Input: "Find data for 123 Main St"
Scout → get_model() → SONNET
Cost: $0.08
No escalamiento
```

### Caso 2: Análisis complejo con patrones inusuales
```
Input: "Complex market analysis with unusual patterns and edge cases"
Scout → get_model() → SONNET → Detecta palabras complejas
→ ESCALA A OPUS automáticamente
→ Registra en Airtable
Cost: $0.30
```

### Caso 3: Debugging de código
```
Input: "Debug authentication error in production"
Programador → get_model() → SONNET → Detecta "debug", "production"
→ ESCALA A OPUS automáticamente
Cost: $0.30
```

---

## Testing antes de producción

1. Crear tabla en Airtable (ver SETUP_AIRTABLE_METRICS.md)
2. Probar logger:
   ```bash
   python3 agents/airtable_escalation_logger.py
   ```
3. Cambiar UN agente (ej: Tracy)
4. Invocar con tarea simple: debe usar HAIKU
5. Invocar con tarea compleja: debe escalar a SONNET
6. Verificar registros en Airtable

---

## Status de Implementación

✅ **COMPLETADO EN TELEGRAM BOT:**
- El Scout: escalamiento habilitado
- El Matemático: escalamiento habilitado
- El Fact-Checker: escalamiento habilitado
- El Creativo: escalamiento habilitado
- El Director: escalamiento habilitado
- Tabla Airtable: creada y conectada
- Logger: testado y funcionando

✅ **PARA CLAUDE CODE:**
- Sistema de escalamiento está listo en agents/model_assignment.py
- Cuando ALEX invoque agentes desde Claude Code usando Agent tool, debe pasar modelo inteligente
- Usar get_model_with_escalation_logging() con el Agent tool

### Próximas optimizaciones:
- [ ] Integrar Tracy con escalamiento (actualmente solo usa Haiku fijo)
- [ ] Integrar El Programador (solo orquesta Blotato, no usa Claude)
- [ ] Monitoreo semanal de escalamientos en Airtable dashboard
- [ ] Ajuste de keywords de complejidad si es necesario

---

**Status:** ✅ PRODUCCIÓN — Sistema operativo en Telegram y listo en Claude Code
