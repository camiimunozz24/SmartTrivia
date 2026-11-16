-- RANKING Y RACHAS
-- Agrega columnas necesarias para registrar rachas diarias (con multiplicador)
-- y estadísticas de aciertos y rachas consecutivas.

ALTER TABLE `usuarios`
    ADD COLUMN `racha_dias` INT NOT NULL DEFAULT 0,
    ADD COLUMN `ultimo_dia_acierto` DATE NULL,
    ADD COLUMN `mejor_racha_historica` INT NOT NULL DEFAULT 0;

ALTER TABLE `partidas`
    MODIFY `puntaje` DECIMAL(8,2) NOT NULL DEFAULT 0,
    ADD COLUMN `respuestas_correctas` INT NOT NULL DEFAULT 0,
    ADD COLUMN `mejor_racha` INT NOT NULL DEFAULT 0,
    ADD COLUMN `racha_actual` INT NOT NULL DEFAULT 0,
    ADD COLUMN `multiplicador_aplicado` DECIMAL(4,2) NOT NULL DEFAULT 1.00;
