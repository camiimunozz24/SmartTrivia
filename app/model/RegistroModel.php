<?php

namespace app\model;
class RegistroModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }
    // Busca en la bd si hay un usuario con ese nombre y devuelve true. O false si no lo está
    public function existeUsuario($usuario)
    {
        $sql = "SELECT id FROM usuarios WHERE usuario = ?";
        return $this->database->queryOne($sql, [$usuario]) !== null;
    }
    // Busca en la bd si hay un usuario con ese email y devuelve true. O false si no lo está
    public function existeEmail($email)
    {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        return $this->database->queryOne($sql, [$email]) !== null;
    }


//Con Token:
    public function registrar(array $datos)
    {
        // Covierte la contraseña en un hash
        $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);

        // se genera un token seguro y único
        $token = bin2hex(random_bytes(16));

        // Inserta al usuario. El estado se define como pendiente hasta que se valide el mail
        $sql = "INSERT INTO usuarios
                    (usuario, email, password, nombre_completo, fecha_nacimiento, genero, pais, ciudad, foto_perfil, token_validacion, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')";
        // Los valores deben ir en el mismo orden que las columnas de arriba
        $this->database->execute($sql, [
            $datos['usuario'],
            $datos['email'],
            $passwordHash,  // Guarda el hash
            $datos['nombre_completo'],
            $datos['fecha_nacimiento'],
            $datos['genero'],
            $datos['pais'],
            $datos['ciudad'],
            $datos['foto_perfil'],
            $token,
        ]);
        // Retornamos el token para enviarlo por mail en el controlador
        return $token;
    }

    // Activa la cuenta que coincida con el token
    public function validarCuenta($token)
    {
        // Pasa a estado activo y borra el token para que solo se pueda usar una vez
        $sql = "UPDATE usuarios SET estado = 'activo', token_validacion = NULL WHERE token_validacion = ?";
        // Devuelve cuántas filas modificó
        $filasAfectadas = $this->database->execute($sql, [$token]);

        //si afecto al menos una fila, el token es valido.
        return $filasAfectadas > 0;
    }
}