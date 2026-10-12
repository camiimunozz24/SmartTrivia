<?php

namespace app\controller;

class EquipoController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show()
    {
        $data = [
            "equipo" => "SmartDevsme ",
            "integrantes" => [
                ["nombre" => "Sidero, Celeste Melany"],
                ["nombre" => "Ramirez, Natalia Belén"],
                ["nombre" => "Alvarez Perona, Lucas"],
                ["nombre" => "Lopez, Sofia Belén"],
                ["nombre" => "Muñoz, Camila"],
            ],
        ];

        $this->render->renderiza("equipo", $data);
    }
}
