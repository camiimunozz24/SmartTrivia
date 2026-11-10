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

    // Dice si el nombre de usuario está libre. El propio usuario no cuenta (puede dejar el suyo igual)
    public function usuarioDisponible($usuario, $idPropio)
    {
        $sql = "SELECT id FROM usuarios WHERE usuario = ? AND id <> ?";
        return $this->database->queryOne($sql, [$usuario, (int)$idPropio]) === null;
    }

    // Guarda los datos editados. La foto solo se cambia si viene una nueva ($foto distinta de null)
    public function actualizar($id, array $datos, $foto = null)
    {
        $sql = "UPDATE usuarios
                SET usuario = ?, nombre_completo = ?, fecha_nacimiento = ?, genero = ?, pais = ?, ciudad = ?";
        $valores = [$datos['usuario'], $datos['nombre_completo'], $datos['fecha_nacimiento'],
                    $datos['genero'], $datos['pais'], $datos['ciudad']];

        if ($foto !== null) {
            $sql .= ", foto_perfil = ?";
            $valores[] = $foto;
        }

        $sql .= " WHERE id = ?";
        $valores[] = (int)$id;
        $this->database->execute($sql, $valores);
    }

    // Trae el hash de la contraseña actual para poder compararlo
    public function obtenerHashPassword($id)
    {
        $fila = $this->database->queryOne("SELECT password FROM usuarios WHERE id = ?", [(int)$id]);
        return $fila['password'] ?? null;
    }

    // Guarda la contraseña nueva (ya convertida en hash)
    public function cambiarPassword($id, $hash)
    {
        $this->database->execute("UPDATE usuarios SET password = ? WHERE id = ?", [$hash, (int)$id]);
    }
}
