# 🔍 ANÁLISIS DETALLADO: EL POLLING → EL CHISMOSO → CONTACTS

**Fecha de análisis:** 2026-04-10  
**Problema reportado:** Tracy table llena de datos, Contacts table vacía  
**Conclusión:** 3 bugs críticos impidiendo migración de datos

---

## 1. FLUJO ACTUAL ESPERADO (How it SHOULD work)

```
┌─────────────────────────────────────────────────────────────────┐
│ EL POLLING (Hostinger Cron, cada 5 min)                         │
├─────────────────────────────────────────────────────────────────┤
│ STEP 1: Lee Leads table                                         │
│   → Busca Stage='Review this Deal' Y Skip Trace Done=false      │
│   → Obtiene dirección, ciudad, estado, zip                      │
│                                                                 │
│ STEP 2-5: Interactúa con Tracerfy API                          │
│   → Crea Tracy record (status=pending)                          │
│   → Sube CSV a Tracerfy                                        │
│   → Poll queue hasta obtener resultados                         │
│   → Actualiza Tracy con datos encontrados                       │
│                                                                 │
│ STEP 6: ENVÍA POST a el_chismoso.php                           │
│   → URL: https://pinnaclegroupwi.com/Tools/el_chismoso.php     │
│   → Headers: X-Chismoso-Token: pinnacle2026                    │
│   → Body JSON: { record_id: "recXXXXXX" }  ← Tracy record ID    │
│                                                                 │
│ STEP 7: Actualiza Leads table                                  │
│   → Skip Trace Done = true                                      │
│   → Stage = 'To be Contacted'                                   │
└─────────────────────────────────────────────────────────────────┘
                            ↓ (HTTP POST)
┌─────────────────────────────────────────────────────────────────┐
│ EL CHISMOSO (Webhook receiver)                                  │
├─────────────────────────────────────────────────────────────────┤
│ STEP 1: Verifica token                                          │
│   → Lee header X-Chismoso-Token                                │
│   → Compara con CHISMOSO_TOKEN = 'pinnacle2026'                │
│                                                                 │
│ STEP 2: Parse payload JSON                                      │
│   → Extrae record_id (Tracy record ID)                         │
│                                                                 │
│ STEP 3: Fetch Tracy record desde Airtable                       │
│   → API GET: /tblXXXXXXXX/recXXXXXX                             │
│   → Obtiene: first_name, last_name, teléfonos, emails, etc.    │
│                                                                 │
│ STEP 4: Valida status='success'                                │
│   → Solo procesa si Tracy.status = 'success'                   │
│                                                                 │
│ STEP 5: Construye Contact fields desde Tracy                    │
│   → Full Name, Phone1/2/3, Email1/2, Mail Address, etc.        │
│                                                                 │
│ STEP 6: BUSCA Contact existente por Tracerfy ID                │
│   → Si existe: PATCH (actualiza)                                │
│   → Si no existe: POST (crea nuevo)                             │
│                                                                 │
│ STEP 7: Marca Tracy como pushed_to_contacts=true               │
│   → Airtable PATCH: Tracy.pushed_to_contacts = true            │
└─────────────────────────────────────────────────────────────────┘
                            ↓ (HTTP Response)
┌─────────────────────────────────────────────────────────────────┐
│ CONTACTS TABLE: Register (create o update)                      │
│ ✅ Full Name                                                     │
│ ✅ Phone1/2/3                                                    │
│ ✅ Email1/2                                                      │
│ ✅ Mail Address/City/State/Zip                                   │
│ ✅ Tracerfy ID                                                   │
│ ✅ Stage = 'To Be Contacted'                                     │
│ ✅ Lead Source = 'Skip Trace - Tracerfy'                        │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. PROBLEMAS IDENTIFICADOS

### 🔴 PROBLEMA #1: Table Names vs Table IDs en el_chismoso.php

**Ubicación:** Lines 22-23 de el_chismoso.php

```php
define('TABLE_TRACY',    'Tracy');        // ❌ INCORRECTO: es el NOMBRE
define('TABLE_CONTACTS', 'Contacts');     // ❌ INCORRECTO: es el NOMBRE
```

**Lo correcto debería ser:**
```php
define('TABLE_TRACY',    'tbl6CJm4kYspOuTDB');
define('TABLE_CONTACTS', 'tblacvw0Ss770x8l5');
```

**¿Por qué es crítico?**

Cuando el_chismoso hace:
```php
$tracyRecord = airtableGet(TABLE_TRACY, $tracyId);
```

Se convierte a:
```
GET https://api.airtable.com/v0/appfQbDA750Oihy9J/Tracy/recXXXXXX
```

Airtable API **a veces** tolera nombres de tabla, pero es **inconsistente** y puede retornar:
- Error 404 (tabla no encontrada)
- Error 401 (acceso denegado)
- Timeout o respuesta vacía

**IMPACTO:** El_chismoso no puede fetchear el Tracy record → línea 45 retorna error 404 → exit sin crear Contact

---

### 🔴 PROBLEMA #2: Array Filter rechazando tracerfy_id = 0 en el_polling.php

**Ubicación:** Line 454 de el_polling.php

```php
$tracyUpdate = array_filter([
    'status'             => 'success',
    'resultado'          => $resultSummary,
    'first_name'         => $ownerFirst,
    // ... más campos ...
    'tracerfy_id'        => intval($contact['id'] ?? 0),  // ← Puede ser 0
    'notas'              => 'Processed by el_polling.php',
], fn($v) => $v !== '' && $v !== null && $v !== 0);  // ❌ Rechaza $v === 0
```

**Qué sucede:**

1. Tracerfy devuelve un contact con `$contact['id']` (Tracerfy ID)
2. El_polling hace `intval($contact['id'] ?? 0)` → si es null, devuelve 0
3. Array_filter aplica `fn($v) => $v !== '' && $v !== null && $v !== 0`
4. Si tracerfy_id = 0, la condición es FALSE → **SE ELIMINA DEL ARRAY**
5. Tracy record se guarda **SIN tracerfy_id**

**Luego en el_chismoso:**
- Intenta buscar Contact por Tracerfy ID (línea 106)
- Como Tracy no tiene tracerfy_id, $tracerfyId = 0
- No encuentra nada
- Hace FALLBACK a Mail Address (línea 126-144)
- **Si Mail Address también está vacío O hay múltiples Contacts con mismo Mail Address, falla**

**IMPACTO:** Tracy record incompleto → búsqueda de Contact por ID falla → depende de fallback frágil

---

### 🔴 PROBLEMA #3: Array Filter rechazando phones = 0 en el_chismoso.php

**Ubicación:** Lines 98-100 de el_chismoso.php

```php
$contactFields = array_filter($contactFields, function($v) {
    return $v !== '' && $v !== null && $v !== 0;  // ❌ Rechaza $v === 0
});
```

**Qué sucede:**

1. El_polling calcula: `$phone1 = phoneToE164($contact['primary_phone'] ?? '')`
2. phoneToE164 devuelve string vacío '' si no hay teléfono válido
3. El_polling guarda en Tracy: `'primary_phone' => '+18594757302'` (string E.164)
4. El_chismoso lee: `phoneToInt($tf['primary_phone'] ?? '')` → 8594757302 (integer)
5. Array_filter se aplica a `$contactFields['Phone1'] = 8594757302`
6. Si por alguna razón phoneToInt retorna 0 (teléfono inválido), **se rechaza**
7. El Contact se crea **SIN teléfono**, aunque Tracy lo tiene

**IMPACTO:** Pérdida de datos de contacto → Contact vacío

---

## 3. MATRIZ DE FALLOS: DÓNDE SE PIERDE LA DATA

| Paso | Acción | Problema | Resultado |
|------|--------|----------|-----------|
| el_polling L303 | Crea Tracy record | ✅ OK | Tracy tabla llena |
| el_polling L350 | Upload CSV a Tracerfy | ✅ OK | Tracerfy retorna queue_id |
| el_polling L382 | Poll Tracerfy queue | ✅ OK | Obtiene contact data |
| el_polling L456 | PATCH Tracy con resultados | ❌ tracerfy_id=0 rechazado | Tracy sin ID |
| el_polling L463-478 | POST a el_chismoso | ✅ POST enviado | HTTP 200 recibido |
| **el_chismoso L44** | **airtableGet('Tracy', ...) ** | ❌ Nombre 'Tracy' vs ID | **GET retorna 404** |
| el_chismoso L45-49 | Verifica Tracy encontrado | ❌ FALLA | Retorna error, exit(404) |
| el_chismoso L154 | airtablePost('Contacts', ...) | ❌ Nunca se ejecuta | Contact NUNCA se crea |

---

## 4. EVIDENCIA EN LOS LOGS

**¿Dónde buscar pruebas?**

Archivo de log: `/opt/alex-bot/hostinger/tools/el_polling.log`

Búsquedas clave:
```bash
# Confirmación de que el_polling envía POST
grep "Notifying el_chismoso" el_polling.log

# Verificar HTTP response de el_chismoso
grep "el_chismoso.php" el_polling.log

# Buscar timeouts o errores
grep "ERROR\|timeout" el_polling.log

# Ver status de Tracy records
grep "Tracy record" el_polling.log
```

**¿Qué debería ver?**
```
[2026-04-10 10:15:32] Notifying el_chismoso.php for Tracy record recXXXXXX...
[2026-04-10 10:15:34] el_chismoso.php (200): {"success":true,"action":"created",...}
```

**¿Qué PROBABLEMENTE está viendo?**
```
[2026-04-10 10:15:32] Notifying el_chismoso.php for Tracy record recXXXXXX...
[2026-04-10 10:15:35] el_chismoso.php (404): {"error":"Tracy record not found"}
```

---

## 5. SOLUCIONES PROPUESTAS

### ✅ FIX #1: Actualizar Table IDs en el_chismoso.php

Reemplazar líneas 22-23:
```php
// ANTES:
define('TABLE_TRACY',    'Tracy');
define('TABLE_CONTACTS', 'Contacts');

// DESPUÉS:
define('TABLE_TRACY',    'tbl6CJm4kYspOuTDB');
define('TABLE_CONTACTS', 'tblacvw0Ss770x8l5');
```

**Impacto:** Crítico. Sin esto, el_chismoso no puede ni fetchear Tracy ni crear Contacts.

---

### ✅ FIX #2: NO rechazar tracerfy_id = 0 en el_polling.php

Cambiar línea 454:
```php
// ANTES (rechaza tracerfy_id si es 0):
], fn($v) => $v !== '' && $v !== null && $v !== 0);

// DESPUÉS (permite tracerfy_id = 0, pero rechaza strings vacíos):
], fn($v) => $v !== '' && $v !== null && $v !== false);
```

**¿Por qué?**
- Los teléfonos y mail address pueden ser legitimamente strings vacíos → rechazar
- tracerfy_id puede ser legitimamente 0 (si Tracerfy no devolvió ID) → aceptar
- Usar `$v !== false` permite 0 pero rechaza false

**Impacto:** Moderado. Permite que Tracy tenga tracerfy_id incluso si es 0, facilitando debugging.

---

### ✅ FIX #3: NO rechazar phones = 0 en el_chismoso.php

Cambiar línea 98-100:
```php
// ANTES:
$contactFields = array_filter($contactFields, function($v) {
    return $v !== '' && $v !== null && $v !== 0;
});

// DESPUÉS:
$contactFields = array_filter($contactFields, function($v) {
    return $v !== '' && $v !== null && $v !== false;
});
```

**Impacto:** Bajo. Las phones deberían venir como integer válido (10+ dígitos), nunca como 0 legítimo.

---

## 6. CAMBIOS EN DETALLE

### Archivo: hostinger/tools/el_chismoso.php

**Línea 22-23:** Reemplazar nombres con IDs
```diff
- define('TABLE_TRACY',    'Tracy');
- define('TABLE_CONTACTS', 'Contacts');
+ define('TABLE_TRACY',    'tbl6CJm4kYspOuTDB');
+ define('TABLE_CONTACTS', 'tblacvw0Ss770x8l5');
```

**Línea 98-100:** Cambiar condición de filtro
```diff
- $contactFields = array_filter($contactFields, function($v) {
-     return $v !== '' && $v !== null && $v !== 0;
- });
+ $contactFields = array_filter($contactFields, function($v) {
+     return $v !== '' && $v !== null && $v !== false;
+ });
```

### Archivo: hostinger/tools/el_polling.php

**Línea 454:** Cambiar condición de filtro
```diff
- ], fn($v) => $v !== '' && $v !== null && $v !== 0);
+ ], fn($v) => $v !== '' && $v !== null && $v !== false);
```

---

## 7. PLAN DE TESTING DESPUÉS DE FIX

```
1. Aplicar los 3 fixes en staging
2. Ejecutar el_polling.php manualmente para 1 lead de prueba
3. Verificar Tracy record: ✅ tiene tracerfy_id, ✅ status=success
4. Verificar el_polling.log: ✅ "el_chismoso.php (200): {"success":true...
5. Verificar Contacts table: ✅ nuevo contact creado con todos los datos
6. Si no: revisar HTTP response de el_chismoso en el_polling.log
```

---

## 8. COMPARACIÓN: ANTES vs DESPUÉS DEL FIX

| Métrica | ANTES (Actual) | DESPUÉS (Esperado) |
|---------|---|---|
| Tracy records creados | ✅ Sí | ✅ Sí |
| Tracy records con datos | ✅ Sí | ✅ Sí |
| El_chismoso fetchea Tracy | ❌ 404 error | ✅ Éxito |
| Contact records creados | ❌ 0 | ✅ 1+ |
| Contact records con teléfono | N/A | ✅ Sí |
| Contact records con email | N/A | ✅ Sí |
| Contact records con Mail Addr | N/A | ✅ Sí |

