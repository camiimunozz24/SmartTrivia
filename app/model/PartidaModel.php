<?php

namespace app\model;

class PartidaModel
{
    private $database;

    // Define cuantas preguntas vistas no se borran al reiniciar el historial
    const PREGUNTAS_RECIENTES = 3;

    // ---- REGLAS DEL JUEGO (todas se controlan en el servidor, el navegador solo las muestra) ----
    const TIEMPO_PREGUNTA_MS = 20000;  // 20 segundos para responder cada pregunta
    const GRACIA_MS = 2000;            // Margen por demora de red: se acepta hasta 22 s medidos en el servidor
    const ABANDONO_MS = 60000;         // Partida trabada sin pregunta por más de 60 s: se da por terminada

    public function __construct($database)
    {
        $this->database = $database;
    }

    // Hora actual en milisegundos (se usa para medir los 20 segundos)
    private function ahoraMs()
    {
        return (int)round(microtime(true) * 1000);
    }

    // ------------------------------------------------------------------
    // CATEGORIAS Y PREGUNTAS
    // ------------------------------------------------------------------

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

    // Elige una pregunta que el usuario no vio. Si ya vio todas las de la categoría, reinicia ese historial y elige de nuevo
    public function elegirPregunta($usuarioId, $categoriaId)
    {
        $pregunta = $this->obtenerPreguntaAleatoria($usuarioId, $categoriaId);

        if ($pregunta === null) {
            $this->reiniciarVistas($usuarioId, $categoriaId);
            $pregunta = $this->obtenerPreguntaAleatoria($usuarioId, $categoriaId);
        }
        return $pregunta;
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
            $sql = "DELETE FROM preguntas_vistas
                    WHERE usuario_id = ?
                    AND pregunta_id IN (SELECT id FROM preguntas WHERE categoria_id = ?)";
            $this->database->execute($sql, [(int)$usuarioId, (int)$categoriaId]);
            return;
        }

        // Se borran las anteriores a ese límite. Quedan solo las más recientes
        $sql = "DELETE FROM preguntas_vistas
                WHERE usuario_id = ? AND id < ?
                AND pregunta_id IN (SELECT id FROM preguntas WHERE categoria_id = ?)";
        $this->database->execute($sql, [(int)$usuarioId, (int)$limite['id'], (int)$categoriaId]);
    }

    // Trae las respuestas de una pregunta. NO incluye cuál es la correcta.
    // El orden es "al azar" pero siempre el mismo para una misma pregunta mostrada (depende del token),
    // así al recargar la página las opciones no se mueven de lugar.
    public function obtenerRespuestas($preguntaId, $token = '')
    {
        $respuestas = $this->database->queryAll("SELECT id, texto FROM respuestas WHERE pregunta_id = ? ORDER BY id", [(int)$preguntaId]);
        usort($respuestas, function ($a, $b) use ($token) {
            return strcmp(md5($token . $a['id']), md5($token . $b['id']));
        });
        return $respuestas;
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

    // ------------------------------------------------------------------
    // PARTIDAS (todo el estado vive en la base de datos, no en el navegador ni en la sesión)
    // ------------------------------------------------------------------

    // Devuelve la partida en curso del usuario, o null si no tiene.
    // Si el tiempo de la pregunta ya se agotó, la cierra en el momento (aunque el usuario haya cerrado el navegador)
    public function partidaActiva($usuarioId)
    {
        $partida = $this->database->queryOne(
            "SELECT * FROM partidas WHERE usuario_id = ? AND estado = 'en_curso' ORDER BY id DESC LIMIT 1",
            [(int)$usuarioId]
        );
        if ($partida === null) {
            return null;
        }

        if ($this->estaVencida($partida)) {
            $this->finalizar($partida['id'], 'tiempo');
            return null;
        }
        return $partida;
    }

    // Dice si a la partida se le terminó el tiempo (mira el reloj del servidor)
    public function estaVencida($partida)
    {
        if ($partida['pregunta_inicio_ms'] === null) {
            return false;
        }
        $transcurrido = $this->ahoraMs() - (int)$partida['pregunta_inicio_ms'];

        if ($partida['pregunta_token'] !== null) { // Hay una pregunta esperando respuesta
            return $transcurrido > self::TIEMPO_PREGUNTA_MS + self::GRACIA_MS;
        }
        return $transcurrido > self::ABANDONO_MS; // Sin pregunta asignada por demasiado tiempo
    }

    // Milisegundos que lleva mostrada la pregunta actual
    public function milisegundosTranscurridos($partida)
    {
        return $this->ahoraMs() - (int)$partida['pregunta_inicio_ms'];
    }

    // Milisegundos que le quedan al usuario para responder (nunca menos que 0)
    public function milisegundosRestantes($partida)
    {
        return max(0, self::TIEMPO_PREGUNTA_MS - $this->milisegundosTranscurridos($partida));
    }

    // Crea la partida junto con su primera pregunta en una sola operación.
    // Devuelve false si el usuario YA tenía una partida en curso (la base de datos lo impide con el índice único)
    public function crearPartida($usuarioId, $categoriaId, $pregunta)
    {
        $token = bin2hex(random_bytes(16));
        try {
            $this->database->execute(
                "INSERT INTO partidas (usuario_id, categoria_id, puntaje, estado, activa_usuario_id, pregunta_id, pregunta_token, pregunta_inicio_ms)
                 VALUES (?, ?, 0, 'en_curso', ?, ?, ?, ?)",
                [(int)$usuarioId, (int)$categoriaId, (int)$usuarioId, (int)$pregunta['id'], $token, $this->ahoraMs()]
            );
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') { // Violación de clave única: ya hay una partida en curso
                return false;
            }
            throw $e;
        }
        $this->marcarComoVista($usuarioId, $pregunta['id']);
        return true;
    }

    // Le asigna a la partida la siguiente pregunta (con un token nuevo y el reloj en cero).
    // Si no quedan preguntas, termina la partida. Devuelve true si se asignó una pregunta
    public function asignarSiguientePregunta($partida)
    {
        $pregunta = $this->elegirPregunta($partida['usuario_id'], $partida['categoria_id']);
        if ($pregunta === null) {
            $this->finalizar($partida['id'], 'sin_preguntas');
            return false;
        }

        // La condición "pregunta_token IS NULL" evita que dos pestañas asignen preguntas distintas a la vez
        $filas = $this->database->execute(
            "UPDATE partidas SET pregunta_id = ?, pregunta_token = ?, pregunta_inicio_ms = ?
             WHERE id = ? AND estado = 'en_curso' AND pregunta_token IS NULL",
            [(int)$pregunta['id'], bin2hex(random_bytes(16)), $this->ahoraMs(), (int)$partida['id']]
        );
        if ($filas === 1) {
            $this->marcarComoVista($partida['usuario_id'], $pregunta['id']);
        }
        return true;
    }

    // "Gasta" el token de la pregunta: solo UNA petición puede conseguirlo, así cada pregunta se responde una sola vez.
    // Devuelve true si esta petición fue la que lo consiguió
    public function consumirPregunta($partidaId, $token)
    {
        $filas = $this->database->execute(
            "UPDATE partidas SET pregunta_token = NULL
             WHERE id = ? AND estado = 'en_curso' AND pregunta_token = ?",
            [(int)$partidaId, $token]
        );
        return $filas === 1;
    }

    // Suma un punto a la partida
    public function sumarPunto($partidaId)
    {
        $this->database->execute("UPDATE partidas SET puntaje = puntaje + 1 WHERE id = ?", [(int)$partidaId]);
    }

    // Cierra la partida. Libera "activa_usuario_id" para que el usuario pueda empezar otra.
    public function finalizar($partidaId, $motivo)
    {
        $this->database->execute(
            "UPDATE partidas
             SET estado = 'terminada', activa_usuario_id = NULL, pregunta_token = NULL,
                 motivo_fin = ?, fecha_fin = CURRENT_TIMESTAMP
             WHERE id = ? AND estado = 'en_curso'",
            [$motivo, (int)$partidaId]
        );
    }

    // Última partida terminada del usuario (para mostrar el resultado)
    public function ultimaTerminada($usuarioId)
    {
        return $this->database->queryOne(
            "SELECT * FROM partidas WHERE usuario_id = ? AND estado = 'terminada' ORDER BY id DESC LIMIT 1",
            [(int)$usuarioId]
        );
    }

    // Dice si el usuario tiene una partida terminada cuyo resultado todavía no vio (por ejemplo, se le acabó el tiempo)
    public function tieneResultadoPendiente($usuarioId)
    {
        $fila = $this->database->queryOne(
            "SELECT id FROM partidas WHERE usuario_id = ? AND estado = 'terminada' AND resultado_visto = 0 LIMIT 1",
            [(int)$usuarioId]
        );
        return $fila !== null;
    }

    // Marca el resultado como visto
    public function marcarResultadoVisto($partidaId)
    {
        $this->database->execute("UPDATE partidas SET resultado_visto = 1 WHERE id = ?", [(int)$partidaId]);
    }
}
