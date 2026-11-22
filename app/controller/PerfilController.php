<?php

namespace app\controller;

use Redirect;
use Request;
use app\validator\FechaNacimientoValida;
use app\validator\FotoPerfilValida;
use app\validator\GeneroValido;
use app\validator\NombreCompletoValido;
use app\validator\PasswordMinima;
use app\validator\PasswordsCoinciden;
use app\validator\UbicacionValida;
use app\validator\UsuarioValido;
use app\validator\Validador;

class PerfilController
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
        $idParam = Request::get('id');
        $usuarioLogueadoId = $_SESSION['usuario_id'] ?? null;

        // Si no viene id por GET, muestra el perfil del usuario con sesión activa
        $idUsuario = $idParam !== null ? (int)$idParam : $usuarioLogueadoId;

        if (!$idUsuario) {
            Redirect::to('/login/show');
            return;
        }

        $usuario = $this->model->obtenerPorId($idUsuario);

        if (!$usuario) {
            Redirect::to('/login/show');
            return;
        }

        // Cálculo de edad a partir de la fecha de nacimiento
        $edad = null;
        if (!empty($usuario['fecha_nacimiento'])) {
            try {
                $nacimiento = new \DateTime($usuario['fecha_nacimiento']);
                $hoy = new \DateTime();
                $edad = $hoy->diff($nacimiento)->y;
            } catch (\Exception $e) {
                $edad = null;
            }
        }

        $esPropio = ($usuarioLogueadoId !== null && (int)$usuarioLogueadoId === (int)$usuario['id']);
        $rolLogueado = $_SESSION['rol'] ?? '';
        $esPrivilegiado = in_array($rolLogueado, ['editor', 'admin', 'maestro'], true);
        // El historial es privado: solo lo ve el propio usuario o roles privilegiados (editor/admin/maestro)
        $puedeVerHistorial = $esPropio || $esPrivilegiado;

        // Estadísticas de juego (en el perfil solo mostramos las últimas 3 partidas)
        $stats = $this->model->obtenerEstadisticas($usuario['id']);
        $historial = $puedeVerHistorial ? $this->model->obtenerHistorialPartidas($usuario['id'], 3) : [];

        // URL pública para el código QR
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $urlPerfil = "{$protocolo}://{$host}/perfil/show?id=" . $usuario['id'];
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($urlPerfil);

        $urlHistorialCompleto = $esPropio ? '/perfil/historial' : '/perfil/historial?id=' . (int)$usuario['id'];

        $data = [
            "id" => $usuario['id'],
            "usuario" => $usuario['usuario'],
            "nombre_completo" => $usuario['nombre_completo'],
            "email" => $usuario['email'],
            "fecha_nacimiento" => $usuario['fecha_nacimiento'],
            "edad" => $edad,
            "genero" => $usuario['genero'],
            "pais" => $usuario['pais'],
            "ciudad" => $usuario['ciudad'],
            "foto_perfil" => $usuario['foto_perfil'],
            "tiene_foto" => !empty($usuario['foto_perfil']),
            "puntaje_acumulado" => $stats['puntaje_acumulado_formato'],
            "partidas_jugadas" => $stats['partidas_jugadas'],
            "preguntas_correctas" => $stats['preguntas_correctas'],
            "mayor_racha" => $stats['mayor_racha'],
            "racha_dias" => $stats['racha_dias'],
            "multiplicador" => $stats['multiplicador'],
            "tiene_racha_dias" => $stats['racha_dias'] > 0,
            "puede_ver_historial" => $puedeVerHistorial,
            "url_historial_completo" => $urlHistorialCompleto,
            "historial_partidas" => $historial,
            "tiene_historial" => count($historial) > 0,
            "qr_url" => $qrUrl,
            "url_perfil" => $urlPerfil,
            "es_propio" => $esPropio,
            "mostrar_panel_editor" => $esPropio && in_array($rolLogueado, ['editor', 'admin', 'maestro'], true),
            "mostrar_panel_admin" => $esPropio && in_array($rolLogueado, ['admin', 'maestro'], true),
            "mostrar_panel_maestro" => $esPropio && $rolLogueado === 'maestro',
            "fecha_registro" => date('d/m/Y', strtotime($usuario['fecha_registro'])),
            "aviso" => $_SESSION['perfil_ok'] ?? null, // Mensaje de "cambios guardados"
        ];
        unset($_SESSION['perfil_ok']);

        $this->render->renderiza("perfil", $data);
    }

    // Historial completo de partidas (privado: solo propio usuario, o editor/admin/maestro)
    public function historial()
    {
        $usuarioLogueadoId = (int)($_SESSION['usuario_id'] ?? 0);
        $rolLogueado = $_SESSION['rol'] ?? '';
        $esPrivilegiado = in_array($rolLogueado, ['editor', 'admin', 'maestro'], true);

        $idParam = Request::get('id');
        $idObjetivo = $idParam !== null ? (int)$idParam : $usuarioLogueadoId;

        // Un usuario común jamás puede ver el historial de otro usuario
        if ($idObjetivo !== $usuarioLogueadoId && !$esPrivilegiado) {
            Redirect::to('/perfil/historial');
            return;
        }

        $usuario = $this->model->obtenerPorId($idObjetivo);
        if (!$usuario) {
            Redirect::to('/home/show');
            return;
        }

        $stats = $this->model->obtenerEstadisticas($usuario['id']);
        $historialCompleto = $this->model->obtenerHistorialPartidas($usuario['id'], null);
        $esPropio = ($idObjetivo === $usuarioLogueadoId);

        $this->render->renderiza("historial", [
            "id" => $usuario['id'],
            "usuario" => $usuario['usuario'],
            "nombre_completo" => $usuario['nombre_completo'],
            "es_propio" => $esPropio,
            "url_volver_perfil" => $esPropio ? '/perfil/show' : '/perfil/show?id=' . (int)$usuario['id'],
            "puntaje_acumulado" => $stats['puntaje_acumulado_formato'],
            "partidas_jugadas" => $stats['partidas_jugadas'],
            "preguntas_correctas" => $stats['preguntas_correctas'],
            "mayor_racha" => $stats['mayor_racha'],
            "historial_partidas" => $historialCompleto,
            "tiene_historial" => count($historialCompleto) > 0,
        ]);
    }

    // Muestra el formulario para editar el perfil. Solo se puede editar el propio
    public function editar()
    {
        $usuario = $this->model->obtenerPorId($_SESSION['usuario_id']);
        if (!$usuario) {
            Redirect::to('/login/show');
            return;
        }

        $data = [
            "usuario" => $usuario['usuario'],
            "email" => $usuario['email'],
            "nombre_completo" => $usuario['nombre_completo'],
            "fecha_nacimiento" => $usuario['fecha_nacimiento'],
            "pais" => $usuario['pais'],
            "ciudad" => $usuario['ciudad'],
            "foto_perfil" => $usuario['foto_perfil'],
            "tiene_foto" => !empty($usuario['foto_perfil']),
            "genero_masculino" => $usuario['genero'] === 'Masculino',
            "genero_femenino" => $usuario['genero'] === 'Femenino',
            "genero_nocargado" => $usuario['genero'] === 'Prefiero no cargarlo',
            "error" => $_SESSION['perfil_error'] ?? null,
        ];
        unset($_SESSION['perfil_error']);

        $this->render->renderiza("perfilEditar", $data);
    }

    // Recibe los datos editados, los valida (en el servidor) y los guarda
    public function guardar()
    {
        $id = $_SESSION['usuario_id'];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/perfil/editar');
            return;
        }

        $datos = [
            "usuario" => trim(Request::post('usuario', '')),
            "nombre_completo" => trim(Request::post('nombre_completo', '')),
            "fecha_nacimiento" => trim(Request::post('fecha_nacimiento', '')),
            "genero" => Request::post('genero', ''),
            "pais" => trim(Request::post('pais', '')),
            "ciudad" => trim(Request::post('ciudad', '')),
        ];
        $foto = $_FILES['foto'] ?? null;
        $subioFoto = $foto !== null && $foto['error'] !== UPLOAD_ERR_NO_FILE; // La foto es opcional: si no sube, queda la actual

        $reglas = [new NombreCompletoValido(), new FechaNacimientoValida(), new GeneroValido(), new UbicacionValida(), new UsuarioValido()];
        if ($subioFoto) {
            $reglas[] = new FotoPerfilValida();
        }
        $error = (new Validador($reglas))->validar($datos + ["foto" => $foto]);

        if ($error === null && $datos['usuario'] === '') {
            $error = "Ingresá un nombre de usuario.";
        }
        if ($error === null && !$this->model->usuarioDisponible($datos['usuario'], $id)) {
            $error = "Ese nombre de usuario ya está en uso.";
        }
        if ($error !== null) {
            $_SESSION['perfil_error'] = $error;
            Redirect::to('/perfil/editar');
            return;
        }

        $nombreFoto = null;
        if ($subioFoto) {
            $nombreFoto = $this->guardarFoto($foto);
            if ($nombreFoto === null) {
                $_SESSION['perfil_error'] = "No se pudo guardar la foto. Probá de nuevo.";
                Redirect::to('/perfil/editar');
                return;
            }
        }

        $fotoAnterior = $this->model->obtenerPorId($id)['foto_perfil'] ?? '';
        $this->model->actualizar($id, $datos, $nombreFoto);

        if ($nombreFoto !== null && $fotoAnterior !== '') { // Se borra la foto vieja para no acumular archivos
            @unlink(dirname(__DIR__, 2) . '/public/img/perfiles/' . basename($fotoAnterior));
        }

        // La barra de navegación lee estos datos de la sesión: se actualizan
        $_SESSION['usuario'] = $datos['usuario'];
        if ($nombreFoto !== null) {
            $_SESSION['foto_perfil'] = $nombreFoto;
        }

        $_SESSION['perfil_ok'] = "Tus datos se guardaron correctamente.";
        Redirect::to('/perfil/show');
    }

    // Cambia la contraseña: pide la actual y la nueva dos veces
    public function cambiarPassword()
    {
        $id = $_SESSION['usuario_id'];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/perfil/editar');
            return;
        }

        $actual = Request::post('password_actual', '');
        $datos = [
            "password" => Request::post('password', ''),
            "confirmar_password" => Request::post('confirmar_password', ''),
        ];

        if (!password_verify($actual, (string)$this->model->obtenerHashPassword($id))) {
            $error = "La contraseña actual no es correcta.";
        } else {
            $error = (new Validador([new PasswordMinima(), new PasswordsCoinciden()]))->validar($datos);
        }

        if ($error !== null) {
            $_SESSION['perfil_error'] = $error;
            Redirect::to('/perfil/editar');
            return;
        }

        $this->model->cambiarPassword($id, password_hash($datos['password'], PASSWORD_DEFAULT));
        $_SESSION['perfil_ok'] = "Tu contraseña se cambió correctamente.";
        Redirect::to('/perfil/show');
    }

    // Guarda la foto subida en public/img/perfiles con un nombre aleatorio. Devuelve el nombre o null si falla
    private function guardarFoto($foto)
    {
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($foto['tmp_name']);
        if (!isset($extensiones[$mime])) {
            return null;
        }

        $carpeta = dirname(__DIR__, 2) . '/public/img/perfiles/';
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . $extensiones[$mime];
        return move_uploaded_file($foto['tmp_name'], $carpeta . $nombre) ? $nombre : null;
    }
}
