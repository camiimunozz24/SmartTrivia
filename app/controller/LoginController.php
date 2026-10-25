<?php

namespace app\controller;

use Redirect;
use Request;

class LoginController
{
    private $render;
    private $model;

    public function __construct($render, $model)
    {
        $this->render = $render;
        $this->model = $model;
    }
    /* Muestra el formulario */
    public function show()
    {
//        if (isset($_SESSION['usuario_id'])) {
//            Redirect::to('/equipo/show'); // Si hay una sesión dirige al home
//        }

        $error = null;
        if (Request::get('login_error') !== null) { // Usuario o contraseña incorrectos
            $error = "Usuario o contraseña incorrectos.";
        } elseif (Request::get('error_verificacion') !== null) { // Mail sin confirmar
            $error = "Tu cuenta aún no está activada. Por favor, revisá tu casilla de correo.";
        }

        $data = [
            "error" => $error,
            "registro_ok" => Request::get('registro_ok') !== null, // Indica si el registro fue exitoso
        ];
        $this->render->renderiza("login", $data); // Arma la vista del login
    }

    public function login()
    {
        // El modelo revisa si los datos son correctos y los devuelve. Si no lo son devuelve null
        $usuario = $this->model->autenticar(trim(Request::post('usuario', '')), Request::post('password', ''));

        if ($usuario === null) { // Credenciales incorrectas
            Redirect::to('/login/show?login_error=1');
            return;
        }
        if ($usuario['estado'] !== 'activo') { // Mail sin confirmar
            // La contraseña era correcta pero falta verificar el mail:
            // lo mandamos a la pantalla del código (desde ahí puede pedir que se lo reenvíen).
            $_SESSION['email_pendiente'] = $usuario['email'];
            Redirect::to('/registro/verificarEmail');
            return;
        }
        // Regenera el id de la sesión para proteger de robos
        session_regenerate_id(true);
        // Guarda los datos del usuario
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['usuario'];
        $_SESSION['foto_perfil'] = $usuario['foto_perfil'];
        $_SESSION['rol']        = $usuario['rol'];
        Redirect::to('/equipo');
    }

    public function logout() {
        // Borra los datos de la sesión y dirige al login
        $_SESSION = [];          // limpia todas las variables de sesión
        session_destroy();
        Redirect::to('/login/show');
    }
}
