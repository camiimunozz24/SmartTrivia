<?php

namespace app\validator;
class NombreCompletoValido implements Regla
{
    // Revisa el nombre completo: Cant de caracteres y solo letras
    public function validar(array $datos)
    {
        $nombre = $datos['nombre_completo'] ?? '';

        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100 || !preg_match("/^[\p{L}\s'.-]+$/u", $nombre)) {
            return "Ingresá tu nombre completo (entre 3 y 100 caracteres, solo letras).";
        }

        return null;
    }
}