<?php

namespace app\validator;
class PasswordMinima implements Regla
{
    // Comprueba cumpla con el número mínimo de caracteres
    public function validar(array $datos)
    {
        if (strlen($datos['password']) < 6) {
            return "La contraseña debe tener al menos 6 caracteres.";
        }

        return null;
    }
}