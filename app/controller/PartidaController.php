<?php

namespace app\controller;

use Redirect;
use Request;
use app\model\PartidaModel;

class PartidaController
{
    private $render;
    private $model;

    public function __construct($render, $model)
    {
        $this->render = $render;
        $this->model = $model;
    }

    // Todo el estado de la partida está en la base de datos, así que da igual desde qué pestaña, navegador o
    // dispositivo se entre: el usuario siempre ve LA MISMA partida, la misma pregunta y el mismo tiempo restante.

    // Muestra las categorías para elegir. Si el usuario ya está jugando, lo manda de vuelta a su partida
    public function iniciar()
    {
        $usuarioId = $_SESSION['usuario_id'];

        if ($this->model->partidaActiva($usuarioId) !== null) { // No puede empezar otra partida mientras tiene una abierta
            Redirect::to('/partida/pregunta');
            return;
        }
        if ($this->model->tieneResultadoPendiente($usuarioId)) { // Antes de jugar de nuevo ve cómo terminó la anterior
            Redirect::to('/partida/resultado');
            return;
        }

        $this->noGuardarEnCache();
        $categorias = $this->model->obtenerCategoriasConPreguntas();
        $this->render->renderiza("categorias", array_merge($this->datosRuleta($categorias), [
            "limite_segundos" => PartidaModel::TIEMPO_PREGUNTA_MS / 1000,
            "sin_preguntas" => count($categorias) === 0 || Request::get('error') === 'sin_preguntas',
        ]));
    }

    // Gira la ruleta: el SERVIDOR sortea la categoría (todas con la misma probabilidad) y crea la partida
    public function elegir()
    {
        $usuarioId = $_SESSION['usuario_id'];

        if ($this->model->partidaActiva($usuarioId) !== null) { // Ya está jugando (por ejemplo desde otra pestaña): vuelve a esa partida
            Redirect::to('/partida/pregunta');
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/partida/iniciar');
            return;
        }
        // Cada partida nueva empieza con el historial vacío: las preguntas solo se evitan repetir dentro de una misma partida
        $this->model->borrarHistorialVistas($usuarioId);

        $categoria = $this->model->sortearCategoria();
        $pregunta = $categoria === null
            ? null
            : $this->model->obtenerPreguntaAleatoria($usuarioId, $categoria['id']);
        if ($pregunta === null) { // No hay ninguna categoría con preguntas activas
            Redirect::to('/partida/iniciar?error=sin_preguntas');
            return;
        }

        // Si otra pestaña se adelantó y ya creó la partida, la base de datos lo rechaza y se sigue con esa
        $this->model->crearPartida($usuarioId, $categoria['id'], $pregunta);
        Redirect::to('/partida/ruleta');
    }

    // Muestra la ruleta girando hasta la categoría que YA sorteó el servidor. El reloj todavía no corre
    public function ruleta()
    {
        $usuarioId = $_SESSION['usuario_id'];
        $partida = $this->model->partidaActiva($usuarioId);

        if ($partida === null) {
            Redirect::to($this->model->tieneResultadoPendiente($usuarioId) ? '/partida/resultado' : '/partida/iniciar');
            return;
        }
        if (!$this->model->esperandoPregunta($partida)) { // Ya vio la primera pregunta: no se vuelve a girar
            Redirect::to('/partida/pregunta');
            return;
        }

        $categorias = $this->model->obtenerCategoriasConPreguntas();
        $indice = 0;
        foreach ($categorias as $i => $categoria) {
            if ((int)$categoria['id'] === (int)$partida['categoria_id']) {
                $indice = $i;
            }
        }
        $elegida = $categorias[$indice] ?? ['nombre' => '', 'color' => '#546E7A'];

        $this->noGuardarEnCache();
        $this->render->renderiza("ruleta", array_merge($this->datosRuleta($categorias), [
            "indice" => $indice,
            "categoria" => $elegida['nombre'],
            "color" => $elegida['color'],
        ]));
    }

    // Muestra la pregunta actual de la partida en curso
    public function pregunta()
    {
        $usuarioId = $_SESSION['usuario_id'];
        $partida = $this->model->partidaActiva($usuarioId);

        if ($partida === null) { // No hay partida (o se le acabó el tiempo recién)
            Redirect::to($this->model->tieneResultadoPendiente($usuarioId) ? '/partida/resultado' : '/partida/iniciar');
            return;
        }

        if ($partida['pregunta_token'] === null) { // Caso raro: justo entre una pregunta y la siguiente
            $this->model->asignarSiguientePregunta($partida);
            Redirect::to('/partida/pregunta');
            return;
        }

        if ($this->model->esperandoPregunta($partida)) { // Es la primera vez que ve la pregunta: recién ahora empiezan los 20 s
            $partida = $this->model->iniciarReloj($partida);
        }

        $pregunta = $this->model->obtenerPregunta($partida['pregunta_id']);

        // A cada respuesta le asignamos su letra A, B, C, D
        $letras = ['A', 'B', 'C', 'D'];
        $respuestas = [];
        foreach ($this->model->obtenerRespuestas($pregunta['id'], $partida['pregunta_token']) as $i => $respuesta) {
            $respuestas[] = [
                "id" => $respuesta['id'],
                "letra" => $letras[$i] ?? '',
                "texto" => $respuesta['texto'],
            ];
        }

        $restanteMs = $this->model->milisegundosRestantes($partida);

        // Información de racha diaria y multiplicador aplicado a esta partida
        $rachaDiaria = $this->model->obtenerRachaDiaria($usuarioId);
        $multiplicadorActivo = (float)($partida['multiplicador_aplicado'] ?? $rachaDiaria['multiplicador']);
        $rachaActualPartida = (int)($partida['racha_actual'] ?? 0);

        // Aviso de "terminaste la categoría, seguís con otra"
        $aviso = $_SESSION['aviso_categoria'] ?? null;
        unset($_SESSION['aviso_categoria']);
        if ($aviso !== null && $aviso['token'] !== $partida['pregunta_token']) {
            $aviso = null;
        }

        // Empaqueta toda la información y renderiza
        $data = [
            "categoria" => $pregunta['categoria'],
            "color" => $pregunta['color'],
            "cambio_categoria" => $aviso !== null,
            "categoria_anterior" => $aviso['anterior'] ?? '',
            "enunciado" => $pregunta['enunciado'],
            "respuestas" => $respuestas,
            "puntaje" => PartidaModel::formatearPuntos($partida['puntaje']),
            "racha_actual" => $rachaActualPartida,
            "tiene_racha" => $rachaActualPartida > 0,
            "multiplicador" => number_format($multiplicadorActivo, 2, ',', '.') . 'x',
            "tiene_multiplicador" => $multiplicadorActivo > 1.00,
            "racha_dias" => $rachaDiaria['racha_dias'],
            "token" => $partida['pregunta_token'],
            "limite_segundos" => PartidaModel::TIEMPO_PREGUNTA_MS / 1000,
            "restante_ms" => $restanteMs,
            "restante_segundos" => (int)ceil($restanteMs / 1000),
        ];

        $this->noGuardarEnCache(); // El botón "atrás" no puede mostrar una pregunta vieja
        $this->render->renderiza("pregunta", $data);
    }

    // Recibe la respuesta
    public function responder()
    {
        $usuarioId = $_SESSION['usuario_id'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/partida/pregunta');
            return;
        }

        $partida = $this->model->partidaActiva($usuarioId); // Si se venció el tiempo, acá mismo la cierra
        if ($partida === null || $partida['pregunta_token'] === null) {
            Redirect::to('/partida/pregunta');
            return;
        }

        $resultado = $this->model->procesarRespuesta(
            $partida,
            (string)Request::post('token', ''),
            (int)Request::post('respuesta', 0)
        );

        switch ($resultado) {
            case PartidaModel::RESP_CORRECTA:
                $this->avisarSiHuboCambioDeCategoria($usuarioId, $partida);
                Redirect::to('/partida/pregunta');
                break;

            case PartidaModel::RESP_ERROR:
            case PartidaModel::RESP_TIEMPO:
            case PartidaModel::RESP_SIN_PREGUNTAS:
                Redirect::to('/partida/resultado');
                break;

            default: // RESP_IGNORADA
                Redirect::to('/partida/pregunta');
        }
    }

    // Muestra el final de la partida: por qué terminó, la respuesta correcta y los puntos ganados
    public function resultado()
    {
        $usuarioId = $_SESSION['usuario_id'];

        if ($this->model->partidaActiva($usuarioId) !== null) { // Sigue jugando: no puede espiar el resultado
            Redirect::to('/partida/pregunta');
            return;
        }

        $partida = $this->model->ultimaTerminada($usuarioId);
        if ($partida === null) {
            Redirect::to('/home');
            return;
        }
        $this->model->marcarResultadoVisto($partida['id']);

        $motivo = $partida['motivo_fin'];
        $titulos = [
            'error' => ['❌', 'Respuesta incorrecta'],
            'tiempo' => ['⏰', '¡Se acabó el tiempo!'],
            'sin_preguntas' => ['🏁', 'No hay más preguntas'],
        ];
        [$icono, $titulo] = $titulos[$motivo] ?? ['🏁', 'Fin de la partida'];

        $rachaDiaria = $this->model->obtenerRachaDiaria($usuarioId);

        $data = [
            "icono" => $icono,
            "titulo" => $titulo,
            "motivo_error" => $motivo === 'error',
            "motivo_tiempo" => $motivo === 'tiempo',
            "motivo_sin_preguntas" => $motivo === 'sin_preguntas',
            "puntaje" => PartidaModel::formatearPuntos($partida['puntaje']),
            "puntos_texto" => (int)$partida['puntaje'] === 1 ? 'punto' : 'puntos',
            "respuestas_correctas" => (int)($partida['respuestas_correctas'] ?? 0),
            "mejor_racha" => (int)($partida['mejor_racha'] ?? 0),
            "racha_dias" => $rachaDiaria['racha_dias'],
            "multiplicador_aplicado" => number_format((float)($partida['multiplicador_aplicado'] ?? 1.0), 2, ',', '.') . 'x',
            "limite_segundos" => PartidaModel::TIEMPO_PREGUNTA_MS / 1000,
        ];

        // Solo se muestra la respuesta correcta cuando la partida terminó por error o por tiempo
        if (in_array($motivo, ['error', 'tiempo'], true) && $partida['pregunta_id'] !== null) {
            $pregunta = $this->model->obtenerPregunta($partida['pregunta_id']);
            $data["pregunta"] = $pregunta['enunciado'];
            $data["correcta"] = $this->model->obtenerTextoCorrecta($partida['pregunta_id']);
            $data["mostrar_correcta"] = true;
        }

        $this->noGuardarEnCache();
        $this->render->renderiza("resultado", $data);
    }

    // Si la partida pasó a otra categoría, lo anota en la sesión para avisarlo en la pantalla de esa pregunta
    private function avisarSiHuboCambioDeCategoria($usuarioId, $partidaAnterior)
    {
        $actual = $this->model->partidaActiva($usuarioId);
        if ($actual === null || $actual['pregunta_token'] === null) {
            return;
        }
        if ((int)$actual['categoria_id'] === (int)$partidaAnterior['categoria_id']) {
            return;
        }
        $anterior = $this->model->obtenerCategoria($partidaAnterior['categoria_id']);
        $_SESSION['aviso_categoria'] = [
            'anterior' => $anterior['nombre'] ?? '',
            'token' => $actual['pregunta_token'],
        ];
    }

    // Cada categoría usa un dibujo de public/img/iconos/. El archivo se llama como la categoría, sin tildes,
    // en minúsculas y con guiones: "Ciencia y Tecnología" -> ciencia-y-tecnologia.svg
    // Si no existe el archivo devuelve vacío y ese gajo queda sin ícono
    private function iconoCategoria($nombre)
    {
        $nombre = strtr(mb_strtolower($nombre), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $archivo = trim(preg_replace('/[^a-z0-9]+/', '-', $nombre), '-');
        return is_file(__DIR__ . '/../../public/img/iconos/' . $archivo . '.svg') ? $archivo : '';
    }

    // Arma los datos para dibujar la ruleta: un gajo por categoría, todos del mismo tamaño
    private function datosRuleta($categorias)
    {
        $total = max(1, count($categorias));
        $angulo = 360 / $total;
        $tramos = [];
        $gajos = [];
        foreach ($categorias as $i => $categoria) {
            $centro = ($i + 0.5) * $angulo;
            $tramos[] = $categoria['color'] . ' ' . round($i * $angulo, 2) . 'deg ' . round(($i + 1) * $angulo, 2) . 'deg';
            $gajos[] = [
                "nombre" => $categoria['nombre'],
                "icono" => $this->iconoCategoria($categoria['nombre']),
                "centro" => round($centro, 2),
                "invertido" => $centro > 180, // En la mitad izquierda el texto se da vuelta para que se lea derecho
            ];
        }
        return [
            "gradiente" => 'conic-gradient(' . implode(', ', $tramos) . ')',
            "gajos" => $gajos,
            "total" => $total,
            "grados_gajo" => round($angulo, 2),
        ];
    }

    // Le dice al navegador que no guarde la página, así "atrás" y "adelante" siempre piden la versión actual al servidor
    private function noGuardarEnCache()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
