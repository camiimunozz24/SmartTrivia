<?php

namespace app\controller;

use app\model\PartidaModel;

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
        $rachaDias = 0;
        $multiplicador = '1,00x';
        $ultimasPartidas = [];

        try {
            if (isset($_SESSION['usuario_id'])) {
                $usuarioId = (int)$_SESSION['usuario_id'];
                $enCurso = $this->partidaModel->partidaActiva($usuarioId) !== null;
                $puntajeTotal = $this->partidaModel->puntajeTotal($usuarioId);

                $rachaDiaria = $this->partidaModel->obtenerRachaDiaria($usuarioId);
                $rachaDias = $rachaDiaria['racha_dias'];
                $multiplicador = number_format($rachaDiaria['multiplicador'], 2, ',', '.') . 'x';

                $filas = $this->partidaModel->obtenerUltimasPartidas($usuarioId, 5);
                foreach ($filas as $fila) {
                    $motivo = $fila['motivo_fin'];
                    $motivoTexto = 'Completada';
                    if ($motivo === 'error') $motivoTexto = 'Error';
                    elseif ($motivo === 'tiempo') $motivoTexto = 'Tiempo agotado';
                    elseif ($motivo === 'sin_preguntas') $motivoTexto = 'Completada';

                    $ultimasPartidas[] = [
                        'id' => $fila['id'],
                        'puntaje' => PartidaModel::formatearPuntos($fila['puntaje']),
                        'respuestas_correctas' => (int)$fila['respuestas_correctas'],
                        'mejor_racha' => (int)$fila['mejor_racha'],
                        'categoria' => $fila['categoria'] ?? 'Varias',
                        'color' => $fila['color'] ?? '#1E56A0',
                        'motivo_texto' => $motivoTexto,
                        'fecha' => date('d/m/Y H:i', strtotime($fila['fecha_inicio']))
                    ];
                }
            }
        } catch (\Throwable $e) {
            $enCurso = false;
            $puntajeTotal = 0;
        }

        $this->render->renderiza("home", [
            "usuario" => $_SESSION['usuario'] ?? '',
            "puntaje_total" => PartidaModel::formatearPuntos($puntajeTotal),
            "racha_dias" => $rachaDias,
            "tiene_racha_dias" => $rachaDias > 0,
            "multiplicador" => $multiplicador,
            "ultimas_partidas" => $ultimasPartidas,
            "hay_partidas" => count($ultimasPartidas) > 0,
            "en_curso" => $enCurso,
            "limite_segundos" => PartidaModel::TIEMPO_PREGUNTA_MS / 1000,
        ]);
    }
}
