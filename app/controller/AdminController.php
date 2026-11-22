<?php

namespace app\controller;

class AdminController
{
    private $render;
    private $model;

    public function __construct($render, $model)
    {
        $this->render = $render;
        $this->model = $model;
    }

    public function show()
    {
        $rol = $_SESSION['rol'] ?? 'admin';
        $resumen = $this->model->obtenerResumenSistema();

        $this->render->renderiza("admin", [
            'usuario' => $_SESSION['usuario'] ?? 'admin',
            'rol' => $rol,
            'es_maestro' => $rol === 'maestro',
            'resumen' => $resumen,
        ]);
    }
}
