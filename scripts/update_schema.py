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

def sb_delete(table, filter_str):
    req = urllib.request.Request(f"{URL}/{table}?{filter_str}", headers=HEADERS, method="DELETE")
    try:
        with urllib.request.urlopen(req) as response:
            return json.loads(response.read())
    except Exception as e:
        pass

def sb_post(table, data):
    req = urllib.request.Request(f"{URL}/{table}", data=json.dumps(data).encode('utf-8'), headers=HEADERS, method="POST")
    try:
        with urllib.request.urlopen(req) as response:
            return json.loads(response.read())
    except Exception as e:
        print("Post error", e)
        if hasattr(e, 'read'):
            print(e.read().decode('utf-8'))

sb_delete("pestana_campos", "pestana_id=in.(1,2)")

new_campos = []
for pid in [1, 2]:
    campos = [
        {"pestana_id": pid, "nombre_campo": "fecha", "etiqueta": "FECHA", "tipo": "fecha", "opciones": None, "orden": 1, "visible": 1, "ancho": "120px"},
        {"pestana_id": pid, "nombre_campo": "red_social", "etiqueta": "CANAL", "tipo": "dropdown", "opciones": "red_social", "orden": 2, "visible": 1, "ancho": "130px"},
        {"pestana_id": pid, "nombre_campo": "formato", "etiqueta": "SERIE EDITORIAL", "tipo": "dropdown", "opciones": "formato", "orden": 3, "visible": 1, "ancho": "140px"},
        {"pestana_id": pid, "nombre_campo": "tema", "etiqueta": "PIEZA", "tipo": "textarea", "opciones": None, "orden": 4, "visible": 1, "ancho": "250px"},
        {"pestana_id": pid, "nombre_campo": "pilar", "etiqueta": "3P", "tipo": "dropdown", "opciones": "pilar", "orden": 5, "visible": 1, "ancho": "130px"},
        {"pestana_id": pid, "nombre_campo": "atributo", "etiqueta": "OBJETIVO", "tipo": "dropdown", "opciones": "atributo", "orden": 6, "visible": 1, "ancho": "160px"},
        {"pestana_id": pid, "nombre_campo": "observaciones", "etiqueta": "OBSERVACIONES", "tipo": "textarea", "opciones": None, "orden": 7, "visible": 1, "ancho": "200px"},
        {"pestana_id": pid, "nombre_campo": "estado", "etiqueta": "ESTADO", "tipo": "dropdown", "opciones": "estado", "orden": 8, "visible": 1, "ancho": "130px"}
    ]
    new_campos.extend(campos)

res = sb_post("pestana_campos", new_campos)
if res:
    print("Schema updated successfully.")
