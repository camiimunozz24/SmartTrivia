<?php

namespace app\model;
class LoginModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function autenticar($usuario, $password)
    {
        $sql = "SELECT id, usuario, password, estado FROM usuarios WHERE usuario = ? OR email = ?";
        $fila = $this->database->queryOne($sql, [$usuario, $usuario]);

        if ($fila === null || !password_verify($password, $fila['password'])) {
            return null;
        }

        return ["id" => $fila["id"],
                "usuario" => $fila["usuario"],
                "estado" => $fila["estado"]];
    }



}

