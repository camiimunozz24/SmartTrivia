CREATE TABLE usuarios (
      id INT AUTO_INCREMENT PRIMARY KEY,
      usuario VARCHAR(50) NOT NULL UNIQUE,
      email VARCHAR(100) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL,
      fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
/*se agrega para registro con email*/
ALTER TABLE `usuarios`
    ADD COLUMN  `estado`
    ENUM('pendiente','activo')NOT NULL DEFAULT 'pendiente';

ALTER TABLE `usuarios`
    ADD COLUMN  `token_validacion`
    VARCHAR(255) NULL AFTER `estado`;
/***************************************/
/*Agregado de datos faltantes*/
ALTER TABLE `usuarios`
    ADD COLUMN `nombre_completo` VARCHAR(100) NOT NULL,
    ADD COLUMN `fecha_nacimiento` DATE NOT NULL,
    ADD COLUMN `genero` ENUM('Masculino','Femenino','Prefiero no cargarlo') NOT NULL,
    ADD COLUMN `pais` VARCHAR(100) NOT NULL,
    ADD COLUMN `ciudad` VARCHAR(100) NOT NULL,
    ADD COLUMN `foto_perfil` VARCHAR(255) NOT NULL;






