<?php

namespace app\controller;

use Log;
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

        $categorias = $this->model->obtenerCategorias();
        foreach ($categorias as &$categoria) {
        }
        unset($categoria);

        $this->noGuardarEnCache();
        $this->render->renderiza("categorias", [
            "categorias" => $categorias,
            "limite_segundos" => PartidaModel::TIEMPO_PREGUNTA_MS / 1000,
            "sin_preguntas" => Request::get('error') === 'sin_preguntas',
        ]);
    }

    // Recibe la categoría elegida y empieza la partida con puntaje 0
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

        $categoria = $this->model->obtenerCategoria((int)Request::post('categoria', 0));
        if ($categoria === null) { // No eligió nada o mandó una categoría que no existe
            Redirect::to('/partida/iniciar');
            return;
        }

        $pregunta = $this->model->elegirPregunta($usuarioId, $categoria['id']);
        if ($pregunta === null) { // La categoría no tiene ninguna pregunta activa
            Redirect::to('/partida/iniciar?error=sin_preguntas');
            return;
        }

        // Si otra pestaña se adelantó y ya creó la partida, la base de datos lo rechaza y se sigue con esa
        $this->model->crearPartida($usuarioId, $categoria['id'], $pregunta);
        Redirect::to('/partida/pregunta');
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

        // Empaqueta toda la información y renderiza
        $data = [
            "categoria" => $pregunta['categoria'],
            "color" => $pregunta['color'],
            "enunciado" => $pregunta['enunciado'],
            "respuestas" => $respuestas,
            "puntaje" => $partida['puntaje'],
            "token" => $partida['pregunta_token'],
            "limite_segundos" => PartidaModel::TIEMPO_PREGUNTA_MS / 1000,
            "restante_ms" => $restanteMs,
            "restante_segundos" => (int)ceil($restanteMs / 1000),
        ];

        $this->noGuardarEnCache(); // El botón "atrás" no puede mostrar una pregunta vieja
        $this->render->renderiza("pregunta", $data);
    }

    // Recibe la respuesta. Todas las reglas se validan acá, en el servidor
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

        // REGLA: cada pregunta tiene un código de un solo uso. Si no coincide, es una pregunta vieja
        // (otra pestaña, botón "atrás", formulario viejo): se ignora sin castigo y vuelve a la pregunta actual.
        $token = (string)Request::post('token', '');
        if (!hash_equals($partida['pregunta_token'], $token)) {
            Redirect::to('/partida/pregunta');
            return;
        }

        $preguntaId = (int)$partida['pregunta_id'];
        $respuestaId = (int)Request::post('respuesta', 0);
        $transcurrido = $this->model->milisegundosTranscurridos($partida);

        // REGLA: sin respuesta. Solo vale como "se acabó el tiempo" si el reloj del servidor realmente llegó al límite
        if ($respuestaId === 0) {
            if ($transcurrido >= PartidaModel::TIEMPO_PREGUNTA_MS - 1000) {
                $this->model->finalizar($partida['id'], 'tiempo');
                Redirect::to('/partida/resultado');
            } else {
                Redirect::to('/partida/pregunta'); // Mandó el formulario antes de tiempo: se ignora
            }
            return;
        }

        // REGLA: una sola respuesta por pregunta. Gasta el código: si dos peticiones llegan juntas, solo una pasa
        if (!$this->model->consumirPregunta($partida['id'], $token)) {
            Redirect::to('/partida/pregunta');
            return;
        }

        if ($this->model->esCorrecta($preguntaId, $respuestaId)) {
            $this->model->sumarPunto($partida['id']);
            $partida['puntaje']++;
            if (!$this->model->asignarSiguientePregunta($partida)) { // Se quedó sin preguntas
                Redirect::to('/partida/resultado');
                return;
            }
            Redirect::to('/partida/pregunta');
            return;
        }

        // Se equivocó: la partida termina
        $this->model->finalizar($partida['id'], 'error');
        Redirect::to('/partida/resultado');
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

        $data = [
            "icono" => $icono,
            "titulo" => $titulo,
            "motivo_error" => $motivo === 'error',
            "motivo_tiempo" => $motivo === 'tiempo',
            "motivo_sin_preguntas" => $motivo === 'sin_preguntas',
            "puntaje" => (int)$partida['puntaje'],
            "puntos_texto" => (int)$partida['puntaje'] === 1 ? 'punto' : 'puntos',
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

    // Le dice al navegador que no guarde la página, así "atrás" y "adelante" siempre piden la versión actual al servidor
    private function noGuardarEnCache()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
