<?php

namespace app\controller;

use FormularioPregunta;
use Redirect;
use Request;

/**
 * Controlador base para el rol 'editor'.
 * Accesible por usuarios con rol 'editor' y 'admin' (configurado en config/routes.php).
 *
 * Espacio reservado para agregar las funcionalidades del editor
 * (ej. gestión de preguntas, categorías, reportes, etc.).
 */
class EditorController
{
    const SECCION_SUGERENCIAS = 'sugerencias';
    const SECCION_REPORTES = 'reportes';
    private $render;
    private $preguntaModel;
    private $reporteModel;
    private $validador;
    public function __construct($render, $preguntaModel, $reporteModel, $validador)
    {
        $this->render = $render;
        $this->preguntaModel = $preguntaModel;
        $this->reporteModel = $reporteModel;
        $this->validador = $validador;
    }

    public function show()
    {
        $seccion = Request::get('seccion', self::SECCION_SUGERENCIAS) === self::SECCION_REPORTES
            ? self::SECCION_REPORTES
            : self::SECCION_SUGERENCIAS;

        $aviso = $_SESSION['editor_aviso'] ?? null;
        unset($_SESSION['editor_aviso']); // Se muestra una sola vez

        $categorias = $this->preguntaModel->obtenerCategorias();

        $sugerencias = [];
        foreach ($this->preguntaModel->obtenerSugeridas() as $pregunta) {
            $sugerencias[] = $this->formatearPregunta($pregunta, $categorias);
        }

        $reportes = [];
        foreach ($this->obtenerReportadas() as $item) {
            $fila = $this->formatearPregunta($item['pregunta'], $categorias);
            $fila['cantidad_reportes'] = $item['cantidad'];
            $fila['texto_reportes'] = $item['cantidad'] === 1 ? '1 reporte' : $item['cantidad'] . ' reportes';
            $fila['ultimo_reporte'] = date('d/m/Y H:i', strtotime($item['ultimo']));
            $reportes[] = $fila;
        }

        $this->render->renderiza("editor", [
            "usuario" => $_SESSION['usuario'] ?? '',
            "rol" => $_SESSION['rol'] ?? 'editor',
            "es_admin" => ($_SESSION['rol'] ?? '') === 'admin',
            "seccion_sugerencias" => $seccion === self::SECCION_SUGERENCIAS,
            "seccion_reportes" => $seccion === self::SECCION_REPORTES,
            "cant_sugerencias" => count($sugerencias),
            "cant_reportes" => count($reportes),
            "sugerencias" => $sugerencias,
            "hay_sugerencias" => count($sugerencias) > 0,
            "reportes" => $reportes,
            "hay_reportes" => count($reportes) > 0,
            "aviso" => $aviso['texto'] ?? null,
            "aviso_ok" => ($aviso['tipo'] ?? '') === 'ok',
            "aviso_error" => ($aviso['tipo'] ?? '') === 'error',
        ]);
    }

    // Guarda los cambios hechos a una sugerencia o a una pregunta reportada, sin cambiarle el estado
    public function editar()
    {
        $this->soloPost(self::SECCION_SUGERENCIAS);

        $seccion = Request::post('seccion') === self::SECCION_REPORTES ? self::SECCION_REPORTES : self::SECCION_SUGERENCIAS;
        $estadoEsperado = $seccion === self::SECCION_REPORTES ? 'activa' : 'sugerida';
        $id = (int)Request::post('id', 0);

        $datos = $this->datosValidos();
        if ($datos === null) {
            $this->terminar($seccion);
        }

        if (!$this->preguntaModel->editar($id, $datos, $estadoEsperado)) {
            $this->avisar('error', 'La pregunta ya no está disponible para editar (otro editor pudo haberla resuelto).');
            $this->terminar($seccion);
        }

        $this->avisar('ok', $seccion === self::SECCION_REPORTES
            ? 'Cambios guardados. El reporte sigue pendiente hasta que elijas "Mantener activa" o "Dar de baja".'
            : 'Cambios guardados.');
        $this->terminar($seccion);
    }

    // Aprueba una sugerencia (con las ediciones que haya hecho el editor): pasa a ser una pregunta oficial 'activa'
    public function aprobar()
    {
        $this->soloPost(self::SECCION_SUGERENCIAS);

        $datos = $this->datosValidos();
        if ($datos === null) {
            $this->terminar(self::SECCION_SUGERENCIAS);
        }

        if ($this->preguntaModel->aprobar((int)Request::post('id', 0), $datos)) {
            $this->avisar('ok', 'Pregunta aprobada: ya forma parte del juego.');
        } else {
            $this->avisar('error', 'No se pudo aprobar: la sugerencia ya fue resuelta por otro editor.');
        }
        $this->terminar(self::SECCION_SUGERENCIAS);
    }

    public function rechazar()
    {
        $this->soloPost(self::SECCION_SUGERENCIAS);

        if ($this->preguntaModel->rechazar((int)Request::post('id', 0))) {
            $this->avisar('ok', 'Sugerencia rechazada.');
        } else {
            $this->avisar('error', 'No se pudo rechazar: la sugerencia ya fue resuelta por otro editor.');
        }
        $this->terminar(self::SECCION_SUGERENCIAS);
    }

    // La pregunta reportada esta bien: sigue activa y se cierran sus reportes
    public function mantener()
    {
        $this->soloPost(self::SECCION_REPORTES);

        $id = (int)Request::post('id', 0);
        $cerrados = $this->reporteModel->resolverPendientes($id);
        $this->avisar('ok', $cerrados > 0
            ? 'La pregunta sigue activa y se cerraron sus reportes.'
            : 'Esos reportes ya estaban resueltos.');
        $this->terminar(self::SECCION_REPORTES);
    }

    // La pregunta es incorrecta: sale del juego ('baja') y se cierran sus reportes
    public function baja()
    {
        $this->soloPost(self::SECCION_REPORTES);

        $id = (int)Request::post('id', 0);
        if ($this->preguntaModel->darDeBaja($id)) {
            $this->reporteModel->resolverPendientes($id);
            $this->avisar('ok', 'Pregunta dada de baja: ya no aparece en el juego.');
        } else {
            $this->avisar('error', 'La pregunta ya no estaba activa.');
        }
        $this->terminar(self::SECCION_REPORTES);
    }

    // ---- auxiliares ----

    // Une el resumen de reportes pendientes con el contenido completo de cada pregunta
    private function obtenerReportadas()
    {
        $resumen = $this->reporteModel->obtenerResumenPendientes();
        $preguntas = $this->preguntaModel->obtenerPorIds(array_column($resumen, 'pregunta_id'));

        $reportadas = [];
        foreach ($resumen as $fila) {
            if (isset($preguntas[$fila['pregunta_id']])) {
                $reportadas[] = [
                    'pregunta' => $preguntas[$fila['pregunta_id']],
                    'cantidad' => (int)$fila['cantidad'],
                    'ultimo' => $fila['ultimo'],
                ];
            }
        }
        return $reportadas;
    }

    // Arma los datos de una tarjeta; marca cual es la categoría actual para el desplegable
    private function formatearPregunta($pregunta, $categorias)
    {
        $opciones = [];
        foreach ($categorias as $categoria) {
            $opciones[] = [
                "id" => $categoria['id'],
                "nombre" => $categoria['nombre'],
                "seleccionada" => (int)$categoria['id'] === (int)$pregunta['categoria_id'],
            ];
        }

        return [
            "id" => (int)$pregunta['id'],
            "enunciado" => $pregunta['enunciado'],
            "categoria" => $pregunta['categoria'],
            "color" => $pregunta['color'],
            "categorias" => $opciones,
            "correcta" => $pregunta['correcta'],
            "incorrecta1" => $pregunta['incorrecta1'],
            "incorrecta2" => $pregunta['incorrecta2'],
            "incorrecta3" => $pregunta['incorrecta3'],
            "autor" => $pregunta['autor'] ?? 'Sin autor',
            "fecha" => date('d/m/Y H:i', strtotime($pregunta['fecha_creacion'])),
        ];
    }

    // Lee y valida el formulario. Si hay error lo deja anotado y devuelve null
    private function datosValidos()
    {
        $datos = FormularioPregunta::desdePost();
        $error = $this->validador->validar($datos);
        if ($error !== null) {
            $this->avisar('error', $error);
            return null;
        }
        return $datos;
    }

    private function soloPost($seccion)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->terminar($seccion);
        }
    }

    private function avisar($tipo, $texto)
    {
        $_SESSION['editor_aviso'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function terminar($seccion)
    {
        Redirect::to('/editor/show?seccion=' . $seccion);
    }
}

