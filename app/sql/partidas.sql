-- PARTIDAS: una fila por partida jugada. Es la "fuente de la verdad" del juego (nada se confía al navegador).
-- Ejecutar con la base smart_trivia activa. Se puede correr más de una vez sin problema.
--
-- Cómo evita las trampas esta tabla:
--  * activa_usuario_id es UNIQUE: mientras una partida está en curso vale el id del usuario, y al terminar pasa a NULL.
--    Como MySQL no permite dos filas con el mismo valor (los NULL no cuentan), un usuario NO puede tener
--    dos partidas en curso a la vez, aunque abra otra pestaña, otro navegador o otro dispositivo.
--  * pregunta_token es un código de un solo uso: cada pregunta se puede responder UNA sola vez.
--  * pregunta_inicio_ms guarda cuándo se mostró la pregunta, así el servidor mide los 20 segundos.

CREATE TABLE IF NOT EXISTS partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    puntaje INT NOT NULL DEFAULT 0,
    estado ENUM('en_curso', 'terminada') NOT NULL DEFAULT 'en_curso',
    activa_usuario_id INT NULL,
    pregunta_id INT NULL,
    pregunta_token CHAR(32) NULL,
    pregunta_inicio_ms BIGINT NULL,
    motivo_fin ENUM('error', 'tiempo', 'sin_preguntas') NULL,
    resultado_visto TINYINT(1) NOT NULL DEFAULT 0,
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_fin TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_partida_activa (activa_usuario_id),
    KEY idx_partidas_usuario (usuario_id, estado)
);
