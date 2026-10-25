<?php

namespace app\controller;

use Redirect;
use Request;

class RegistroController
{
    // Segundos que hay que esperar para volver a pedir que se reenvíe el código
    const SEGUNDOS_ENTRE_ENVIOS = 60;

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
//        // Si hay una sesión dirige al home
//        if (isset($_SESSION['usuario_id'])) {
//            Redirect::to('/equipo/show');
//        }

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

        // Inserta al usuario y devuelve el código de verificación
        $codigo = $this->model->registrar($datos);

        // Guardamos en sesión para qué mail estamos verificando
        $_SESSION['email_pendiente'] = $datos['email'];
        $_SESSION['ultimo_envio'] = time();

        // Envía el mail con el código. Si falla, se avisa en la pantalla siguiente
        $enviado = \Mailer::enviarCodigo($datos['email'], $datos['usuario'], $codigo);
        if (!$enviado) {
            $_SESSION['verificacion_error'] = "No pudimos enviar el mail. Probá con \"Reenviar código\" en un momento.";
        }

        // Dirige a la pantalla donde se escribe el código
        Redirect::to('/registro/verificarEmail');
    }

    // Muestra la pantalla donde se escribe el código que llegó por mail
    public function verificarEmail()
    {
        if (!isset($_SESSION['email_pendiente'])) {
            Redirect::to('/registro');
        }

        $data = [
            "email" => $_SESSION['email_pendiente'],
            "error" => $_SESSION['verificacion_error'] ?? null,
            "aviso" => $_SESSION['verificacion_aviso'] ?? null,
        ];
        unset($_SESSION['verificacion_error'], $_SESSION['verificacion_aviso']);

        $this->render->renderiza("verificarEmail", $data);
    }

    // Recibe el código escrito en el formulario y lo valida
    public function validarCodigo()
    {
        $email = $_SESSION['email_pendiente'] ?? null;
        if ($email === null) {
            Redirect::to('/registro');
        }

        // Pasamos a mayúsculas y dejamos solo letras y números (por si escriben "k7m2 qxpa")
        $codigo = preg_replace('/[^A-Z0-9]/', '', strtoupper(Request::post('codigo', '')));

        // El modelo compara el código y devuelve el resultado
        $resultado = $this->model->validarCodigo($email, $codigo);

        if ($resultado === 'ok' || $resultado === 'ya_activo') {
            // Cuenta activada: limpiamos la sesión y vamos al login
            unset($_SESSION['email_pendiente'], $_SESSION['ultimo_envio']);
            Redirect::to('/login/show?registro_ok=1');
            return;
        }

        if ($resultado === 'inexistente') {
            unset($_SESSION['email_pendiente']);
            Redirect::to('/registro');
            return;
        }

        $_SESSION['verificacion_error'] = "El código es incorrecto. Revisalo e intentá de nuevo.";
        Redirect::to('/registro/verificarEmail');
    }

    // Vuelve a mandar por mail el MISMO código (no se genera uno nuevo)
    public function reenviarCodigo()
    {
        $email = $_SESSION['email_pendiente'] ?? null;
        if ($email === null) {
            Redirect::to('/registro');
        }

        // Para que no se pueda usar para mandar mails sin parar
        $espera = self::SEGUNDOS_ENTRE_ENVIOS - (time() - ($_SESSION['ultimo_envio'] ?? 0));
        if ($espera > 0) {
            $_SESSION['verificacion_error'] = "Esperá $espera segundos antes de pedir que te lo mandemos de nuevo.";
            Redirect::to('/registro/verificarEmail');
        }

        $pendiente = $this->model->obtenerPendiente($email);
        if ($pendiente === null) {
            // La cuenta ya está activa o no existe
            unset($_SESSION['email_pendiente']);
            Redirect::to('/login/show');
        }

        $_SESSION['ultimo_envio'] = time();

        if (\Mailer::enviarCodigo($email, $pendiente['usuario'], $pendiente['token_validacion'])) {
            $_SESSION['verificacion_aviso'] = "Te mandamos el código de nuevo. Revisá también la carpeta de spam.";
        } else {
            $_SESSION['verificacion_error'] = "No pudimos enviar el mail. Intentá de nuevo en un momento.";
        }

        Redirect::to('/registro/verificarEmail');
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