-- CONTROL MAESTRO Y BANEO DE USUARIOS
-- Agrega el rol 'maestro' y el estado 'baneado' en la tabla usuarios.

ALTER TABLE `usuarios`
    MODIFY COLUMN `rol` ENUM('usuario', 'editor', 'admin', 'maestro') NOT NULL DEFAULT 'usuario',
    MODIFY COLUMN `estado` ENUM('pendiente', 'activo', 'baneado') NOT NULL DEFAULT 'pendiente';

-- Usuario de prueba para Control Maestro (maestro / maestro1234)
INSERT IGNORE INTO `usuarios`
(`usuario`, `email`, `password`, `nombre_completo`, `fecha_nacimiento`, `genero`, `pais`, `ciudad`, `foto_perfil`, `estado`, `rol`)
VALUES
    ('maestro', 'maestro@smarttrivia.com', '$2y$10$eO4x0KF5vhoL.pTMwUzwxeG9KV4zTSO/gbNSXCOl99NmgNzSByLB2', 'Control Maestro SmartTrivia', '2000-01-01', 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires', '', 'activo', 'maestro');
