<?php

namespace app\model;

class PartidaModel
{
    private $database;

    // ---- REGLAS DEL JUEGO (todas se controlan en el servidor, el navegador solo las muestra) ----
    const TIEMPO_PREGUNTA_MS = 20000;  // 20 segundos para responder cada pregunta
    const GRACIA_MS = 2000;            // Margen por demora de red: se acepta hasta 22 s medidos en el servidor
    const ABANDONO_MS = 60000;         // Partida trabada (sin pregunta, o girando la ruleta sin llegar a ver la pregunta) por más de 60 s: se da por terminada

    // ---- DIFICULTAD Y PUNTOS ----
    const RESPUESTAS_MINIMAS = 5;      // Con menos respuestas que estas la pregunta cuenta como "fácil"
    const ERROR_MAXIMO_FACIL = 0.30;   // Menos de 30 % de respuestas incorrectas = fácil
    const ERROR_MINIMO_DIFICIL = 0.60; // 60 % o más = difícil (entre las dos = media)
    const PUNTOS = ['facil' => 1.0, 'media' => 1.5, 'dificil' => 2.0];

    // ---- RESULTADOS POSIBLES AL PROCESAR UNA RESPUESTA ----
    const RESP_IGNORADA = 'ignorada';           // Petición vieja o inválida: no pasa nada
    const RESP_CORRECTA = 'correcta';           // Acertó y sigue jugando
    const RESP_ERROR = 'error';                 // Se equivocó: partida terminada
    const RESP_TIEMPO = 'tiempo';               // Se acabó el tiempo: partida terminada
    const RESP_SIN_PREGUNTAS = 'sin_preguntas'; // Acertó y ya no quedan preguntas: partida terminada

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
//    public function obtenerCategorias()
//    {
//        return $this->database->queryAll("SELECT id, nombre, color FROM categorias ORDER BY id");
//    }

    // Categorías que tienen al menos una pregunta activa (son las que entran en la ruleta)
    public function obtenerCategoriasConPreguntas()
    {
        return $this->database->queryAll(
            "SELECT c.id, c.nombre, c.color FROM categorias c
             WHERE EXISTS (SELECT 1 FROM preguntas p WHERE p.categoria_id = c.id AND p.estado = 'activa')
             ORDER BY c.id"
        );
    }

    // Sortea una categoría. Todas tienen la MISMA probabilidad (el sorteo se hace acá, en el servidor)
    public function sortearCategoria()
    {
        $categorias = $this->obtenerCategoriasConPreguntas();
        if (count($categorias) === 0) {
            return null;
        }
        return $categorias[random_int(0, count($categorias) - 1)];
    }

    // Busca y devuelve una categoría por su ID
    public function obtenerCategoria($id)
    {
        return $this->database->queryOne("SELECT id, nombre, color FROM categorias WHERE id = ?", [(int)$id]);
    }

    // Trae la pregunta activa de esa categoría que el usuario NO haya visto, en orden de dificultad:
    // primero las fáciles, después las medias y al final las difíciles. Entre las de la misma dificultad, al azar
    public function obtenerPreguntaAleatoria($usuarioId, $categoriaId)
    {
        // 1 = fácil, 2 = media, 3 = difícil.
        $orden = "CASE
                    WHEN p.veces_respondida < " . self::RESPUESTAS_MINIMAS . " THEN 1
                    WHEN p.veces_incorrecta / p.veces_respondida < " . self::ERROR_MAXIMO_FACIL . " THEN 1
                    WHEN p.veces_incorrecta / p.veces_respondida >= " . self::ERROR_MINIMO_DIFICIL . " THEN 3
                    ELSE 2
                  END";

        $sql = "SELECT p.id, p.enunciado, c.nombre AS categoria, c.color
                FROM preguntas p
                JOIN categorias c ON c.id = p.categoria_id
                WHERE p.estado = 'activa'
                AND p.categoria_id = ?
                AND p.id NOT IN (SELECT pregunta_id FROM preguntas_vistas WHERE usuario_id = ?)
                ORDER BY $orden, RAND() LIMIT 1";
        return $this->database->queryOne($sql, [(int)$categoriaId, (int)$usuarioId]);
    }

    // Elige una pregunta que el usuario no vio. Si ya vio todas las de la categoría, reinicia ese historial y elige de nuevo
//    public function elegirPregunta($usuarioId, $categoriaId)
//    {
//        $pregunta = $this->obtenerPreguntaAleatoria($usuarioId, $categoriaId);
//
//        if ($pregunta === null) {
//            $this->reiniciarVistas($usuarioId, $categoriaId);
//            $pregunta = $this->obtenerPreguntaAleatoria($usuarioId, $categoriaId);
//        }
//        return $pregunta;
//    }

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

    // Reinicia el historial del usuario SOLO en esa categoría: vuelve a empezar desde las preguntas fáciles
//    public function reiniciarVistas($usuarioId, $categoriaId)
//    {
//        $sql = "DELETE FROM preguntas_vistas
//                WHERE usuario_id = ?
//                AND pregunta_id IN (SELECT id FROM preguntas WHERE categoria_id = ?)";
//        $this->database->execute($sql, [(int)$usuarioId, (int)$categoriaId]);
//    }

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
        if ($this->esperandoPregunta($partida)) { // Giró la ruleta pero nunca llegó a ver la pregunta
            return $this->ahoraMs() - abs((int)$partida['pregunta_inicio_ms']) > self::ABANDONO_MS;
        }
        $transcurrido = $this->ahoraMs() - (int)$partida['pregunta_inicio_ms'];

        if ($partida['pregunta_token'] !== null) { // Hay una pregunta esperando respuesta
            return $transcurrido > self::TIEMPO_PREGUNTA_MS + self::GRACIA_MS;
        }
        return $transcurrido > self::ABANDONO_MS; // Sin pregunta asignada por demasiado tiempo
    }

    // Mientras gira la ruleta la partida está "esperando" que se muestre la primera pregunta.
    // En ese tiempo el reloj NO corre: se guarda la hora de creación en NEGATIVO (pregunta_inicio_ms < 0)
    public function esperandoPregunta($partida)
    {
        return $partida['pregunta_inicio_ms'] !== null && (int)$partida['pregunta_inicio_ms'] < 0;
    }

    // Arranca los 20 segundos en el momento en que se muestra la pregunta por primera vez.
    // La condición "pregunta_inicio_ms < 0" hace que solo cuente la primera vez (recargar no reinicia el reloj)
    public function iniciarReloj($partida)
    {
        $this->database->execute(
            "UPDATE partidas SET pregunta_inicio_ms = ? WHERE id = ? AND estado = 'en_curso' AND pregunta_inicio_ms < 0",
            [$this->ahoraMs(), (int)$partida['id']]
        );
        return $this->database->queryOne("SELECT * FROM partidas WHERE id = ?", [(int)$partida['id']]);
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
                [(int)$usuarioId, (int)$categoriaId, (int)$usuarioId, (int)$pregunta['id'], $token, -$this->ahoraMs()] // negativo = el reloj arranca cuando se vea la pregunta
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
    // Primero busca en la misma categoría. Si el usuario ya vio todas sus preguntas, la partida salta a la
    // siguiente categoría que todavía tenga preguntas sin ver. Solo termina cuando no queda ninguna en todo el juego.
    // Devuelve true si se asignó una pregunta
    public function asignarSiguientePregunta($partida)
    {
        $usuarioId = (int)$partida['usuario_id'];
        $categoriaId = (int)$partida['categoria_id'];

        $pregunta = $this->obtenerPreguntaAleatoria($usuarioId, $categoriaId);

        if ($pregunta === null) { // Categoría agotada: pasa a la siguiente
            $siguiente = $this->buscarSiguienteCategoria($usuarioId, $categoriaId);
            if ($siguiente === null) { // No queda ninguna pregunta sin ver en ninguna categoría
                $this->finalizar($partida['id'], 'sin_preguntas');
                return false;
            }
            $categoriaId = (int)$siguiente['categoria']['id'];
            $pregunta = $siguiente['pregunta'];
        }

        // La condición "pregunta_token IS NULL" evita que dos pestañas asignen preguntas distintas a la vez
        $filas = $this->database->execute(
            "UPDATE partidas SET categoria_id = ?, pregunta_id = ?, pregunta_token = ?, pregunta_inicio_ms = ?
             WHERE id = ? AND estado = 'en_curso' AND pregunta_token IS NULL",
            [$categoriaId, (int)$pregunta['id'], bin2hex(random_bytes(16)), $this->ahoraMs(), (int)$partida['id']]
        );
        if ($filas === 1) {
            $this->marcarComoVista($usuarioId, $pregunta['id']);
        }
        return true;
    }

    // Recorre las categorías en el orden de la ruleta, a partir de la que sigue a la actual,
    // y devuelve la primera que tenga una pregunta activa sin ver, junto con esa pregunta. Si no hay ninguna, null
    private function buscarSiguienteCategoria($usuarioId, $categoriaActualId)
    {
        $categorias = $this->obtenerCategoriasConPreguntas(); // Ordenadas por id, igual que la ruleta
        $total = count($categorias);

        $posicion = -1; // Si la actual ya no tiene preguntas activas no figura en la lista y se empieza desde la primera
        foreach ($categorias as $i => $categoria) {
            if ((int)$categoria['id'] === (int)$categoriaActualId) {
                $posicion = $i;
            }
        }

        for ($paso = 1; $paso <= $total; $paso++) {
            $categoria = $categorias[($posicion + $paso) % $total];
            if ((int)$categoria['id'] === (int)$categoriaActualId) { // Dio toda la vuelta: la actual ya se sabe que no tiene
                continue;
            }
            $pregunta = $this->obtenerPreguntaAleatoria($usuarioId, $categoria['id']);
            if ($pregunta !== null) {
                return ['categoria' => $categoria, 'pregunta' => $pregunta];
            }
        }
        return null;
    }

    /**
     * Borra TODO el historial de preguntas vistas del usuario.
     * Es el único reinicio del historial: se llama en elegir() al empezar cada partida.
     * Durante la partida, el historial evita que se repitan preguntas.*/
    public function borrarHistorialVistas($usuarioId)
    {
        $this->database->execute("DELETE FROM preguntas_vistas WHERE usuario_id = ?", [(int)$usuarioId]);
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

    // Aplica TODAS las reglas de una respuesta y devuelve qué pasó.
    public function procesarRespuesta($partida, $token, $respuestaId)
    {
        // Todavía no vio la pregunta (el reloj no empezó)
        if ($this->esperandoPregunta($partida)) {
            return self::RESP_IGNORADA;
        }

        // Cada pregunta tiene un código de un solo uso. Si no coincide, es una pregunta vieja
        if (!hash_equals($partida['pregunta_token'], $token)) {
            return self::RESP_IGNORADA;
        }

        $preguntaId = (int)$partida['pregunta_id'];

        // Sin respuesta: solo vale como "se acabó el tiempo" si el reloj del servidor realmente llegó al límite
        if ($respuestaId === 0) {
            if ($this->milisegundosTranscurridos($partida) >= self::TIEMPO_PREGUNTA_MS - 1000) {
                $this->finalizar($partida['id'], 'tiempo');
                return self::RESP_TIEMPO;
            }
            return self::RESP_IGNORADA; // Mandó el formulario antes de tiempo
        }

        // Una sola respuesta por pregunta: si dos peticiones llegan juntas, solo una pasa
        if (!$this->consumirPregunta($partida['id'], $token)) {
            return self::RESP_IGNORADA;
        }

        // La dificultad se toma ANTES de anotar esta respuesta
        $dificultad = $this->obtenerDificultad($preguntaId);
        $correcta = $this->esCorrecta($preguntaId, $respuestaId);
        $this->registrarRespuesta($preguntaId, $correcta);

        if (!$correcta) {
            $this->finalizar($partida['id'], 'error');
            return self::RESP_ERROR;
        }

        $this->sumarPuntos($partida['id'], self::puntosPorDificultad($dificultad));

        if (!$this->asignarSiguientePregunta($partida)) {
            return self::RESP_SIN_PREGUNTAS; // asignarSiguientePregunta ya cerró la partida
        }
        return self::RESP_CORRECTA;
    }

    // ------------------------------------------------------------------
    // DIFICULTAD Y PUNTAJE
    // ------------------------------------------------------------------

    // Devuelve 'facil', 'media' o 'dificil' según cuántas veces se respondió mal la pregunta.
    // Cuanto más se falla, más difícil. Con pocas respuestas registradas todavía es 'facil'
    public static function calcularDificultad($respondidas, $incorrectas)
    {
        $respondidas = (int)$respondidas;
        if ($respondidas < self::RESPUESTAS_MINIMAS || (int)$incorrectas / $respondidas < self::ERROR_MAXIMO_FACIL) {
            return 'facil';
        }
        if ((int)$incorrectas / $respondidas >= self::ERROR_MINIMO_DIFICIL) {
            return 'dificil';
        }
        return 'media';
    }

    // Dificultad actual de una pregunta, calculada con sus contadores
    public function obtenerDificultad($preguntaId)
    {
        $fila = $this->database->queryOne(
            "SELECT veces_respondida, veces_incorrecta FROM preguntas WHERE id = ?",
            [(int)$preguntaId]
        );
        return self::calcularDificultad($fila['veces_respondida'] ?? 0, $fila['veces_incorrecta'] ?? 0);
    }

    // Puntos que suma un acierto según la dificultad: fácil 1, media 1.5, difícil 2
    public static function puntosPorDificultad($dificultad)
    {
        return self::PUNTOS[$dificultad] ?? self::PUNTOS['facil'];
    }

    // Anota la respuesta en la estadística de la pregunta. Solo se llama cuando el usuario realmente respondió:
    // quedarse sin tiempo no cuenta
    public function registrarRespuesta($preguntaId, $fueCorrecta)
    {
        $this->database->execute(
            "UPDATE preguntas
             SET veces_respondida = veces_respondida + 1, veces_incorrecta = veces_incorrecta + ?
             WHERE id = ?",
            [$fueCorrecta ? 0 : 1, (int)$preguntaId]
        );
    }

    // Suma puntos al puntaje de la partida
    public function sumarPuntos($partidaId, $puntos)
    {
        $this->database->execute(
            "UPDATE partidas SET puntaje = puntaje + ? WHERE id = ?",
            [number_format((float)$puntos, 1, '.', ''), (int)$partidaId]
        );
    }

    // Suma de los puntajes de todas las partidas del usuario
    public function puntajeTotal($usuarioId)
    {
        $fila = $this->database->queryOne(
            "SELECT COALESCE(SUM(puntaje), 0) AS total FROM partidas WHERE usuario_id = ?",
            [(int)$usuarioId]
        );
        return (float)($fila['total'] ?? 0);
    }

    // Da formato a los puntos para mostrarlos
    public static function formatearPuntos($puntos)
    {
        $texto = number_format((float)$puntos, 1, ',', '.');
        return substr($texto, -2) === ',0' ? substr($texto, 0, -2) : $texto;
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
