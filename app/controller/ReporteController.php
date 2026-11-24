<?php

namespace app\controller;

use Redirect;

// "Reportar pregunta": se usa desde la pantalla de resultado cuando el usuario perdio.
// La pregunta reportada NO viene del navegador: es la de la ultima partida terminada del usuario,
// asi nadie puede reportar una pregunta que no le toco.
class ReporteController
{
    private $reporteModel;
    private $partidaModel;

    public function __construct($reporteModel, $partidaModel)
    {
        $this->reporteModel = $reporteModel;
        $this->partidaModel = $partidaModel;
    }

    public function reportar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/partida/resultado');
            return;
        }

        $usuarioId = (int)$_SESSION['usuario_id'];
        $partida = $this->partidaModel->ultimaTerminada($usuarioId);

        if ($partida === null || $partida['pregunta_id'] === null || !in_array($partida['motivo_fin'], ['error', 'tiempo'], true)) {
            $_SESSION['reporte_aviso'] = ['tipo' => 'error', 'texto' => 'No hay ninguna pregunta para reportar.'];
            Redirect::to('/partida/resultado');
            return;
        }

        if ($this->reporteModel->crear($partida['pregunta_id'], $usuarioId)) {
            $_SESSION['reporte_aviso'] = ['tipo' => 'ok', 'texto' => '¡Gracias! Registramos tu reporte y un editor va a revisar la pregunta.'];
        } else {
            $_SESSION['reporte_aviso'] = ['tipo' => 'info', 'texto' => 'Ya reportaste esta pregunta. Un editor la está revisando.'];
        }
        Redirect::to('/partida/resultado');
    }
}