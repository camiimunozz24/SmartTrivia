<?php

namespace app\validator;
class EmailDisponible implements Regla
{
    private $model;

    public function __construct($model)
    {
        $this->model = $model; // Llama al modelo para hacer las consultas con la bd
    }

    // Comprueba que el correo no esté registrado
    public function validar(array $datos)
    {
        if ($this->model->existeEmail($datos['email'])) {
            return "Ese email ya está registrado.";
        }

        return null;
    }
}