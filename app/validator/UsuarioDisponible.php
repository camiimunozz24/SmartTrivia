<?php

namespace app\validator;
class UsuarioDisponible implements Regla
{
    private $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function validar(array $datos)
    {
        if ($this->model->existeUsuario($datos['usuario'])) {
            return "Ese nombre de usuario ya está en uso.";
        }

        return null;
    }
}