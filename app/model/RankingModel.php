<?php

namespace app\model;

class RankingModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    // Ranking mensual por puntos (se reinicia automáticamente al cambiar de mes)
    public function obtenerRankingPuntosMensual($mes = null, $anio = null, $limite = 50)
    {
        $mes = $mes !== null ? (int)$mes : (int)date('n');
        $anio = $anio !== null ? (int)$anio : (int)date('Y');

        $sql = "SELECT u.id, u.usuario, u.nombre_completo, u.foto_perfil, u.pais, u.ciudad,
                       COALESCE(SUM(p.puntaje), 0) AS puntaje,
                       COUNT(p.id) AS partidas,
                       COALESCE(SUM(p.respuestas_correctas), 0) AS correctas,
                       COALESCE(MAX(p.mejor_racha), 0) AS mejor_racha
                FROM usuarios u
                JOIN partidas p ON p.usuario_id = u.id
                WHERE p.estado = 'terminada'
                  AND MONTH(p.fecha_inicio) = ? AND YEAR(p.fecha_inicio) = ?
                GROUP BY u.id, u.usuario, u.nombre_completo, u.foto_perfil, u.pais, u.ciudad
                ORDER BY puntaje DESC, correctas DESC, partidas ASC
                LIMIT " . (int)$limite;

        return $this->database->queryAll($sql, [$mes, $anio]);
    }

    // Ranking mensual por mayor racha de respuestas correctas
    public function obtenerRankingRachaMensual($mes = null, $anio = null, $limite = 50)
    {
        $mes = $mes !== null ? (int)$mes : (int)date('n');
        $anio = $anio !== null ? (int)$anio : (int)date('Y');

        $sql = "SELECT u.id, u.usuario, u.nombre_completo, u.foto_perfil, u.pais, u.ciudad,
                       COALESCE(MAX(p.mejor_racha), 0) AS mejor_racha,
                       COALESCE(SUM(p.puntaje), 0) AS puntaje,
                       COUNT(p.id) AS partidas
                FROM usuarios u
                JOIN partidas p ON p.usuario_id = u.id
                WHERE p.estado = 'terminada'
                  AND MONTH(p.fecha_inicio) = ? AND YEAR(p.fecha_inicio) = ?
                GROUP BY u.id, u.usuario, u.nombre_completo, u.foto_perfil, u.pais, u.ciudad
                HAVING mejor_racha > 0
                ORDER BY mejor_racha DESC, puntaje DESC
                LIMIT " . (int)$limite;

        return $this->database->queryAll($sql, [$mes, $anio]);
    }

    // Ranking histórico general por puntos acumulados totales
    public function obtenerRankingPuntosHistorico($limite = 50)
    {
        $sql = "SELECT u.id, u.usuario, u.nombre_completo, u.foto_perfil, u.pais, u.ciudad,
                       COALESCE(SUM(p.puntaje), 0) AS puntaje,
                       COUNT(p.id) AS partidas,
                       COALESCE(SUM(p.respuestas_correctas), 0) AS correctas,
                       COALESCE(MAX(p.mejor_racha), 0) AS mejor_racha
                FROM usuarios u
                JOIN partidas p ON p.usuario_id = u.id
                WHERE p.estado = 'terminada'
                GROUP BY u.id, u.usuario, u.nombre_completo, u.foto_perfil, u.pais, u.ciudad
                ORDER BY puntaje DESC, correctas DESC, partidas ASC
                LIMIT " . (int)$limite;

        return $this->database->queryAll($sql);
    }

    // Ranking histórico general por mayor racha de aciertos
    public function obtenerRankingRachaHistorico($limite = 50)
    {
        $sql = "SELECT u.id, u.usuario, u.nombre_completo, u.foto_perfil, u.pais, u.ciudad,
                       GREATEST(COALESCE(MAX(p.mejor_racha), 0), u.mejor_racha_historica) AS mejor_racha,
                       COALESCE(SUM(p.puntaje), 0) AS puntaje,
                       COUNT(p.id) AS partidas
                FROM usuarios u
                JOIN partidas p ON p.usuario_id = u.id
                WHERE p.estado = 'terminada'
                GROUP BY u.id, u.usuario, u.nombre_completo, u.foto_perfil, u.pais, u.ciudad, u.mejor_racha_historica
                HAVING mejor_racha > 0
                ORDER BY mejor_racha DESC, puntaje DESC
                LIMIT " . (int)$limite;

        return $this->database->queryAll($sql);
    }

    // Obtener la posición del usuario en el ranking mensual de puntos
    public function obtenerPosicionUsuarioMensual($usuarioId, $mes = null, $anio = null)
    {
        $mes = $mes !== null ? (int)$mes : (int)date('n');
        $anio = $anio !== null ? (int)$anio : (int)date('Y');

        $fila = $this->database->queryOne(
            "SELECT COALESCE(SUM(puntaje), 0) AS puntaje 
             FROM partidas 
             WHERE usuario_id = ? AND estado = 'terminada' AND MONTH(fecha_inicio) = ? AND YEAR(fecha_inicio) = ?",
            [(int)$usuarioId, $mes, $anio]
        );
        $puntaje = (float)($fila['puntaje'] ?? 0);
        if ($puntaje <= 0) {
            return null;
        }

        $pos = $this->database->queryOne(
            "SELECT COUNT(*) AS puesto FROM (
                SELECT usuario_id, SUM(puntaje) AS total
                FROM partidas
                WHERE estado = 'terminada' AND MONTH(fecha_inicio) = ? AND YEAR(fecha_inicio) = ?
                GROUP BY usuario_id
                HAVING total > ?
            ) AS mejores",
            [$mes, $anio, $puntaje]
        );

        return ((int)($pos['puesto'] ?? 0)) + 1;
    }
}
