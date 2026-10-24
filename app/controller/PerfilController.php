<?php

namespace app\controller;

use Redirect;
use Request;

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

        // Estadísticas de juego
        $stats = $this->model->obtenerEstadisticas($usuario['id']);

        // URL pública para el código QR
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $urlPerfil = "{$protocolo}://{$host}/perfil/show?id=" . $usuario['id'];
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($urlPerfil);

        $esPropio = ($usuarioLogueadoId !== null && (int)$usuarioLogueadoId === (int)$usuario['id']);

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
            "puntaje_acumulado" => $stats['puntaje_acumulado'],
            "partidas_jugadas" => $stats['partidas_jugadas'],
            "qr_url" => $qrUrl,
            "url_perfil" => $urlPerfil,
            "es_propio" => $esPropio,
            "fecha_registro" => date('d/m/Y', strtotime($usuario['fecha_registro']))
        ];

        $this->render->renderiza("perfil", $data);
    }
}
