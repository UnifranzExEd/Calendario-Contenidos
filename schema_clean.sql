-- ================================================
-- UNIFRANZ Calendar - PostgreSQL Schema (Clean)
-- ================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- DROP existing tables
DROP TABLE IF EXISTS notificaciones CASCADE;
DROP TABLE IF EXISTS metricas CASCADE;
DROP TABLE IF EXISTS historial_estado CASCADE;
DROP TABLE IF EXISTS contenido_hashtags CASCADE;
DROP TABLE IF EXISTS contenido_imagenes CASCADE;
DROP TABLE IF EXISTS contenido_slides CASCADE;
DROP TABLE IF EXISTS contenido_detalle CASCADE;
DROP TABLE IF EXISTS contenidos CASCADE;
DROP TABLE IF EXISTS hashtags CASCADE;
DROP TABLE IF EXISTS pestana_campos CASCADE;
DROP TABLE IF EXISTS pestanas CASCADE;
DROP TABLE IF EXISTS dropdown_opciones CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;

-- ============ TABLE: usuarios ============
CREATE TABLE usuarios (
  id SERIAL PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol VARCHAR(30) NOT NULL DEFAULT 'community',
  avatar VARCHAR(255) DEFAULT NULL,
  activo SMALLINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: pestanas ============
CREATE TABLE pestanas (
  id SERIAL PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  slug VARCHAR(100) DEFAULT NULL,
  color VARCHAR(20) DEFAULT '#6366f1',
  orden INTEGER DEFAULT 0,
  activo SMALLINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: pestana_campos ============
CREATE TABLE pestana_campos (
  id SERIAL PRIMARY KEY,
  pestana_id INTEGER NOT NULL,
  nombre_campo VARCHAR(100) NOT NULL,
  etiqueta VARCHAR(100) NOT NULL,
  tipo VARCHAR(50) DEFAULT 'text',
  requerido SMALLINT DEFAULT 0,
  visible SMALLINT DEFAULT 1,
  orden INTEGER DEFAULT 0,
  opciones TEXT DEFAULT NULL,
  ancho VARCHAR(20) DEFAULT 'full',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: contenidos ============
CREATE TABLE contenidos (
  id SERIAL PRIMARY KEY,
  pestana_id INTEGER NOT NULL,
  semana VARCHAR(50) DEFAULT NULL,
  mes VARCHAR(20) DEFAULT NULL,
  anio INTEGER DEFAULT 2026,
  fecha DATE DEFAULT NULL,
  buyer VARCHAR(50) DEFAULT NULL,
  pilar VARCHAR(50) DEFAULT NULL,
  atributo VARCHAR(80) DEFAULT NULL,
  etapa VARCHAR(10) DEFAULT NULL,
  aspecto VARCHAR(50) DEFAULT NULL,
  carrera VARCHAR(10) DEFAULT NULL,
  tema TEXT DEFAULT NULL,
  idea TEXT DEFAULT NULL,
  red_social VARCHAR(30) DEFAULT NULL,
  estado VARCHAR(30) DEFAULT 'En elaboración',
  error_ortografico SMALLINT DEFAULT 0,
  formato VARCHAR(50) DEFAULT NULL,
  horario VARCHAR(50) DEFAULT NULL,
  enlace_contenido TEXT DEFAULT NULL,
  enlace_publicado TEXT DEFAULT NULL,
  observaciones TEXT DEFAULT NULL,
  enviar_postproduccion SMALLINT DEFAULT 0,
  espectadores INTEGER DEFAULT NULL,
  interacciones INTEGER DEFAULT NULL,
  postproductor_id INTEGER DEFAULT NULL,
  creado_por INTEGER NOT NULL,
  actualizado_por INTEGER DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  enlace_diseno TEXT DEFAULT NULL,
  deleted_at TIMESTAMP DEFAULT NULL,
  error_ortografico_detalle TEXT DEFAULT NULL
);

-- ============ TABLE: contenido_detalle ============
CREATE TABLE contenido_detalle (
  id SERIAL PRIMARY KEY,
  contenido_id INTEGER NOT NULL,
  campo VARCHAR(100) NOT NULL,
  valor TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: contenido_hashtags ============
CREATE TABLE contenido_hashtags (
  contenido_id INTEGER NOT NULL,
  hashtag_id INTEGER NOT NULL,
  PRIMARY KEY (contenido_id, hashtag_id)
);

-- ============ TABLE: contenido_imagenes ============
CREATE TABLE contenido_imagenes (
  id SERIAL PRIMARY KEY,
  contenido_id INTEGER DEFAULT NULL,
  nombre_original VARCHAR(255) DEFAULT NULL,
  nombre_guardado VARCHAR(255) DEFAULT NULL,
  ruta VARCHAR(500) DEFAULT NULL,
  tipo VARCHAR(50) DEFAULT NULL,
  tamano INTEGER DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: contenido_slides ============
CREATE TABLE contenido_slides (
  id SERIAL PRIMARY KEY,
  contenido_id INTEGER NOT NULL,
  texto TEXT DEFAULT NULL,
  orden INTEGER DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: dropdown_opciones ============
CREATE TABLE dropdown_opciones (
  id SERIAL PRIMARY KEY,
  campo VARCHAR(100) NOT NULL,
  valor VARCHAR(200) NOT NULL,
  color VARCHAR(20) DEFAULT NULL,
  orden INTEGER DEFAULT 0,
  activo SMALLINT DEFAULT 1
);

-- ============ TABLE: hashtags ============
CREATE TABLE hashtags (
  id SERIAL PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  veces_usado INTEGER DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: historial_estado ============
CREATE TABLE historial_estado (
  id SERIAL PRIMARY KEY,
  contenido_id INTEGER NOT NULL,
  estado_anterior VARCHAR(30) DEFAULT NULL,
  estado_nuevo VARCHAR(30) DEFAULT NULL,
  usuario_id INTEGER DEFAULT NULL,
  comentario TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: metricas ============
CREATE TABLE metricas (
  id SERIAL PRIMARY KEY,
  contenido_id INTEGER NOT NULL,
  espectadores INTEGER DEFAULT NULL,
  interacciones INTEGER DEFAULT NULL,
  usuario_id INTEGER DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ TABLE: notificaciones ============
CREATE TABLE notificaciones (
  id SERIAL PRIMARY KEY,
  usuario_id INTEGER NOT NULL,
  tipo VARCHAR(50) DEFAULT NULL,
  mensaje TEXT DEFAULT NULL,
  contenido_id INTEGER DEFAULT NULL,
  leida SMALLINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ SEED: pestanas (tabs) ============
INSERT INTO pestanas (nombre, slug, color, orden, activo) VALUES
('Orgánicos', 'organicos', '#a42328', 1, 1),
('Pagados', 'pagados', '#898b8e', 2, 1);

-- ============ SEED: dropdown_opciones ============
INSERT INTO dropdown_opciones (campo, valor, color, orden, activo) VALUES
('red_social', 'FACEBOOK', '#1877F2', 1, 1),
('red_social', 'INSTAGRAM', '#E4405F', 2, 1),
('red_social', 'TIK TOK', '#000000', 3, 1),
('red_social', 'YOUTUBE', '#FF0000', 4, 1),
('red_social', 'LINKEDIN', '#0A66C2', 5, 1),
('red_social', 'WHATSAPP', '#25D366', 6, 1),
('estado', 'En elaboración', '#6b7280', 1, 1),
('estado', 'Redacción', '#f59e0b', 2, 1),
('estado', 'En revisión', '#3b82f6', 3, 1),
('estado', 'Producción', '#8b5cf6', 4, 1),
('estado', 'Corrección', '#ef4444', 5, 1),
('estado', 'Aprobado', '#10b981', 6, 1),
('estado', 'Programado', '#06b6d4', 7, 1),
('estado', 'Publicado', '#22c55e', 8, 1),
('formato', 'Video', NULL, 1, 1),
('formato', 'Arte simple', NULL, 2, 1),
('formato', 'Arte compuesto', NULL, 3, 1),
('formato', 'Carrusel', NULL, 4, 1),
('formato', 'Reels', NULL, 5, 1),
('formato', 'Historia', NULL, 6, 1),
('pilar', 'INNOVACIÓN', NULL, 1, 1),
('pilar', 'FLEXIBILIDAD', NULL, 2, 1),
('pilar', 'EXCELENCIA', NULL, 3, 1),
('rol', 'admin', NULL, 1, 1),
('rol', 'community', NULL, 2, 1),
('rol', 'postproductor', NULL, 3, 1);

-- ============ Admin user ============
INSERT INTO usuarios (nombre, email, password, rol, activo) VALUES 
('Edson Llanque', 'edson.llanque.consultor@unifranz.edu.bo', crypt('Poteto2023*', gen_salt('bf')), 'admin', 1);