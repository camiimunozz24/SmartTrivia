<?php

namespace app\controller;

use FormularioPregunta;
use Redirect;

// "Sugerir pregunta": lo puede usar cualquier usuario con sesion. La pregunta queda en estado 'sugerida'
// y NO entra al juego hasta que un editor la apruebe.
class SugerenciaController
{
    private $render;
    private $model;
    private $validador;

    public function __construct($render, $model, $validador)
    {
        $this->render = $render;
        $this->model = $model;
        $this->validador = $validador;
    }

    // Muestra el formulario
    public function show()
    {
        // Si la validacion fallo, se vuelve a mostrar lo que el usuario habia escrito
        $previos = $_SESSION['sugerencia_datos'] ?? [];
        $error = $_SESSION['sugerencia_error'] ?? null;
        $ok = $_SESSION['sugerencia_ok'] ?? null;
        unset($_SESSION['sugerencia_datos'], $_SESSION['sugerencia_error'], $_SESSION['sugerencia_ok']); // Se muestran una sola vez

        $categorias = [];
        $hayElegida = false;
        foreach ($this->model->obtenerCategorias() as $categoria) {
            $seleccionada = (string)$categoria['id'] === (string)($previos['categoria_id'] ?? '');
            $hayElegida = $hayElegida || $seleccionada;
            $categorias[] = [
                "id" => $categoria['id'],
                "nombre" => $categoria['nombre'],
                "seleccionada" => $seleccionada,
            ];
        }

        $this->render->renderiza("sugerencia", [
            "categorias" => $categorias,
            "sin_categoria_elegida" => !$hayElegida,
            "pregunta" => $previos['pregunta'] ?? '',
            "correcta" => $previos['correcta'] ?? '',
            "incorrecta1" => $previos['incorrecta1'] ?? '',
            "incorrecta2" => $previos['incorrecta2'] ?? '',
            "incorrecta3" => $previos['incorrecta3'] ?? '',
            "error" => $error,
            "ok" => $ok,
        ]);
    }

    // Recibe el formulario
    public function enviar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/sugerencia/show');
            return;
        }

        $datos = FormularioPregunta::desdePost();
        $error = $this->validador->validar($datos);

        if ($error !== null) {
            $_SESSION['sugerencia_error'] = $error;
            $_SESSION['sugerencia_datos'] = $datos;
            Redirect::to('/sugerencia/show');
            return;
        }

        $this->model->crearSugerida((int)$_SESSION['usuario_id'], $datos);
        $_SESSION['sugerencia_ok'] = "¡Gracias! Tu pregunta quedó pendiente de revisión por un editor.";
        Redirect::to('/sugerencia/show');
    }
}