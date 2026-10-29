-- PREGUNTA, RESPUESTAS Y REPORTES
-- Requiere haber ejecutado antes smart_trivia.sql (depende de la tabla usuarios).
-- Ejecutar con la base smart_trivia activa


CREATE TABLE categorias(
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) NOT NULL
);

CREATE TABLE preguntas(
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    enunciado VARCHAR(255) NOT NULL,
    estado ENUM('sugerida', 'activa', 'rechazada', 'baja') NOT NULL DEFAULT 'sugerida',
    creada_por INT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (creada_por) REFERENCES usuarios(id)
);

CREATE TABLE respuestas(
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    texto VARCHAR (150) NOT NULL,
    es_correcto TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE
);
);

CREATE TABLE reportes(
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    estado ENUM('pendiente', 'resuelto') NOT NULL DEFAULT 'pendiente',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pregunta_id) REFERENCES preguntas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE preguntas_vistas(
     id INT AUTO_INCREMENT PRIMARY KEY,
     usuario_id INT NOT NULL,
     pregunta_id INT NOT NULL,
     UNIQUE (usuario_id, pregunta_id),
     FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
     FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE
);