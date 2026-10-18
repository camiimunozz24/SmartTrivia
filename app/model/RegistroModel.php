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

/*   public function registrar($usuario, $email, $password)
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuarios (usuario, email, password) VALUES (?, ?, ?)";
        $this->database->execute($sql, [$usuario, $email, $passwordHash]);
    }*/



//Con Token:
    public function registrar($usuario, $email, $password)
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        //se genera un token seguro y único
        $token = bin2hex(random_bytes(16));

        $sql = "INSERT INTO usuarios (usuario, email, password, token_validacion, estado) VALUES (?, ?, ?, ?, 'pendiente')";
        $this->database->execute($sql, [$usuario, $email, $passwordHash, $token]);

        // Retornamos el token para enviarlo por mail en el controlador
        return $token;
    }

    public function validarCuenta($token)
    {
        $sql = "UPDATE usuarios SET estado = 'activo', token_validacion = NULL WHERE token_validacion = ?";
        return $this->database->execute($sql, [$token]);
    }
}