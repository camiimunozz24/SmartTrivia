<?php

namespace app\validator;
class PasswordsCoinciden implements Regla
{
    // Comprueba que las contraseñas del registro sean iguales
    public function validar(array $datos)
    {
        if ($datos['password'] !== $datos['confirmar_password']) {
            return "Las contraseñas no coinciden.";
        }

        return null;
    }
}