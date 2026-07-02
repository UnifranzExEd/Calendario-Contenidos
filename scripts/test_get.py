import urllib.request
import json

URL = "https://fhnolvqocysnjwgsdflq.supabase.co/rest/v1"
KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZobm9sdnFvY3lzbmp3Z3NkZmxxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4Mjc1NzQ5NSwiZXhwIjoyMDk4MzMzNDk1fQ.IO59t9zhCbyFi_nHNjMlrckHWJEdzYU4-5gCVbgWaog"
HEADERS = {
    "apikey": KEY,
    "Authorization": f"Bearer {KEY}",
    "Content-Type": "application/json"
}

select = "*,pestanas(slug,nombre,color),contenido_detalle(titulo_post,copy_facebook,copy_instagram,copy_tiktok),contenido_slides(numero_slide,texto,notas),metricas(alcance,impresiones,interacciones,clicks,guardados,compartidos,comentarios,reproducciones,fecha_registro),historial_estado(estado_anterior,estado_nuevo,usuario_id,comentario,created_at),contenido_imagenes(filename,tipo),contenido_hashtags(hashtags(id,tag,categoria,red_social))"
req = urllib.request.Request(f"{URL}/contenidos?id=eq.4&select={urllib.parse.quote(select)}&limit=1", headers=HEADERS, method="GET")
try:
    with urllib.request.urlopen(req) as response:
        print(response.read().decode('utf-8'))
except Exception as e:
    print(e)
    if hasattr(e, 'read'):
        print(e.read().decode('utf-8'))
