CREATE TABLE usuarios (
      id INT AUTO_INCREMENT PRIMARY KEY,
      usuario VARCHAR(50) NOT NULL UNIQUE,
      email VARCHAR(100) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL,
      fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
/*se agrega para registro con email*/
ALTER TABLE `usuarios`
    ADD COLUMN `estado`
        ENUM('pendiente','activo','baneado') NOT NULL DEFAULT 'pendiente';
ALTER TABLE `usuarios`
    ADD COLUMN `token_validacion`
        VARCHAR(255) NULL AFTER `estado`;
/*Agregado de datos faltantes*/
ALTER TABLE `usuarios`
    ADD COLUMN `nombre_completo` VARCHAR(100) NOT NULL,
    ADD COLUMN `fecha_nacimiento` DATE NOT NULL,
    ADD COLUMN `genero` ENUM('Masculino','Femenino','Prefiero no cargarlo') NOT NULL,
    ADD COLUMN `pais` VARCHAR(100) NOT NULL,
    ADD COLUMN `ciudad` VARCHAR(100) NOT NULL,
    ADD COLUMN `foto_perfil` VARCHAR(255) NOT NULL;
/*Se agrega rol*/
ALTER TABLE `usuarios`
    ADD COLUMN `rol`
        ENUM('usuario', 'editor', 'admin', 'maestro') NOT NULL DEFAULT 'usuario';
/* Racha diaria de acceso y mejor racha de aciertos */
ALTER TABLE `usuarios`
    ADD COLUMN `racha_dias` INT NOT NULL DEFAULT 0,
    ADD COLUMN `ultimo_dia_acierto` DATE NULL,
    ADD COLUMN `mejor_racha_historica` INT NOT NULL DEFAULT 0;
/***************************************/
-- Usuarios de prueba para testing / corrección
-- Usuario: admin | Contraseña: admin1234
INSERT INTO `usuarios`
(`usuario`, `email`, `password`, `nombre_completo`, `fecha_nacimiento`, `genero`, `pais`, `ciudad`, `foto_perfil`, `estado`, `rol`)
VALUES
    ('admin', 'admin@smarttrivia.com', '$2y$10$HvNfcT0ODfNj/fWx4Hbh7eFSaduwZ8dAWQRcbHqEguhBjGfFJIUIi', 'Administrador SmartTrivia', '2000-01-01', 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires', '', 'activo', 'admin');
-- Usuario: usuario | Contraseña: usuario1234
INSERT INTO `usuarios`
(`usuario`, `email`, `password`, `nombre_completo`, `fecha_nacimiento`, `genero`, `pais`, `ciudad`, `foto_perfil`, `estado`, `rol`)
VALUES
    ('usuario', 'usuario@smarttrivia.com', '$2y$10$wjwyAmsuCa6UkUIFdeQZv.Oll.D2CzVFHd1p/TKOtc5Enr43WkN8S', 'Usuario Prueba', '2000-01-01', 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires', '', 'activo', 'usuario');
-- Usuario: editor | Contraseña: editor1234
INSERT INTO `usuarios`
(`usuario`, `email`, `password`, `nombre_completo`, `fecha_nacimiento`, `genero`, `pais`, `ciudad`, `foto_perfil`, `estado`, `rol`)
VALUES
    ('editor', 'editor@smarttrivia.com', '$2y$10$/AuZYyhNgGib7FeCAjuqde6xlNIFN1M32rOEsVWG5XJJSpYp0MAg2', 'Editor SmartTrivia', '2000-01-01', 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires', '', 'activo', 'editor');
-- Usuario: maestro | Contraseña: maestro1234
INSERT INTO `usuarios`
(`usuario`, `email`, `password`, `nombre_completo`, `fecha_nacimiento`, `genero`, `pais`, `ciudad`, `foto_perfil`, `estado`, `rol`)
VALUES
    ('maestro', 'maestro@smarttrivia.com', '$2y$10$eO4x0KF5vhoL.pTMwUzwxeG9KV4zTSO/gbNSXCOl99NmgNzSByLB2', 'Control Maestro SmartTrivia', '2000-01-01', 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires', '', 'activo', 'maestro');
/***************************************/