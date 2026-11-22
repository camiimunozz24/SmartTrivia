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
}
