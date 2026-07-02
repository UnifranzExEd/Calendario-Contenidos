import pandas as pd
import json

df = pd.read_excel('Calendario_Julio_2026_ExEd (1).xlsx')
df = df.dropna(how='all')

# Process logic based on previous inspection
rows = []
for i in range(1, len(df), 7):
    # This expects each entry to span 7 rows: 
    # Row 1: day numbers
    # Row 2: Canal
    # Row 3: Tipo
    # Row 4: Pieza
    # Row 5: 3P
    # Row 6: Objetivo
    # Row 7: Observaciones
    if i+6 >= len(df):
        break
    
    days = df.iloc[i].values[1:] # M to S
    canal = df.iloc[i+1].values[1:]
    tipo = df.iloc[i+2].values[1:]
    pieza = df.iloc[i+3].values[1:]
    tres_p = df.iloc[i+4].values[1:]
    objetivo = df.iloc[i+5].values[1:]
    obs = df.iloc[i+6].values[1:]

    for col_idx in range(len(days)):
        day = days[col_idx]
        if pd.notna(day) and pd.notna(canal[col_idx]):
            rows.append({
                'day': day,
                'canal': canal[col_idx],
                'tipo': tipo[col_idx],
                'pieza': pieza[col_idx],
                '3p': tres_p[col_idx],
                'objetivo': objetivo[col_idx],
                'observaciones': obs[col_idx]
            })

print(json.dumps(rows[:2], indent=2, ensure_ascii=False))
