CREATE TABLE noticias_historial (
    id SERIAL PRIMARY KEY, -- Genera un ID numérico automático
    titulo TEXT,           -- Almacena el título de la noticia o video
    link TEXT,             -- URL de la noticia (GNews) o video (YouTube)
    fecha DATE,            -- Fecha de publicación de la fuente original
    diario TEXT,           -- Nombre del medio o canal de YouTube
    created_at TIMESTAMP DEFAULT now(), -- Fecha/hora de registro en tu sistema
    resumen TEXT,          -- Descripción corta o snippet
    imagen TEXT            -- URL de la miniatura o imagen destacada
);