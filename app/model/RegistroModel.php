<?php

namespace app\model;
class RegistroModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }
    // Busca en la bd si hay un usuario con ese nombre y devuelve true. O false si no lo está
    public function existeUsuario($usuario)
    {
        $sql = "SELECT id FROM usuarios WHERE usuario = ?";
        return $this->database->queryOne($sql, [$usuario]) !== null;
    }
    // Busca en la bd si hay un usuario con ese email y devuelve true. O false si no lo está
    public function existeEmail($email)
    {
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        return $this->database->queryOne($sql, [$email]) !== null;
    }


//Con código de verificación:
    public function registrar(array $datos)
    {
        // Covierte la contraseña en un hash
        $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);

        // Se genera el código de verificación (se guarda en la columna token_validacion)
        $codigo = $this->generarCodigo();

        // Inserta al usuario. El estado se define como pendiente hasta que se valide el mail
        $sql = "INSERT INTO usuarios
                    (usuario, email, password, nombre_completo, fecha_nacimiento, genero, pais, ciudad, foto_perfil, token_validacion, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')";
        // Los valores deben ir en el mismo orden que las columnas de arriba
        $this->database->execute($sql, [
            $datos['usuario'],
            $datos['email'],
            $passwordHash,  // Guarda el hash
            $datos['nombre_completo'],
            $datos['fecha_nacimiento'],
            $datos['genero'],
            $datos['pais'],
            $datos['ciudad'],
            $datos['foto_perfil'],
            $codigo,
        ]);
        // Retornamos el código para enviarlo por mail en el controlador
        return $codigo;
    }

    // Devuelve el usuario y el código de una cuenta pendiente (para reenviar el mismo código).
    // Devuelve null si el email no existe o la cuenta ya está activa.
    public function obtenerPendiente($email)
    {
        $sql = "SELECT usuario, token_validacion FROM usuarios
                WHERE email = ? AND estado = 'pendiente' AND token_validacion IS NOT NULL";
        return $this->database->queryOne($sql, [$email]);
    }

    // Compara el código ingresado con el guardado para ese email y activa la cuenta si coincide.
    // Devuelve: 'ok', 'ya_activo', 'incorrecto' o 'inexistente'
    public function validarCodigo($email, $codigo)
    {
        $sql = "SELECT id, estado, token_validacion FROM usuarios WHERE email = ?";
        $fila = $this->database->queryOne($sql, [$email]);

        if ($fila === null) {
            return 'inexistente';
        }
        if ($fila['estado'] === 'activo') {
            return 'ya_activo';
        }
        // hash_equals compara sin filtrar información por el tiempo que tarda
        if ($fila['token_validacion'] === null || !hash_equals($fila['token_validacion'], $codigo)) {
            return 'incorrecto';
        }

        // Pasa a estado activo y borra el código para que solo se pueda usar una vez
        $sql = "UPDATE usuarios SET estado = 'activo', token_validacion = NULL WHERE id = ?";
        $this->database->execute($sql, [$fila['id']]);
        return 'ok';
    }

    // Código de 8 caracteres, ej: "K7M2QXPA".
    // Se usan letras y números sin los que se confunden (0/O, 1/I) y random_int, que es seguro
    // (a diferencia de rand()). Con 8 caracteres hay más de un billón de combinaciones,
    // así que no se puede adivinar probando.
    private function generarCodigo()
    {
        $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';
        for ($i = 0; $i < 8; $i++) {
            $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }
        return $codigo;
    }
}
