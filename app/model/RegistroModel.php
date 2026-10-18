<?php

namespace app\model;
class RegistroModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function existeUsuario($usuario)
    {
        $sql = "SELECT id FROM usuarios WHERE usuario = ?";
        return $this->database->queryOne($sql, [$usuario]) !== null;
    }

    public function existeEmail($email)
    {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        return $this->database->queryOne($sql, [$email]) !== null;
    }

    public function registrar($usuario, $email, $password)
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuarios (usuario, email, password) VALUES (?, ?, ?)";
        $this->database->execute($sql, [$usuario, $email, $passwordHash]);
    }
}