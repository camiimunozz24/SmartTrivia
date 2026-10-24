<?php

namespace app\model;

class PerfilModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerPorId($id)
    {
        $sql = "SELECT id, usuario, email, nombre_completo, fecha_nacimiento, genero, pais, ciudad, foto_perfil, fecha_registro
                FROM usuarios
                WHERE id = ?";
        return $this->database->queryOne($sql, [(int)$id]);
    }

    public function obtenerEstadisticas($usuarioId)
    {
        // En esta entrega se inicializan en 0.
        // Quedará conectado a las tablas de partidas y preguntas en la siguiente entrega.
        return [
            "puntaje_acumulado" => 0,
            "partidas_jugadas" => 0
        ];
    }
}
