<?php

namespace app\model;

class AdminModel
{
    private $database;

    const ROLES_VALIDOS = ['usuario', 'editor', 'admin'];

    public function __construct($database)
    {
        $this->database = $database;
    }

    // Estadísticas generales del sistema para visibilidad total del administrador
    public function obtenerResumenSistema()
    {
        $resumen = [
            'total_usuarios' => 0,
            'total_editores' => 0,
            'total_admins' => 0,
            'total_partidas' => 0,
            'total_preguntas' => 0,
        ];

        try {
            $u = $this->database->queryOne(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN rol = 'editor' THEN 1 ELSE 0 END) AS editores,
                    SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) AS admins
                 FROM usuarios"
            );
            $resumen['total_usuarios'] = (int)($u['total'] ?? 0);
            $resumen['total_editores'] = (int)($u['editores'] ?? 0);
            $resumen['total_admins'] = (int)($u['admins'] ?? 0);
        } catch (\Throwable $e) {}

        try {
            $p = $this->database->queryOne("SELECT COUNT(*) AS total FROM partidas WHERE estado = 'terminada'");
            $resumen['total_partidas'] = (int)($p['total'] ?? 0);
        } catch (\Throwable $e) {}

        try {
            $q = $this->database->queryOne("SELECT COUNT(*) AS total FROM preguntas WHERE estado = 'activa'");
            $resumen['total_preguntas'] = (int)($q['total'] ?? 0);
        } catch (\Throwable $e) {}

        return $resumen;
    }

    // Lista todos los usuarios ordenados por jerarquía de rol
    public function obtenerUsuarios()
    {
        return $this->database->queryAll(
            "SELECT id, usuario, nombre_completo, email, rol, estado, fecha_registro
             FROM usuarios
             ORDER BY FIELD(rol, 'admin', 'editor', 'usuario'), id ASC"
        );
    }

    // Cambia el rol de un usuario ('usuario', 'editor', 'admin')
    public function cambiarRol($usuarioId, $nuevoRol)
    {
        if (!in_array($nuevoRol, self::ROLES_VALIDOS, true)) {
            return false;
        }
        $this->database->execute(
            "UPDATE usuarios SET rol = ? WHERE id = ?",
            [$nuevoRol, (int)$usuarioId]
        );
        return true;
    }

    // Verifica si un nombre de usuario o email ya existe
    public function existeUsuarioOEmail($usuario, $email)
    {
        $fila = $this->database->queryOne(
            "SELECT id FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1",
            [$usuario, $email]
        );
        return $fila !== null;
    }

    // Crea un nuevo usuario con rol 'editor' ya activo
    public function crearEditor($usuario, $email, $nombreCompleto, $password)
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->database->execute(
            "INSERT INTO usuarios
                (usuario, email, password, nombre_completo, fecha_nacimiento, genero, pais, ciudad, foto_perfil, estado, rol)
             VALUES (?, ?, ?, ?, '2000-01-01', 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires', '', 'activo', 'editor')",
            [$usuario, $email, $hash, $nombreCompleto]
        );
    }

    // Elimina un usuario y sus registros asociados
    public function eliminarUsuario($usuarioId)
    {
        $id = (int)$usuarioId;
        try {
            $this->database->execute("DELETE FROM preguntas_vistas WHERE usuario_id = ?", [$id]);
            $this->database->execute("DELETE FROM partidas WHERE usuario_id = ?", [$id]);
        } catch (\Throwable $e) {}

        $this->database->execute("DELETE FROM usuarios WHERE id = ?", [$id]);
    }

    // -------------------------------------------------------------
    // HELPERS DE FILTRADO POR FECHA (Día, Semana, Mes, Año, Todos)
    // -------------------------------------------------------------
    private function condicionFecha($columna, $periodo)
    {
        switch ($periodo) {
            case 'dia':
                return "DATE({$columna}) = CURDATE()";
            case 'semana':
                return "{$columna} >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'mes':
                return "{$columna} >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'anio':
                return "{$columna} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            case 'todos':
            default:
                return "1=1";
        }
    }
    public function obtenerMetricasPeriodo($periodo = 'mes')
    {
        $condPartidas   = $this->condicionFecha('fecha_inicio', $periodo);
        $condUsuarios   = $this->condicionFecha('fecha_registro', $periodo);
        $condPreguntas  = $this->condicionFecha('fecha_creacion', $periodo);
        $metricas = [
            'total_jugadores'    => 0, // Total histórico de usuarios registrados
            'usuarios_nuevos'    => 0, // Registrados en el período
            'partidas_jugadas'   => 0, // Partidas terminadas en el período
            'preguntas_en_juego' => 0, // Total preguntas activas actuales
            'preguntas_creadas'  => 0, // Preguntas creadas en el período
            'tasa_acierto_global'=> 0, // Porcentaje de respuestas correctas en el período
        ];
        // Total de jugadores históricos (con rol usuario o en general)
        try {
            $uTotal = $this->database->queryOne("SELECT COUNT(*) AS c FROM usuarios WHERE rol = 'usuario'");
            $metricas['total_jugadores'] = (int)($uTotal['c'] ?? 0);
        } catch (\Throwable $e) {}
        // Usuarios nuevos en ese periodo
        try {
            $uNuevos = $this->database->queryOne("SELECT COUNT(*) AS c FROM usuarios WHERE {$condUsuarios}");
            $metricas['usuarios_nuevos'] = (int)($uNuevos['c'] ?? 0);
        } catch (\Throwable $e) {}
        // Partidas jugadas en ese periodo
        try {
            $pTerm = $this->database->queryOne("SELECT COUNT(*) AS c FROM partidas WHERE estado = 'terminada' AND {$condPartidas}");
            $metricas['partidas_jugadas'] = (int)($pTerm['c'] ?? 0);
        } catch (\Throwable $e) {}
        // Preguntas en el juego (activas)
        try {
            $qAct = $this->database->queryOne("SELECT COUNT(*) AS c FROM preguntas WHERE estado = 'activa'");
            $metricas['preguntas_en_juego'] = (int)($qAct['c'] ?? 0);
        } catch (\Throwable $e) {}
        // Preguntas creadas en ese periodo
        try {
            $qCre = $this->database->queryOne("SELECT COUNT(*) AS c FROM preguntas WHERE {$condPreguntas}");
            $metricas['preguntas_creadas'] = (int)($qCre['c'] ?? 0);
        } catch (\Throwable $e) {}
        // Tasa de acierto global del sistema en ese periodo
        try {
            $tasa = $this->database->queryOne(
                "SELECT 
                    SUM(respuestas_correctas) AS correctas,
                    SUM(respuestas_correctas + CASE WHEN motivo_fin IN ('error', 'tiempo') THEN 1 ELSE 0 END) AS total
                 FROM partidas 
                 WHERE estado = 'terminada' AND {$condPartidas}"
            );
            $correctas = (int)($tasa['correctas'] ?? 0);
            $total = (int)($tasa['total'] ?? 0);
            $metricas['tasa_acierto_global'] = $total > 0 ? round(($correctas / $total) * 100, 1) : 0;
        } catch (\Throwable $e) {}
        return $metricas;
    }


    public function obtenerUsuariosPorPais($periodo = 'todos')
    {
        $cond = $this->condicionFecha('fecha_registro', $periodo);
        $filas = $this->database->queryAll(
            "SELECT 
                COALESCE(NULLIF(TRIM(pais), ''), 'Sin especificar') AS pais,
                COUNT(*) AS total
             FROM usuarios
             WHERE {$cond}
             GROUP BY pais
             ORDER BY total DESC
             LIMIT 10"
        );
        $totalGeneral = array_sum(array_column($filas, 'total')) ?: 1;
        foreach ($filas as &$f) {
            $f['porcentaje'] = round(($f['total'] / $totalGeneral) * 100, 1);
        }
        unset($f);
        return $filas;
    }

    public function obtenerUsuariosPorSexo($periodo = 'todos')
    {
        $cond = $this->condicionFecha('fecha_registro', $periodo);
        $filas = $this->database->queryAll(
            "SELECT 
                COALESCE(NULLIF(TRIM(genero), ''), 'Prefiero no cargarlo') AS genero,
                COUNT(*) AS total
             FROM usuarios
             WHERE {$cond}
             GROUP BY genero
             ORDER BY total DESC"
        );
        $totalGeneral = array_sum(array_column($filas, 'total')) ?: 1;
        foreach ($filas as &$f) {
            $f['porcentaje'] = round(($f['total'] / $totalGeneral) * 100, 1);
        }
        unset($f);
        return $filas;
    }


//usuarios por edad
    public function obtenerUsuariosPorEdad($periodo = 'todos')
    {
        $cond = $this->condicionFecha('fecha_registro', $periodo);
        $res = $this->database->queryOne(
            "SELECT
                COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) < 18 THEN 1 ELSE 0 END), 0) AS menores,
                COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 18 AND 64 THEN 1 ELSE 0 END), 0) AS medio,
                COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) >= 65 THEN 1 ELSE 0 END), 0) AS jubilados
             FROM usuarios
             WHERE fecha_nacimiento IS NOT NULL 
               AND fecha_nacimiento <> '0000-00-00'
               AND {$cond}"
        );
        $menores   = (int)($res['menores'] ?? 0);
        $medio     = (int)($res['medio'] ?? 0);
        $jubilados = (int)($res['jubilados'] ?? 0);
        $total     = ($menores + $medio + $jubilados) ?: 1;
        return [
            [
                'grupo'      => 'Menores',
                'rango'      => 'Menos de 18 años',
                'total'      => $menores,
                'porcentaje' => round(($menores / $total) * 100, 1),
                'color'      => '#3b82f6', // azul
            ],
            [
                'grupo'      => 'Medio / Adultos',
                'rango'      => '18 a 64 años',
                'total'      => $medio,
                'porcentaje' => round(($medio / $total) * 100, 1),
                'color'      => '#10b981', // verde
            ],
            [
                'grupo'      => 'Jubilados / Adultos mayores',
                'rango'      => '65 años o más',
                'total'      => $jubilados,
                'porcentaje' => round(($jubilados / $total) * 100, 1),
                'color'      => '#f59e0b', // naranja
            ],
        ];
    }

    // porcentaje de preguntas respondidas correctamente por usuario.

    public function obtenerEfectividadUsuarios($periodo = 'mes', $limite = 10)
    {
        $cond = $this->condicionFecha('p.fecha_inicio', $periodo);
        $filas = $this->database->queryAll(
            "SELECT 
                u.id,
                u.usuario,
                u.nombre_completo,
                COUNT(p.id) AS partidas_jugadas,
                COALESCE(SUM(p.respuestas_correctas), 0) AS correctas,
                COALESCE(SUM(p.respuestas_correctas + CASE WHEN p.motivo_fin IN ('error', 'tiempo') THEN 1 ELSE 0 END), 0) AS total_respondidas,
                ROUND(
                    (COALESCE(SUM(p.respuestas_correctas), 0) / 
                     NULLIF(SUM(p.respuestas_correctas + CASE WHEN p.motivo_fin IN ('error', 'tiempo') THEN 1 ELSE 0 END), 0)
                    ) * 100, 1
                ) AS porcentaje_acierto
             FROM usuarios u
             JOIN partidas p ON p.usuario_id = u.id
             WHERE p.estado = 'terminada' AND {$cond}
             GROUP BY u.id, u.usuario, u.nombre_completo
             HAVING total_respondidas > 0
             ORDER BY porcentaje_acierto DESC, correctas DESC
             LIMIT " . (int)$limite
        );
        return $filas;
    }



    public function obtenerEvolucionPartidas($periodo = 'mes')
    {
        $cond = $this->condicionFecha('fecha_inicio', $periodo);
        return $this->database->queryAll(
            "SELECT 
                DATE_FORMAT(fecha_inicio, '%d/%m') AS fecha,
                COUNT(*) AS total
             FROM partidas
             WHERE estado = 'terminada' AND {$cond}
             GROUP BY DATE(fecha_inicio), DATE_FORMAT(fecha_inicio, '%d/%m')
             ORDER BY DATE(fecha_inicio) ASC
             LIMIT 15"
        );
    }


    public function obtenerPreguntasPorCategoria()
    {
        return $this->database->queryAll(
            "SELECT 
                c.nombre AS categoria,
                c.color,
                COUNT(p.id) AS total
             FROM categorias c
             LEFT JOIN preguntas p ON p.categoria_id = c.id AND p.estado = 'activa'
             GROUP BY c.id, c.nombre, c.color
             ORDER BY total DESC"
        );
    }

}
