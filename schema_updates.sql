-- ================================================
-- UNIFRANZ Calendar - Schema Updates
-- Run this in your Supabase SQL Editor
-- ================================================

-- 1. Add 'prioridad' to contenidos
ALTER TABLE contenidos ADD COLUMN IF NOT EXISTS prioridad VARCHAR(20) DEFAULT 'Media';

-- 2. Create microtareas table
CREATE TABLE IF NOT EXISTS microtareas (
  id SERIAL PRIMARY KEY,
  titulo VARCHAR(255) NOT NULL,
  descripcion TEXT DEFAULT NULL,
  responsable_id INTEGER DEFAULT NULL,
  proyecto_id INTEGER DEFAULT NULL,
  fecha_entrega DATE DEFAULT NULL,
  prioridad VARCHAR(20) DEFAULT 'Media',
  estado VARCHAR(50) DEFAULT 'Pendiente',
  creado_por INTEGER NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP DEFAULT NULL
);

-- 3. Create microtareas_items table
CREATE TABLE IF NOT EXISTS microtareas_items (
  id SERIAL PRIMARY KEY,
  microtarea_id INTEGER NOT NULL,
  texto TEXT NOT NULL,
  completada SMALLINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Verify/Create notificaciones table if missing
CREATE TABLE IF NOT EXISTS notificaciones (
  id SERIAL PRIMARY KEY,
  usuario_id INTEGER NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  mensaje TEXT NOT NULL,
  contenido_id INTEGER DEFAULT NULL,
  leida SMALLINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Seed Prioridad dropdown
INSERT INTO dropdown_opciones (grupo, valor, color) VALUES 
('prioridad', 'Alta', '#ef4444'),
('prioridad', 'Media', '#f59e0b'),
('prioridad', 'Baja', '#3b82f6')
ON CONFLICT DO NOTHING;
