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

# DB VARCHAR LIMITS (from schema.sql)
# etapa varchar(10), formato varchar(50), buyer varchar(50),
# aspecto varchar(50), pilar varchar(50), red_social varchar(30),
# atributo varchar(80), horario varchar(50), semana varchar(50)

def truncate(val, maxlen):
    if val and len(val) > maxlen:
        return val[:maxlen]
    return val

# ──────────────────────────────────────────────────────────────────────
# Import from correct Excel sheet
# ──────────────────────────────────────────────────────────────────────
print("Importing from 'Launch Operating Planner_Julio'...")
df = pd.read_excel('Calendario_Julio_2026_ExEd (1).xlsx', sheet_name='Launch Operating Planner_Julio')

posts = []
detalles_raw = []

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
    
    post = {
        "pestana_id": 1,
        "mes": "JULIO",
        "anio": 2026,
        "fecha": fecha.strftime('%Y-%m-%d'),
        "semana": val('Semana', 50),
        "formato": val('Serie Editorial', 50),      # Serie Editorial
        "tema": val('Conversación'),                 # Conversación (text, no limit)
        "idea": val('Creencia'),                     # Creencia (text, no limit)
        "buyer": val('Comunidad', 50),               # Comunidad
        "pilar": val('3P', 50),                      # 3P
        "etapa": val('Funnel', 10),                  # Funnel — TRUNCATED to 10
        "aspecto": val('Emoción', 50),               # Emoción
        "atributo": val('Objetivo', 80),             # Objetivo
        "red_social": val('Canal', 30),              # Canal
        "estado": val('Estado', 30) or 'Pendiente',  # Estado
        "horario": val('KPI Principal', 50),         # KPI Principal
        "observaciones": val('Observaciones'),       # Observaciones
        "creado_por": 1,
    }
    
    posts.append(post)
    detalles_raw.append({
        "titulo_post": val('Headline', 500),
        "copy_facebook": val('Copy'),
        "copy_instagram": val('CTA'),
        "copy_tiktok": None,
    })

# Check etapa truncation issues
print("\n  Funnel values after truncation:")
funnels = set(p['etapa'] for p in posts if p['etapa'])
for f in funnels:
    print(f"    '{f}' ({len(f)} chars)")

# Fix: Consideration -> Consider, Conversion -> Convert (or use abbreviations)
# Actually let's use smart abbreviations
FUNNEL_MAP = {
    "Awareness": "Awareness",       # 9 chars - fits!
    "Consideration": "Consider",     # 8 chars
    "Conversion": "Conversion",      # 10 chars - fits!
}
for p in posts:
    if p['etapa'] in FUNNEL_MAP:
        p['etapa'] = FUNNEL_MAP[p['etapa']]

print("\n  Funnel values after smart mapping:")
funnels = set(p['etapa'] for p in posts if p['etapa'])
for f in funnels:
    print(f"    '{f}' ({len(f)} chars)")

# Insert posts
result = sb_post("contenidos", posts)
if result:
    print(f"\n  ✅ Inserted {len(result)} contenidos.")
    
    # Insert detalles
    detalles = []
    for idx, item in enumerate(result):
        det = detalles_raw[idx].copy()
        det["contenido_id"] = item['id']
        detalles.append(det)
    
    det_result = sb_post("contenido_detalle", detalles)
    if det_result:
        print(f"  ✅ Inserted {len(det_result)} detalles (Headline/Copy/CTA).")
    else:
        print("  ⚠️  Warning: Could not insert detalles.")
else:
    print("  ❌ ERROR: Failed to insert contenidos!")

print("\n🎉 Import complete!")
