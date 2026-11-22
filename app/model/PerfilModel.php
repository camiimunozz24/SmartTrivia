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
        try {
            $fila = $this->database->queryOne(
                "SELECT 
                    COALESCE(SUM(puntaje), 0) AS puntaje, 
                    COUNT(*) AS partidas,
                    COALESCE(SUM(respuestas_correctas), 0) AS correctas,
                    COALESCE(MAX(mejor_racha), 0) AS mayor_racha
                 FROM partidas 
                 WHERE usuario_id = ? AND estado = 'terminada'",
                [(int)$usuarioId]
            );
            $usuario = $this->database->queryOne(
                "SELECT racha_dias, ultimo_dia_acierto, mejor_racha_historica FROM usuarios WHERE id = ?",
                [(int)$usuarioId]
            );
        } catch (\Throwable $e) {
            $fila = null;
            $usuario = null;
        }

        $mayorRacha = max((int)($fila['mayor_racha'] ?? 0), (int)($usuario['mejor_racha_historica'] ?? 0));

        // Racha de días de acceso activo (misma lógica que PartidaModel::obtenerRachaDiaria)
        $rachaDias = (int)($usuario['racha_dias'] ?? 0);
        $ultimo = $usuario['ultimo_dia_acierto'] ?? null;
        $hoy = date('Y-m-d');
        $ayer = date('Y-m-d', strtotime('-1 day'));
        $jugoHoy = ($ultimo === $hoy);
        $jugoAyer = ($ultimo === $ayer);
        if (!$jugoHoy && !$jugoAyer) {
            $rachaDias = 0;
        }
        $diaParaMultiplicador = $jugoHoy ? $rachaDias : ($jugoAyer ? ($rachaDias + 1) : 1);
        $multiplicador = \app\model\PartidaModel::calcularMultiplicadorRacha($diaParaMultiplicador);

        return [
            "puntaje_acumulado" => (int)round((float)($fila['puntaje'] ?? 0)),
            "puntaje_acumulado_formato" => \app\model\PartidaModel::formatearPuntos($fila['puntaje'] ?? 0),
            "partidas_jugadas" => (int)($fila['partidas'] ?? 0),
            "preguntas_correctas" => (int)($fila['correctas'] ?? 0),
            "mayor_racha" => $mayorRacha,
            "racha_dias" => $rachaDias,
            "multiplicador" => number_format($multiplicador, 2, ',', '.') . 'x'
        ];
    }

    // Historial de partidas jugadas con detalle para mostrar en el perfil o historial extendido.
    // Si $limite es null, trae el historial completo sin recorte.
    public function obtenerHistorialPartidas($usuarioId, $limite = 3)
    {
        try {
            $sql = "SELECT p.id, p.puntaje, p.respuestas_correctas, p.mejor_racha, p.multiplicador_aplicado, p.motivo_fin,
                           p.fecha_inicio, p.fecha_fin, c.nombre AS categoria, c.color
                    FROM partidas p
                    LEFT JOIN categorias c ON c.id = p.categoria_id
                    WHERE p.usuario_id = ? AND p.estado = 'terminada'
                    ORDER BY p.id DESC";
            if ($limite !== null) {
                $sql .= " LIMIT " . (int)$limite;
            }
            $filas = $this->database->queryAll($sql, [(int)$usuarioId]);
            $resultado = [];
            foreach ($filas as $fila) {
                $motivo = $fila['motivo_fin'];
                $motivoTexto = 'Completada';
                if ($motivo === 'error') $motivoTexto = 'Error';
                elseif ($motivo === 'tiempo') $motivoTexto = 'Tiempo agotado';
                elseif ($motivo === 'sin_preguntas') $motivoTexto = 'Sin más preguntas';

                $resultado[] = [
                    'id' => $fila['id'],
                    'puntaje' => \app\model\PartidaModel::formatearPuntos($fila['puntaje']),
                    'respuestas_correctas' => (int)$fila['respuestas_correctas'],
                    'mejor_racha' => (int)$fila['mejor_racha'],
                    'multiplicador' => number_format((float)($fila['multiplicador_aplicado'] ?? 1.0), 2, ',', '.') . 'x',
                    'categoria' => $fila['categoria'] ?? 'Varias',
                    'color' => $fila['color'] ?? '#1E56A0',
                    'motivo_texto' => $motivoTexto,
                    'fecha' => date('d/m/Y H:i', strtotime($fila['fecha_inicio']))
                ];
            }
            return $resultado;
        } catch (\Throwable $e) {
            return [];
        }
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
