<?php

namespace app\model;

class PartidaModel
{
    private $database;

    // Define cuantas preguntas vistas no se borran al reiniciar el historial
    const PREGUNTAS_RECIENTES = 3;

    public function __construct($database)
    {
        $this->database = $database;
    }

    // Trae todas las categorías de la bd para que el usuario elija
    public function obtenerCategorias()
    {
        return $this->database->queryAll("SELECT id, nombre, color FROM categorias ORDER BY id");
    }

    // Busca y devuelve una categoría por su ID
    public function obtenerCategoria($id)
    {
        return $this->database->queryOne("SELECT id, nombre, color FROM categorias WHERE id = ?", [(int)$id]);
    }

    // Trae una pregunta activa al azar de esa categoría que el usuario NO haya visto
    public function obtenerPreguntaAleatoria($usuarioId, $categoriaId)
    {
        $sql = "SELECT p.id, p.enunciado, c.nombre AS categoria, c.color
                FROM preguntas p
                JOIN categorias c ON c.id = p.categoria_id
                WHERE p.estado = 'activa'
                AND p.categoria_id = ?
                AND p.id NOT IN (SELECT pregunta_id FROM preguntas_vistas WHERE usuario_id = ?)
                ORDER BY RAND() LIMIT 1";
        return $this->database->queryOne($sql, [(int)$categoriaId, (int)$usuarioId]);
    }

    // Trae una pregunta puntual por su ID (para volver a mostrarla si se recarga la página)
    public function obtenerPregunta($id)
    {
        $sql = "SELECT p.id, p.enunciado, c.nombre AS categoria, c.color
                FROM preguntas p
                JOIN categorias c ON c.id = p.categoria_id
                WHERE p.id = ?";
        return $this->database->queryOne($sql, [(int)$id]);
    }

    // Registra que el usuario ya vio esta pregunta
    public function marcarComoVista($usuarioId, $preguntaId)
    {
        $sql = "INSERT IGNORE INTO preguntas_vistas (usuario_id, pregunta_id) VALUES (?, ?)";
        $this->database->execute($sql, [(int)$usuarioId, (int)$preguntaId]);
    }

    // Reinicia el historial del usuario SOLO en esa categoría, conservando las últimas vistas
    public function reiniciarVistas($usuarioId, $categoriaId)
    {
        // Cuántas preguntas activas tiene la categoría
        $fila = $this->database->queryOne(
            "SELECT COUNT(*) AS total FROM preguntas WHERE categoria_id = ? AND estado = 'activa'",
            [(int)$categoriaId]
        );

        // Se conservan como mucho las recientes, pero siempre tiene que quedar al menos una pregunta disponible
        $conservar = min(self::PREGUNTAS_RECIENTES, (int)$fila['total'] - 1);

        // Busca la última de las preguntas que se conservan (la más antigua de las recientes)
        $limite = null;
        if ($conservar > 0) {
            $sql = "SELECT pv.id
                    FROM preguntas_vistas pv
                    JOIN preguntas p ON p.id = pv.pregunta_id
                    WHERE pv.usuario_id = ? AND p.categoria_id = ?
                    ORDER BY pv.id DESC
                    LIMIT 1 OFFSET " . ($conservar - 1);
            $limite = $this->database->queryOne($sql, [(int)$usuarioId, (int)$categoriaId]);
        }

        if ($limite === null) { // Nada para conservar: se borran todas las vistas de esa categoría
            $sql = "DELETE pv FROM preguntas_vistas pv
                    JOIN preguntas p ON p.id = pv.pregunta_id
                    WHERE pv.usuario_id = ? AND p.categoria_id = ?";
            $this->database->execute($sql, [(int)$usuarioId, (int)$categoriaId]);
            return;
        }

        // Se borran las anteriores a ese límite. Quedan solo las más recientes
        $sql = "DELETE pv FROM preguntas_vistas pv
                JOIN preguntas p ON p.id = pv.pregunta_id
                WHERE pv.usuario_id = ? AND p.categoria_id = ? AND pv.id < ?";
        $this->database->execute($sql, [(int)$usuarioId, (int)$categoriaId, (int)$limite['id']]);
    }

    // Trae las respuestas de una pregunta en orden aleatorio. NO incluye cuál es la correcta
    public function obtenerRespuestas($preguntaId)
    {
        $sql = "SELECT id, texto FROM respuestas WHERE pregunta_id = ? ORDER BY RAND()";
        return $this->database->queryAll($sql, [(int)$preguntaId]);
    }

    // Revisa si la respuesta elegida pertenece a la pregunta y es la correcta
    public function esCorrecta($preguntaId, $respuestaId)
    {
        $sql = "SELECT es_correcto FROM respuestas WHERE id = ? AND pregunta_id = ?";
        $fila = $this->database->queryOne($sql, [(int)$respuestaId, (int)$preguntaId]);
        return $fila !== null && (int)$fila['es_correcto'] === 1;
    }

    // Devuelve el texto de la respuesta correcta
    public function obtenerTextoCorrecta($preguntaId)
    {
        $sql = "SELECT texto FROM respuestas WHERE pregunta_id = ? AND es_correcto = 1 LIMIT 1";
        $fila = $this->database->queryOne($sql, [(int)$preguntaId]);
        return $fila['texto'] ?? '';
    }
}