import pandas as pd
import urllib.request
import json

URL = "https://fhnolvqocysnjwgsdflq.supabase.co/rest/v1"
KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZobm9sdnFvY3lzbmp3Z3NkZmxxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4Mjc1NzQ5NSwiZXhwIjoyMDk4MzMzNDk1fQ.IO59t9zhCbyFi_nHNjMlrckHWJEdzYU4-5gCVbgWaog"
HEADERS = {
    "apikey": KEY,
    "Authorization": f"Bearer {KEY}",
    "Content-Type": "application/json",
    "Prefer": "return=representation"
}

def sb_request(method, path, data=None):
    url = f"{URL}/{path}"
    body = json.dumps(data).encode('utf-8') if data else None
    req = urllib.request.Request(url, data=body, headers=HEADERS, method=method)
    try:
        with urllib.request.urlopen(req) as response:
            return json.loads(response.read())
    except Exception as e:
        err = ""
        if hasattr(e, 'read'):
            err = e.read().decode('utf-8')
        print(f"  {method} {path} => ERROR: {e} {err}")
        return None

def sb_post(table, data): return sb_request("POST", table, data)
def sb_delete(table, params): return sb_request("DELETE", f"{table}?{params}")

def truncate(val, maxlen):
    if val and len(val) > maxlen:
        return val[:maxlen]
    return val

# ──────────────────────────────────────────────────────────────────────
# STEP 1: Clean old data
# ──────────────────────────────────────────────────────────────────────
print("STEP 1: Cleaning old data...")
sb_delete("contenido_slides", "id=gt.0")
sb_delete("contenido_detalle", "id=gt.0")
sb_delete("contenidos", "id=gt.0")
print("  Done.")

# ──────────────────────────────────────────────────────────────────────
# STEP 2: Import from the fixed Excel
# ──────────────────────────────────────────────────────────────────────
print("\nSTEP 2: Importing from 'Calendario_Julio_2026 fijo.xlsx'...")
df = pd.read_excel('Calendario_Julio_2026 fijo.xlsx', sheet_name='Launch Operating Planner_Julio')

posts = []
extra_data = []

for i, row in df.iterrows():
    fecha = row['Fecha']
    if pd.isna(fecha):
        continue
    
    def val(col, maxlen=None):
        v = row.get(col)
        if pd.isna(v):
            return None
        s = str(v).strip()
        if maxlen:
            s = truncate(s, maxlen)
        return s
    
    canal = val('Canal', 30)
    
    post = {
        "pestana_id": 1,
        "mes": "JULIO",
        "anio": 2026,
        "fecha": fecha.strftime('%Y-%m-%d'),
        "semana": val('Semana', 50),
        "formato": val('Serie Editorial', 50),
        "tema": val('Conversación'),
        "idea": val('Creencia'),
        "buyer": val('Comunidad', 50),
        "pilar": val('3P', 50),
        "etapa": val('Funnel', 10),
        "aspecto": val('Emoción', 50),
        "atributo": val('Objetivo', 80),
        "red_social": canal,
        "estado": val('Estado', 30) or 'Pendiente',
        "horario": val('KPI Principal', 50),
        "observaciones": val('Observaciones'),
        "creado_por": 1,
    }
    posts.append(post)
    
    # Build detalles based on canal logic:
    # "Meta" = Facebook + Instagram
    # "LinkedIn" = LinkedIn only
    # "LinkedIn + Meta" = LinkedIn + Facebook + Instagram
    headline = val('Headline')
    copy_text = val('Copy')
    cta = val('CTA')
    
    extra_data.append({
        "canal": canal,
        "headline": headline,
        "copy": copy_text,
        "cta": cta,
        "formato_visual": val('Formato'),
        "organico": val('Orgánico'),
        "pauta": val('Pauta'),
        "activo_fuente": val('Activo Fuente'),
        "reutilizacion": val('Reutilización'),
        "owner": val('Owner'),
    })

# Insert contenidos
result = sb_post("contenidos", posts)
if not result:
    print("  ❌ Failed to insert contenidos!")
    exit(1)

print(f"  ✅ Inserted {len(result)} contenidos.")

# ──────────────────────────────────────────────────────────────────────
# STEP 3: Insert contenido_detalle (key-value format)
# ──────────────────────────────────────────────────────────────────────
print("\nSTEP 3: Inserting detalles y slides...")
detalles = []
slides_rows = []

for idx, item in enumerate(result):
    cid = item['id']
    ex = extra_data[idx]
    canal = ex['canal'] or ''
    
    # Col O (Headline) → slide 1 en contenido_slides (sección COPY de la UI)
    if ex['headline']:
        slides_rows.append({
            "contenido_id": cid,
            "orden": 1,
            "texto": ex['headline']
        })

    # Col P (Copy) → HEADLINE section (copy_facebook / copy_instagram / copy_linkedin)
    # Meta = Facebook + Instagram | LinkedIn = LinkedIn only
    if ex['copy']:
        detalles.append({"contenido_id": cid, "campo": "copy", "valor": ex['copy']})
        if 'Meta' in canal:
            detalles.append({"contenido_id": cid, "campo": "copy_facebook", "valor": ex['copy']})
            detalles.append({"contenido_id": cid, "campo": "copy_instagram", "valor": ex['copy']})
        if 'LinkedIn' in canal:
            detalles.append({"contenido_id": cid, "campo": "copy_linkedin", "valor": ex['copy']})

    if ex['cta']:
        detalles.append({"contenido_id": cid, "campo": "cta", "valor": ex['cta']})

    # Store other extra fields
    for campo in ['formato_visual', 'organico', 'pauta', 'activo_fuente', 'reutilizacion', 'owner']:
        if ex[campo]:
            detalles.append({"contenido_id": cid, "campo": campo, "valor": ex[campo]})

det_result = sb_post("contenido_detalle", detalles)
if det_result:
    print(f"  \u2705 Inserted {len(det_result)} detalles.")
else:
    print("  \u26a0\ufe0f  Warning: Could not insert detalles.")

# Insert slides (Col O = Headline → slide 1 COPY section)
if slides_rows:
    sl_result = sb_post("contenido_slides", slides_rows)
    if sl_result:
        print(f"  \u2705 Inserted {len(sl_result)} slides (Headlines).")
    else:
        print("  \u26a0\ufe0f  Warning: Could not insert slides.")
else:
    print("  \u2139\ufe0f  No slides to insert.")

# ──────────────────────────────────────────────────────────────────────
# STEP 4: Verify
# ──────────────────────────────────────────────────────────────────────
print("\n── Verification ──")
for c in result[:5]:
    print(f"  {c['fecha']} | {c['formato']} | {c['tema']} | {c['pilar']} | {c['red_social']}")
print(f"  ... and {len(result)-5} more")

# Check copy distribution
meta_count = sum(1 for d in detalles if d['campo'] == 'copy_facebook')
linkedin_count = sum(1 for d in detalles if d['campo'] == 'copy_linkedin')
print(f"\n  Copy distribution:")
print(f"    Facebook copies: {meta_count}")
print(f"    Instagram copies: {sum(1 for d in detalles if d['campo'] == 'copy_instagram')}")
print(f"    LinkedIn copies: {linkedin_count}")

print("\n🎉 Import complete!")
