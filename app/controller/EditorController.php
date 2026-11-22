<?php

namespace app\controller;

/**
 * Controlador base para el rol 'editor'.
 * Accesible por usuarios con rol 'editor' y 'admin' (configurado en config/routes.php).
 *
 * Espacio reservado para agregar las funcionalidades del editor
 * (ej. gestión de preguntas, categorías, reportes, etc.).
 */
class EditorController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show()
    {
        $data = [
            "usuario" => $_SESSION['usuario'] ?? '',
            "rol"     => $_SESSION['rol'] ?? 'editor',
            "es_admin" => ($_SESSION['rol'] ?? '') === 'admin',
        ];

        $this->render->renderiza("editor", $data);
    }
}
