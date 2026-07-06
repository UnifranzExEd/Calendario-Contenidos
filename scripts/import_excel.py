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

def val(row, col, maxlen=None):
    """Safely extract a string value from a row, returns None if missing/NaN/dash."""
    v = row.get(col)
    if v is None or (isinstance(v, float) and str(v) == 'nan'):
        return None
    s = str(v).strip()
    if s in ('', '—', 'nan', 'None'):
        return None
    if maxlen:
        s = s[:maxlen]
    return s

# ──────────────────────────────────────────────────────────────────────
# STEP 1: Clean old data
# ──────────────────────────────────────────────────────────────────────
print("STEP 1: Cleaning old data...")
sb_delete("contenido_slides", "id=gt.0")
sb_delete("contenido_detalle", "id=gt.0")
sb_delete("contenidos", "id=gt.0")
print("  Done.")

# ──────────────────────────────────────────────────────────────────────
# STEP 2: Read Excel
# ──────────────────────────────────────────────────────────────────────
import os
_DIR = os.path.dirname(os.path.abspath(__file__))
EXCEL_FILE = os.path.join(_DIR, '..', 'Calendario_Julio_2026_ExEd.xlsx')
SHEET_NAME = 'Launch Operating Planner_Julio'

print(f"\nSTEP 2: Importing from '{EXCEL_FILE}'...")
df = pd.read_excel(EXCEL_FILE, sheet_name=SHEET_NAME)

posts = []
extra_data = []

for i, row in df.iterrows():
    fecha = row.get('Fecha')
    if fecha is None or (isinstance(fecha, float) and str(fecha) == 'nan'):
        continue

    # ── Map: Red → red_social (LinkedIn + Meta, Meta, LinkedIn)
    canal = val(row, 'Red', 50)

    post = {
        "pestana_id": 1,
        "mes": "JULIO",
        "anio": 2026,
        "fecha": fecha.strftime('%Y-%m-%d') if hasattr(fecha, 'strftime') else str(fecha)[:10],
        "semana": val(row, 'Semana', 50),
        "formato": val(row, 'Serie Editorial', 80),   # Serie Editorial → serie de contenido
        "tema": val(row, 'Conversación'),              # Conversación → tema
        "idea": val(row, 'Insight'),                   # Insight → idea
        "pilar": val(row, 'Tipo Pieza', 50),           # Tipo Pieza → pilar
        "formato_pieza": val(row, 'Formato', 30),      # Formato (4:5, 1:1…) → columna propia
        "ubicaciones": val(row, 'Ubicaciones', 100),   # Ubicaciones (Feed, Stories…) → columna propia
        "red_social": canal,
        "estado": 'Pendiente',
        "horario": val(row, 'KPI Principal', 80),      # KPI Principal → horario
        "observaciones": val(row, 'Observaciones'),
        "creado_por": 1,
    }
    posts.append(post)

    extra_data.append({
        "canal": canal,
        "copy": val(row, 'COPY'),                       # COPY → copy de redes
        "descripcion": val(row, 'Descripción'),         # Descripción → slide texto (cuerpo)
        "creative_notes": val(row, 'Creative Notes'),   # Creative Notes → notas para el PP
        "duracion": val(row, 'Duración', 50),           # Duración → campo extra en detalle
    })

# Insert contenidos
result = sb_post("contenidos", posts)
if not result:
    print("  ❌ Failed to insert contenidos!")
    exit(1)

print(f"  ✅ Inserted {len(result)} contenidos.")

# ──────────────────────────────────────────────────────────────────────
# STEP 3: Insert contenido_detalle + slides (con Creative Notes → notas)
# ──────────────────────────────────────────────────────────────────────
print("\nSTEP 3: Inserting detalles y slides...")
detalles = []
slides_rows = []

for idx, item in enumerate(result):
    cid = item['id']
    ex = extra_data[idx]
    canal = ex['canal'] or ''

    # ── COPY → copy por red social
    # Meta = Facebook + Instagram | LinkedIn = LinkedIn | LinkedIn + Meta = todas
    if ex['copy']:
        if 'Meta' in canal:
            detalles.append({"contenido_id": cid, "campo": "copy_facebook", "valor": ex['copy']})
            detalles.append({"contenido_id": cid, "campo": "copy_instagram", "valor": ex['copy']})
        if 'LinkedIn' in canal:
            detalles.append({"contenido_id": cid, "campo": "copy_linkedin", "valor": ex['copy']})
        # Guardar también como copy genérico
        detalles.append({"contenido_id": cid, "campo": "copy", "valor": ex['copy']})

    # ── Duración → campo extra en contenido_detalle
    if ex['duracion']:
        detalles.append({"contenido_id": cid, "campo": "duracion", "valor": ex['duracion']})

    # ── Slides: Descripción como texto principal del slide
    #            Creative Notes → notas para el PP
    descripcion = ex['descripcion']
    creative_notes = ex['creative_notes']

    if descripcion or creative_notes:
        slides_rows.append({
            "contenido_id": cid,
            "orden": 1,
            "texto": descripcion or '',          # Descripción → cuerpo del slide
            "notas": creative_notes or None      # Creative Notes → "Notas para el PP"
        })

# Insert detalles
if detalles:
    det_result = sb_post("contenido_detalle", detalles)
    if det_result:
        print(f"  ✅ Inserted {len(det_result)} detalles.")
    else:
        print("  ⚠️  Warning: Could not insert detalles.")
else:
    print("  ℹ️  No detalles to insert.")

# Insert slides
if slides_rows:
    sl_result = sb_post("contenido_slides", slides_rows)
    if sl_result:
        print(f"  ✅ Inserted {len(sl_result)} slides (Descripción + Creative Notes).")
    else:
        print("  ⚠️  Warning: Could not insert slides.")
else:
    print("  ℹ️  No slides to insert.")

# ──────────────────────────────────────────────────────────────────────
# STEP 4: Verify
# ──────────────────────────────────────────────────────────────────────
print("\n── Verification ──")
for c in result[:5]:
    print(f"  {c['fecha']} | {c.get('formato','')} | {c.get('tema','')} | {c.get('red_social','')} | {c.get('pilar','')}")
if len(result) > 5:
    print(f"  ... and {len(result)-5} more")

fb_count = sum(1 for d in detalles if d['campo'] == 'copy_facebook')
li_count = sum(1 for d in detalles if d['campo'] == 'copy_linkedin')
ig_count = sum(1 for d in detalles if d['campo'] == 'copy_instagram')
notes_count = sum(1 for s in slides_rows if s.get('notas'))
print(f"\n  Copy distribution:")
print(f"    Facebook: {fb_count} | Instagram: {ig_count} | LinkedIn: {li_count}")
print(f"  Slides with Creative Notes (notas PP): {notes_count}/{len(slides_rows)}")

print("\n🎉 Import complete!")
