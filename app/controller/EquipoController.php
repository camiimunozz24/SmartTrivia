<?php

namespace app\controller;
use Redirect;
class EquipoController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show()
    {
//        if (!isset($_SESSION['usuario_id'])) {
//            Redirect::to('/login/show');
//        }


        $data = [
            "equipo" => "SmartDevs",
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
