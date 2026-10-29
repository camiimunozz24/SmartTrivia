<?php

namespace app\controller;

class HomeController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show()
    {
        $data = [
            "usuario" => $_SESSION['usuario'] ?? '',
        ];

        $this->render->renderiza("home", $data);
    }
}