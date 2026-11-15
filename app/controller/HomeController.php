<?php

namespace app\controller;

class HomeController
{
    private $render;
    private $partidaModel;

    public function __construct($render, $partidaModel)
    {
        $this->render = $render;
        $this->partidaModel = $partidaModel;
    }

    public function show()
    {
        $enCurso = false;
        $puntajeTotal = 0;
        try { // Si la tabla partidas todavía no existe, el inicio igual tiene que mostrarse
            if (isset($_SESSION['usuario_id'])) {
                $enCurso = $this->partidaModel->partidaActiva($_SESSION['usuario_id']) !== null;
                $puntajeTotal = $this->partidaModel->puntajeTotal($_SESSION['usuario_id']);
            }
        } catch (\Throwable $e) {
            $enCurso = false;
            $puntajeTotal = 0;
        }

        $this->render->renderiza("home", [
            "usuario" => $_SESSION['usuario'] ?? '',
            "puntaje_total" => \app\model\PartidaModel::formatearPuntos($puntajeTotal),
            "en_curso" => $enCurso,
            "limite_segundos" => \app\model\PartidaModel::TIEMPO_PREGUNTA_MS / 1000,
        ]);
    }
}
