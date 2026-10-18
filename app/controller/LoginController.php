<?php

namespace app\controller;

use Redirect;
use Request;

class LoginController
{
    private $model;

    public function __construct($render, $model)
    {
        $this->render = $render;
        $this->model = $model;
    }

    public function show()
    {
        if (isset($_SESSION['usuario_id'])) {
            Redirect::to('/equipo/show');
        }

        $error = null;
        if (Request::get('login_error') !== null) {
            $error = "Usuario o contraseña incorrectos.";
        } elseif (Request::get('error_verificacion') !== null) {
            $error = "Tu cuenta aún no está activada. Por favor, revisá tu casilla de correo.";
        }

        $data = [
            "error" => $error,
            "registro_ok" => Request::get('registro_ok') !== null,
        ];
        $this->render->renderiza("login", $data);
    }

    public function login()
    {
        $usuario = $this->model->autenticar(trim(Request::post('usuario', '')), Request::post('password', ''));

        if ($usuario === null) {
            Redirect::to('/login/show?login_error=1'); // <-- Ruta explícita
        }
        if ($usuario['estado'] !== 'activo') {
            Redirect::to('/login/show?error_verificacion=1');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['usuario'];
        Redirect::toIndex();
    }

    public function logout() {
        session_destroy();
        Redirect::to('/login/show');
    }
}

