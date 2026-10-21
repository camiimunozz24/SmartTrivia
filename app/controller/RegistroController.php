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

    // Muestra el formulario
    public function show()
    {
        // Si hay una sesión dirige al home
        if (isset($_SESSION['usuario_id'])) {
            Redirect::to('/equipo/show');
        }

        $data = [
            "error" => $_SESSION['registro_error'] ?? null, // Si hay error lo muestra
        ];
        unset($_SESSION['registro_error']); // Se borra el error para se se muestre una sola vez

        $this->render->renderiza("registro", $data); // Renderiza los datos
    }

    public function registrar()
    {
        // Arma un arreglo con los datos recibidos
        $datos = [
            "usuario" => trim(Request::post('usuario', '')),
            "email" => trim(Request::post('email', '')),
            "password" => Request::post('password', ''),
            "confirmar_password" => Request::post('confirmar_password', ''),
            "nombre_completo" => trim(Request::post('nombre_completo', '')),
            "fecha_nacimiento" => trim(Request::post('fecha_nacimiento', '')),
            "genero" => Request::post('genero', ''),
            "pais" => trim(Request::post('pais', '')),
            "ciudad" => trim(Request::post('ciudad', '')),
            "foto" => $_FILES['foto'] ?? null,
        ];
        // Valida los datos y devuelve mensaje en caso de error o null si está todo ok
        $error = $this->validador->validar($datos);

        // Si hay error lo guarda y vuelve al registro
        if ($error !== null) {
            $_SESSION['registro_error'] = $error;
            Redirect::to('/registro');
            return;
        }

        // Guarda la foto y si falla muestra mensaje de error
        $nombreFoto = $this->guardarFoto($datos['foto']);
        if ($nombreFoto === null) {
            $_SESSION['registro_error'] = "No se pudo guardar la foto. Probá de nuevo.";
            Redirect::to('/registro');
            return;
        }
        // Suma la foto al arreglo
        $datos['foto_perfil'] = $nombreFoto;

        // Inserta al usuario y devuelve el token
        $token = $this->model->registrar($datos);

        // Si se genera el token, envia el mail de validación
        if ($token) {

            \Mailer::enviarValidacion($datos['email'], $datos['usuario'], $token);
        }


        //Redirect::to('/?registro_ok=1');
        // Dirige a pantalla de revisar correo
        Redirect::to('/registro/verificarEmail');
    }

    // Muestra el aviso de envío de mail
    public function verificarEmail()
    {
        $this->render->renderiza("verificarEmail");
    }

    public function validarToken()
    {
        // Lee el token
        $token = $_GET['token'] ?? null;

        if ($token) {
            // El modelo busca el token y activa la cuenta
            $resultado = $this->model->validarCuenta($token);

            if ($resultado) {
               //exito, redirigimos a login.
                Redirect::to('/login/show');
                return;
            }
        }

        // Si el token falló o no vino, lo mandamos directo al login con el error de verificación
        Redirect::to('/login/show?error_verificacion=1');
    }

    private function guardarFoto($foto)
    {
        // Extensiones permitidas
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        // Detecta el tipo real del archivo mirando su contenido
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($foto['tmp_name']);

        // Si la extensión no está permitida no se guarda
        if (!isset($extensiones[$mime])) {
            return null;
        }
        // Ruta de la carpeta destino
        $carpeta = dirname(__DIR__, 2) . '/public/img/perfiles/';
        // Si no existe, la crea
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }

        // Nombre aleatorio para que no se pisen las fotos ni se pueda adivinar
        $nombre = bin2hex(random_bytes(16)) . '.' . $extensiones[$mime];

        // Mueve el archivo de la carpeta termporal a una definitiva. Si lo logra devuelve el nombre, sino null
        return move_uploaded_file($foto['tmp_name'], $carpeta . $nombre) ? $nombre : null;
    }
}