<?php

namespace app\controller;

use Request;
use app\model\PartidaModel;

class RankingController
{
    private $render;
    private $model;

    public function __construct($render, $model)
    {
        $this->render = $render;
        $this->model = $model;
    }

    public function show()
    {
        $tipo = Request::get('tipo', 'puntos_mes');
        $usuarioActualId = (int)($_SESSION['usuario_id'] ?? 0);

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $mesActual = (int)date('n');
        $anioActual = (int)date('Y');
        $nombreMes = ($meses[$mesActual] ?? '') . ' ' . $anioActual;

        $titulo = '';
        $descripcion = '';
        $esColumnaPuntos = true;

        switch ($tipo) {
            case 'racha_mes':
                $filas = $this->model->obtenerRankingRachaMensual($mesActual, $anioActual);
                $titulo = "Mayor Racha del Mes";
                $descripcion = "Los mejores récords de aciertos consecutivos conseguidos durante {$nombreMes}.";
                $esColumnaPuntos = false;
                break;

            case 'puntos_total':
                $filas = $this->model->obtenerRankingPuntosHistorico();
                $titulo = "Ranking Histórico General";
                $descripcion = "Puntajes acumulados totales de todos los jugadores desde el inicio.";
                $esColumnaPuntos = true;
                break;

            case 'racha_total':
                $filas = $this->model->obtenerRankingRachaHistorico();
                $titulo = "Mayor Racha Histórica";
                $descripcion = "Las mayores rachas de respuestas correctas consecutivas registradas en la historia de SmartTrivia.";
                $esColumnaPuntos = false;
                break;

            case 'puntos_mes':
            default:
                $tipo = 'puntos_mes';
                $filas = $this->model->obtenerRankingPuntosMensual($mesActual, $anioActual);
                $titulo = "Ranking Mensual por Puntos";
                $descripcion = "Puntaje acumulado durante {$nombreMes}. ¡Se renueva el primer día de cada mes!";
                $esColumnaPuntos = true;
                break;
        }

        $ranking = [];
        foreach ($filas as $i => $fila) {
            $puesto = $i + 1;
            $valorFormato = $esColumnaPuntos
                ? PartidaModel::formatearPuntos($fila['puntaje']) . ' pts'
                : (int)$fila['mejor_racha'] . ' seguidas';

            $ubicacion = trim(($fila['ciudad'] ? $fila['ciudad'] . ', ' : '') . $fila['pais']);

            $ranking[] = [
                'puesto' => $puesto,
                'es_primero' => $puesto === 1,
                'es_segundo' => $puesto === 2,
                'es_tercero' => $puesto === 3,
                'medalla' => $puesto === 1 ? '🥇' : ($puesto === 2 ? '🥈' : ($puesto === 3 ? '🥉' : null)),
                'id' => $fila['id'],
                'usuario' => $fila['usuario'],
                'nombre_completo' => $fila['nombre_completo'],
                'foto_perfil' => $fila['foto_perfil'],
                'tiene_foto' => !empty($fila['foto_perfil']),
                'ubicacion' => $ubicacion !== '' ? $ubicacion : 'Ubicación no especificada',
                'partidas' => (int)$fila['partidas'],
                'valor_formato' => $valorFormato,
                'es_usuario_actual' => (int)$fila['id'] === $usuarioActualId,
            ];
        }

        $data = [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'nombre_mes' => $nombreMes,
            'es_columna_puntos' => $esColumnaPuntos,
            'ranking' => $ranking,
            'hay_jugadores' => count($ranking) > 0,
            'tab_puntos_mes' => $tipo === 'puntos_mes',
            'tab_racha_mes' => $tipo === 'racha_mes',
            'tab_puntos_total' => $tipo === 'puntos_total',
            'tab_racha_total' => $tipo === 'racha_total',
        ];

        $this->render->renderiza("ranking", $data);
    }
}
