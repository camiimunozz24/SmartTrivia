<?php

namespace app\model;

// Reportes de pregunta hechos por los usuarios
class ReporteModel
{
    private $database;

    const MOTIVO_PARTIDA = 'Reportada desde el resultado de la partida';

    public function __construct($database){
        $this->database = $database;
    }

    // Registra el reporte, salvo que ese usuario ya tenga uno PENDIENTE para esa pregunta.
    // Develve true si lo registro y false si era un duplicado
    public function crear($preguntaId, $usuarioId, $motivo = self::MOTIVO_PARTIDA){
        $filas = $this->database->execute(
            "INSERT INTO reportes (pregunta_id, usuario_id, motivo)
            SELECT ?, ?, ? FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM reportes WHERE pregunta_id = ? AND usuario_id = ? AND estado = 'pendiente')",
            [(int)$preguntaId, (int)$usuarioId, $motivo, (int)$preguntaId, (int)$usuarioId]
        );
        return $filas === 1;
        }
    // Preguntas activas con reportes pendientes: una fila por pregunta, las mas reportadas primero
    public function obtenerResumenPendientes()
    {
        return $this->database->queryAll(
            "SELECT r.pregunta_id, COUNT(*) AS cantidad, MAX(r.fecha) AS ultimo
             FROM reportes r
             JOIN preguntas p ON p.id = r.pregunta_id
             WHERE r.estado = 'pendiente' AND p.estado = 'activa'
             GROUP BY r.pregunta_id
             ORDER BY cantidad DESC, ultimo ASC"
        );
    }

    // Marca como resueltos todos los reportes pendientes de la pregunta. Devuelve cuantos eran
        public function resolverPendientes($preguntaId)
    {
        return $this->database->execute(
            "UPDATE reportes SET estado = 'resuelto' WHERE pregunta_id = ? AND estado = 'pendiente'",
            [(int)$preguntaId]
        );
    }

}
