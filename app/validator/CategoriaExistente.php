<?php

namespace app\validator;

class CategoriaExistente implements Regla
{
    // Comprueba en la bd que la categoría elegida exista
    private $model;

    public function __construct($model)
    {
        $this->model = $model;
    }
    public function validar(array $datos)
    {
        if (!ctype_digit($datos['categoria_id']) || !$this->model->existeCategoria((int)$datos['categoria_id'])) {
            return "Elegí una categoría válida.";
        }

        return null;
    }
}