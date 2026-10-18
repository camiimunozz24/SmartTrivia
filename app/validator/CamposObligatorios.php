<?php

namespace app\validator;
class CamposObligatorios implements Regla
{
    public function validar(array $datos)
    {
        foreach ($datos as $valor) {
            if ($valor === '') {
                return "Completá todos los campos.";
            }
        }

        return null;
    }
}