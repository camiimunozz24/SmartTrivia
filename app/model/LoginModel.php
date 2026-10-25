<?php

namespace app\model;
class LoginModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    // Revisa si las credenciales son correctas y devuelve los datos. De no serlo, devuelve null
    public function autenticar($usuario, $password)
    {
        // Consulta SQL
        $sql = "SELECT id, usuario, email, password, estado, foto_perfil, rol FROM usuarios WHERE usuario = ? OR email = ?";
        // Ejecuta la consulta y trae la fila o null
        $fila = $this->database->queryOne($sql, [$usuario, $usuario]);

        // Si no existe el usuario o la contraseña no coincide devuelve null
        if ($fila === null || !password_verify($password, $fila['password'])) {
            return null;
        }
        // Devuelve solo los datos que el controlador necesita para armar la sesión
        return ["id" => $fila["id"],
                "usuario" => $fila["usuario"],
                "email" => $fila["email"],
                "estado" => $fila["estado"],
                "foto_perfil" => $fila["foto_perfil"],
                "rol" => $fila["rol"]];
    }

}

