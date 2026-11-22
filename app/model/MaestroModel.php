<?php

namespace app\model;

class MaestroModel
{
    private $database;

    // Control Maestro puede asignar usuario, editor o admin (no puede quitarse a sí mismo el rol maestro)
    const ROLES_ASIGNABLES = ['usuario', 'editor', 'admin'];

    public function __construct($database)
    {
        $this->database = $database;
    }

    // Estadísticas globales de usuarios para Control Maestro
    public function obtenerResumenUsuarios()
    {
        $resumen = [
            'total_usuarios' => 0,
            'usuarios_activos' => 0,
            'usuarios_baneados' => 0,
            'total_editores' => 0,
            'total_admins' => 0,
        ];

        try {
            $u = $this->database->queryOne(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) AS activos,
                    SUM(CASE WHEN estado = 'baneado' THEN 1 ELSE 0 END) AS baneados,
                    SUM(CASE WHEN rol = 'editor' THEN 1 ELSE 0 END) AS editores,
                    SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) AS admins
                 FROM usuarios"
            );
            $resumen['total_usuarios'] = (int)($u['total'] ?? 0);
            $resumen['usuarios_activos'] = (int)($u['activos'] ?? 0);
            $resumen['usuarios_baneados'] = (int)($u['baneados'] ?? 0);
            $resumen['total_editores'] = (int)($u['editores'] ?? 0);
            $resumen['total_admins'] = (int)($u['admins'] ?? 0);
        } catch (\Throwable $e) {}

        return $resumen;
    }

    // Historial completo de usuarios con cantidad de partidas jugadas
    public function obtenerHistorialUsuarios()
    {
        return $this->database->queryAll(
            "SELECT u.id, u.usuario, u.nombre_completo, u.email, u.rol, u.estado, u.fecha_registro,
                    (SELECT COUNT(*) FROM partidas p WHERE p.usuario_id = u.id AND p.estado = 'terminada') AS partidas_jugadas
             FROM usuarios u
             ORDER BY FIELD(u.rol, 'maestro', 'admin', 'editor', 'usuario'), u.fecha_registro DESC"
        );
    }

    // Lista específica de editores y administradores para acceso rápido
    public function obtenerStaff()
    {
        return $this->database->queryAll(
            "SELECT id, usuario, nombre_completo, email, rol, estado, fecha_registro
             FROM usuarios
             WHERE rol IN ('editor', 'admin')
             ORDER BY FIELD(rol, 'admin', 'editor'), usuario ASC"
        );
    }

    // Busca usuarios por nombre de usuario, nombre completo o email
    public function buscarUsuarios($termino)
    {
        $like = '%' . $termino . '%';
        return $this->database->queryAll(
            "SELECT u.id, u.usuario, u.nombre_completo, u.email, u.rol, u.estado, u.fecha_registro,
                    (SELECT COUNT(*) FROM partidas p WHERE p.usuario_id = u.id AND p.estado = 'terminada') AS partidas_jugadas
             FROM usuarios u
             WHERE u.usuario LIKE ? OR u.nombre_completo LIKE ? OR u.email LIKE ?
             ORDER BY FIELD(u.rol, 'maestro', 'admin', 'editor', 'usuario'), u.usuario ASC
             LIMIT 20",
            [$like, $like, $like]
        );
    }

    // Cambia el rol de un usuario (nunca permite alterar a un usuario 'maestro')
    public function cambiarRol($usuarioId, $nuevoRol)
    {
        if (!in_array($nuevoRol, self::ROLES_ASIGNABLES, true)) {
            return false;
        }
        $filas = $this->database->execute(
            "UPDATE usuarios SET rol = ? WHERE id = ? AND rol <> 'maestro'",
            [$nuevoRol, (int)$usuarioId]
        );
        return $filas >= 0;
    }

    // Banea o desbanea un usuario (nunca permite banear a un 'maestro')
    public function cambiarEstadoBaneo($usuarioId, $banear)
    {
        $nuevoEstado = $banear ? 'baneado' : 'activo';
        $this->database->execute(
            "UPDATE usuarios SET estado = ? WHERE id = ? AND rol <> 'maestro'",
            [$nuevoEstado, (int)$usuarioId]
        );
        return true;
    }

    // Verifica si ya existe el usuario o email
    public function existeUsuarioOEmail($usuario, $email)
    {
        $fila = $this->database->queryOne(
            "SELECT id FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1",
            [$usuario, $email]
        );
        return $fila !== null;
    }

    // Crea un usuario con rol 'editor' o 'admin' ya activo
    public function crearUsuarioConRol($usuario, $email, $nombreCompleto, $password, $rol)
    {
        if (!in_array($rol, ['editor', 'admin'], true)) {
            return false;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->database->execute(
            "INSERT INTO usuarios
                (usuario, email, password, nombre_completo, fecha_nacimiento, genero, pais, ciudad, foto_perfil, estado, rol)
             VALUES (?, ?, ?, ?, '2000-01-01', 'Prefiero no cargarlo', 'Argentina', 'Buenos Aires', '', 'activo', ?)",
            [$usuario, $email, $hash, $nombreCompleto, $rol]
        );
        return true;
    }
}
