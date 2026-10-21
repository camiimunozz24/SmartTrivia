<?php

namespace app\validator;
class UsuarioValido implements Regla
{
    // Revisa el formato del nombre de usuario
    public function validar(array $datos)
    {
        $usuario = $datos['usuario'];

        if (strlen($usuario) > 50 || strpos($usuario, '@') !== false) {
            return "El usuario no puede tener más de 50 caracteres ni contener @.";
        }

        return null;
    }
}