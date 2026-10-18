<?php

namespace app\controller;

use Redirect;
use Request;

class RegistroController
{
    private $render;
    private $model;
    private $validador;

    public function __construct($render, $model, $validador)
    {
        $this->render = $render;
        $this->model = $model;
        $this->validador = $validador;
    }

    public function show()
    {
        if (isset($_SESSION['usuario_id'])) {
            Redirect::to('/equipo/show');
        }

        $data = [
            "error" => $_SESSION['registro_error'] ?? null,
        ];
        unset($_SESSION['registro_error']);

        $this->render->renderiza("registro", $data);
    }

    public function registrar()
    {
        $datos = [
            "usuario" => trim(Request::post('usuario', '')),
            "email" => trim(Request::post('email', '')),
            "password" => Request::post('password', ''),
            "confirmar_password" => Request::post('confirmar_password', ''),
        ];

        $error = $this->validador->validar($datos);

        if ($error !== null) {
            $_SESSION['registro_error'] = $error;
            Redirect::to('/registro');
        }

        $this->model->registrar($datos['usuario'], $datos['email'], $datos['password']);
        Redirect::to('/?registro_ok=1');
    }
}