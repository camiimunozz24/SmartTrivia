<?php

namespace app\validator;
class EmailValido implements Regla
{
    public function validar(array $datos)
    {
        $email = $datos['email'];

        if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Ingresá un email válido (hasta 100 caracteres).";
        }

        return null;
    }
}