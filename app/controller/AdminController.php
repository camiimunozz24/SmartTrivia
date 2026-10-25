<?php

namespace app\controller;

class AdminController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show()
    {
        $data = [
            "usuario" => $_SESSION['usuario'] ?? 'admin',
            "rol"     => $_SESSION['rol'] ?? 'admin'
        ];

        $this->render->renderiza("admin", $data);
    }
}