import re
import sys

def convert():
    with open('if0_42131777_redes.sql', 'r', encoding='utf-8') as f:
        lines = f.readlines()

    out_lines = []
    
    # Tables we want to keep the INSERT data for
    config_tables = ['dropdown_opciones', 'pestanas', 'pestana_campos']
    
    skip = False
    in_create_table = False
    
    for line in lines:
        if line.startswith('/*!') or line.startswith('SET') or line.startswith('--'):
            continue
            
        # Ignore INSERTs unless they are for config tables
        if line.startswith('INSERT INTO'):
            table_name = re.search(r'INSERT INTO `?([a-zA-Z0-9_]+)`?', line)
            if table_name and table_name.group(1) not in config_tables:
                skip = True
                continue
                
        if skip:
            if line.strip().endswith(';'):
                skip = False
            continue

        # Convert CREATE TABLE syntax
        if line.startswith('CREATE TABLE'):
            line = line.replace('`', '"')
            in_create_table = True
            
        if in_create_table:
            # End of CREATE TABLE
            if line.strip().startswith(') ENGINE='):
                line = ");\n"
                in_create_table = False
            else:
                line = line.replace('`', '"')
                
                # Make id SERIAL PRIMARY KEY
                if line.strip().startswith('"id"'):
                    line = '  "id" SERIAL PRIMARY KEY,\n'
                    out_lines.append(line)
                    continue

                # Replace data types
                line = re.sub(r'int\(\d+\)', 'INTEGER', line)
                line = re.sub(r'tinyint\(\d+\)', 'BOOLEAN', line)
                line = line.replace('datetime', 'TIMESTAMP')
                line = line.replace('current_timestamp() ON UPDATE current_timestamp()', 'CURRENT_TIMESTAMP')
                line = line.replace('current_timestamp()', 'CURRENT_TIMESTAMP')
                
                # Remove extra commas at the end if the next line is a constraint we remove
                # Actually, simpler: we'll handle PRIMARY KEY inline if possible. 
                # Let's remove PRIMARY KEY (id) and KEY constraints
                if 'PRIMARY KEY' in line and 'SERIAL PRIMARY KEY' not in line:
                    if '"id"' in line:
                        line = ""
                if line.strip().startswith('KEY '):
                    line = ""
                    
        # MySQL boolean representation in inserts: 1/0 to true/false? 
        # Postgres accepts 1/0 for boolean sometimes, but better to leave it.
        # Postgres doesn't use backticks
        line = line.replace('`', '"')
        
        # Don't output ALTER TABLE since we already handled SERIAL
        if line.startswith('ALTER TABLE'):
            skip = True
            continue
            
        out_lines.append(line)

    # Clean up trailing commas before closing parenthesis
    cleaned_lines = []
    for i, line in enumerate(out_lines):
        if line.strip() == '':
            continue
        if line.strip() == ');':
            # Check previous line
            if cleaned_lines and cleaned_lines[-1].strip().endswith(','):
                cleaned_lines[-1] = cleaned_lines[-1].rstrip()[:-1] + '\n'
        cleaned_lines.append(line)

    with open('schema.sql', 'w', encoding='utf-8') as f:
        f.writelines(cleaned_lines)

if __name__ == "__main__":
    convert()
