<?php

namespace app\controller;

use Redirect;
use Request;

class PartidaController
{
    private $render;
    private $model;

    public function __construct($render, $model)
    {
        $this->render = $render;
        $this->model = $model;
    }

    // Limpia los datos de partidas anteriores y muestra las categorías para elegir
    public function iniciar()
    {
        unset($_SESSION['partida'], $_SESSION['resultado']);
        $this->render->renderiza("categorias", ["categorias" => $this->model->obtenerCategorias()]);
    }

    // Recibe la categoría elegida y empieza la partida con puntaje 0
    public function elegir()
    {
        $categoria = $this->model->obtenerCategoria((int)Request::post('categoria', 0));

        if ($categoria === null) { // No eligió nada o mandó una categoría que no existe
            Redirect::to('/partida/iniciar');
            return;
        }

        $_SESSION['partida'] = [ // Guarda en la sesión los datos iniciales
            'puntaje' => 0,
            'categoria_id' => $categoria['id'],
            'pregunta_actual' => null,
        ];
        unset($_SESSION['resultado']); // Borra resultados anteriores y redirige al juego
        Redirect::to('/partida/pregunta');
    }

    // Muestra la pregunta actual. Si todavía no hay una, elige una de la categoría que el usuario no haya visto
    public function pregunta()
    {
        if (!isset($_SESSION['partida'])) {
            Redirect::to('/partida/iniciar');
            return;
        }
        $partida = $_SESSION['partida'];
        $usuarioId = $_SESSION['usuario_id'];
        $categoriaId = $partida['categoria_id'];

        if ($partida['pregunta_actual'] === null) { // Si no hay pregunta asignada le pide al modelo una al azar
            $pregunta = $this->model->obtenerPreguntaAleatoria($usuarioId, $categoriaId);

            if ($pregunta === null) { // Ya vio todas las de la categoría: se reinicia ese historial y se elige de nuevo
                $this->model->reiniciarVistas($usuarioId, $categoriaId);
                $pregunta = $this->model->obtenerPreguntaAleatoria($usuarioId, $categoriaId);
            }

            if ($pregunta === null) { // La categoría no tiene ninguna pregunta activa
                $_SESSION['resultado'] = ['puntaje' => $partida['puntaje'], 'sin_preguntas' => true];
                unset($_SESSION['partida']);
                Redirect::to('/partida/resultado');
                return;
            }
            // Registra que el usuario ya está viendo la pregunta para no repetirla
            $this->model->marcarComoVista($usuarioId, $pregunta['id']);
            $_SESSION['partida']['pregunta_actual'] = $pregunta['id'];
        } else {
            // Si recarga la página, ve la misma pregunta (no puede saltearla)
            $pregunta = $this->model->obtenerPregunta($partida['pregunta_actual']);
        }

        // A cada respuesta le asignamos su letra A, B, C, D
        $letras = ['A', 'B', 'C', 'D'];
        $respuestas = [];
        foreach ($this->model->obtenerRespuestas($pregunta['id']) as $i => $respuesta) {
            $respuestas[] = [
                "id" => $respuesta['id'],
                "letra" => $letras[$i] ?? '',
                "texto" => $respuesta['texto'],
            ];
        }
        // Empaqueta toda la información y renderiza
        $data = [
            "categoria" => $pregunta['categoria'],
            "color" => $pregunta['color'],
            "enunciado" => $pregunta['enunciado'],
            "respuestas" => $respuestas,
            "puntaje" => $partida['puntaje'],
        ];
        $this->render->renderiza("pregunta", $data);
    }

    // Recibe la respuesta elegida: si es correcta suma 1 punto y sigue, si no termina la partida
    public function responder()
    {
        $partida = $_SESSION['partida'] ?? null;

        if ($partida === null || $partida['pregunta_actual'] === null) {
            Redirect::to('/partida/pregunta');
            return;
        }

        $preguntaId = $partida['pregunta_actual'];
        $respuestaId = (int)Request::post('respuesta', 0);

        // Si la rta es correcta
        if ($this->model->esCorrecta($preguntaId, $respuestaId)) {
            $_SESSION['partida']['puntaje']++;
            $_SESSION['partida']['pregunta_actual'] = null;
            Redirect::to('/partida/pregunta');
            return;
        }

        // Se equivocó: guardamos el resultado y cerramos la partida
        $pregunta = $this->model->obtenerPregunta($preguntaId);
        $_SESSION['resultado'] = [
            'puntaje' => $partida['puntaje'],
            'sin_preguntas' => false,
            'pregunta' => $pregunta['enunciado'],
            'correcta' => $this->model->obtenerTextoCorrecta($preguntaId),
        ];
        unset($_SESSION['partida']);
        Redirect::to('/partida/resultado');
    }

    // Muestra el final de la partida: respuesta correcta y puntos ganados
    public function resultado()
    {
        $resultado = $_SESSION['resultado'] ?? null;

        if ($resultado === null) {
            Redirect::to('/home');
            return;
        }

        $resultado['puntos_texto'] = $resultado['puntaje'] === 1 ? 'punto' : 'puntos';
        $this->render->renderiza("resultado", $resultado);
    }
}