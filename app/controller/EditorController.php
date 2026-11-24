<?php

namespace app\controller;

use FormularioPregunta;
use Redirect;
use Request;

/**
 * Controlador del rol 'editor'.
 * Accesible por usuarios con rol 'editor', 'admin' y 'maestro' (configurado en config/routes.php).
 * Gestiona sugerencias, reportes, edición/baja lógica de preguntas existentes y gestión de categorías.
 */
class EditorController
{
    const SECCION_SUGERENCIAS = 'sugerencias';
    const SECCION_REPORTES = 'reportes';
    const SECCION_PREGUNTAS = 'preguntas';
    const SECCION_CATEGORIAS = 'categorias';
    const SECCION_CREAR = 'crear';

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
        $seccionesValidas = [
            self::SECCION_SUGERENCIAS,
            self::SECCION_REPORTES,
            self::SECCION_PREGUNTAS,
            self::SECCION_CATEGORIAS,
            self::SECCION_CREAR,
        ];
        $seccionParam = (string)Request::get('seccion', self::SECCION_SUGERENCIAS);
        $seccion = in_array($seccionParam, $seccionesValidas, true) ? $seccionParam : self::SECCION_SUGERENCIAS;

        $aviso = $_SESSION['editor_aviso'] ?? null;
        unset($_SESSION['editor_aviso']); // Se muestra una sola vez

        $categoriasConConteo = $this->preguntaModel->obtenerCategoriasConConteo();
        $categorias = $categoriasConConteo;

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

        $cantPreguntasActivas = $this->preguntaModel->contarActivas();

        // Sección Preguntas (por categoría)
        $catParam = Request::get('cat', null);
        $catSeleccionada = $catParam !== null
            ? (int)$catParam
            : (isset($categoriasConConteo[0]) ? (int)$categoriasConConteo[0]['id'] : 0);

        $filtroCategorias = [];
        foreach ($categoriasConConteo as $cat) {
            $filtroCategorias[] = [
                'id' => (int)$cat['id'],
                'nombre' => $cat['nombre'],
                'color' => $cat['color'],
                'cant_activas' => (int)$cat['cant_activas'],
                'activa' => (int)$cat['id'] === $catSeleccionada,
            ];
        }

        $preguntasActivas = [];
        if ($seccion === self::SECCION_PREGUNTAS) {
            foreach ($this->preguntaModel->obtenerActivas($catSeleccionada) as $pregunta) {
                $preguntasActivas[] = $this->formatearPregunta($pregunta, $categorias);
            }
        }

        // Sección Categorías
        $categoriasLista = [];
        foreach ($categoriasConConteo as $cat) {
            $activas = (int)$cat['cant_activas'];
            $total = (int)$cat['cant_total'];
            $categoriasLista[] = [
                'id' => (int)$cat['id'],
                'nombre' => $cat['nombre'],
                'color' => $cat['color'],
                'cant_activas' => $activas,
                'cant_total' => $total,
                'texto_preguntas' => $activas === 1 ? '1 pregunta activa' : "{$activas} preguntas activas",
            ];
        }

        $previosCrear = $_SESSION['editor_crear_previos'] ?? [];
        unset($_SESSION['editor_crear_previos']);

        $categoriasCrear = [];
        foreach ($categoriasConConteo as $cat) {
            $categoriasCrear[] = [
                'id' => (int)$cat['id'],
                'nombre' => $cat['nombre'],
                'seleccionada' => (string)$cat['id'] === (string)($previosCrear['categoria_id'] ?? ''),
            ];
        }

        $this->render->renderiza("editor", [
            "usuario" => $_SESSION['usuario'] ?? '',
            "rol" => $_SESSION['rol'] ?? 'editor',
            "es_admin" => in_array($_SESSION['rol'] ?? '', ['admin', 'maestro'], true),
            "seccion_sugerencias" => $seccion === self::SECCION_SUGERENCIAS,
            "seccion_reportes" => $seccion === self::SECCION_REPORTES,
            "seccion_preguntas" => $seccion === self::SECCION_PREGUNTAS,
            "seccion_categorias" => $seccion === self::SECCION_CATEGORIAS,
            "seccion_crear" => $seccion === self::SECCION_CREAR,
            "cant_sugerencias" => count($sugerencias),
            "cant_reportes" => count($reportes),
            "cant_preguntas" => $cantPreguntasActivas,
            "cant_categorias" => count($categoriasLista),
            "sugerencias" => $sugerencias,
            "hay_sugerencias" => count($sugerencias) > 0,
            "reportes" => $reportes,
            "hay_reportes" => count($reportes) > 0,
            "filtro_categorias" => $filtroCategorias,
            "cat_seleccionada" => $catSeleccionada,
            "todas_categorias" => $catSeleccionada === 0,
            "preguntas_activas" => $preguntasActivas,
            "hay_preguntas_activas" => count($preguntasActivas) > 0,
            "categorias_lista" => $categoriasLista,
            "hay_categorias" => count($categoriasLista) > 0,
            "categorias_crear" => $categoriasCrear,
            "crear_pregunta" => $previosCrear['pregunta'] ?? '',
            "crear_correcta" => $previosCrear['correcta'] ?? '',
            "crear_incorrecta1" => $previosCrear['incorrecta1'] ?? '',
            "crear_incorrecta2" => $previosCrear['incorrecta2'] ?? '',
            "crear_incorrecta3" => $previosCrear['incorrecta3'] ?? '',
            "aviso" => $aviso['texto'] ?? null,
            "aviso_ok" => ($aviso['tipo'] ?? '') === 'ok',
            "aviso_error" => ($aviso['tipo'] ?? '') === 'error',
        ]);
    }

    // Crea una pregunta directamente como 'activa' desde el panel del editor
    public function crearPregunta()
    {
        $this->soloPost(self::SECCION_CREAR);

        $datos = FormularioPregunta::desdePost();
        $error = $this->validador->validar($datos);
        if ($error !== null) {
            $_SESSION['editor_crear_previos'] = $datos;
            $this->avisar('error', $error);
            $this->terminar(self::SECCION_CREAR);
        }

        $this->preguntaModel->crearActiva((int)($_SESSION['usuario_id'] ?? 0), $datos);
        $this->avisar('ok', 'Pregunta creada y activada: ya forma parte del juego.');
        $this->terminar(self::SECCION_PREGUNTAS, '&cat=' . (int)$datos['categoria_id']);
    }

    // Guarda los cambios hechos a una sugerencia, pregunta reportada o pregunta activa del banco
    public function editar()
    {
        $this->soloPost(self::SECCION_SUGERENCIAS);

        $seccionPost = (string)Request::post('seccion', self::SECCION_SUGERENCIAS);
        if ($seccionPost === self::SECCION_REPORTES) {
            $seccion = self::SECCION_REPORTES;
            $estadoEsperado = 'activa';
        } elseif ($seccionPost === self::SECCION_PREGUNTAS) {
            $seccion = self::SECCION_PREGUNTAS;
            $estadoEsperado = 'activa';
        } else {
            $seccion = self::SECCION_SUGERENCIAS;
            $estadoEsperado = 'sugerida';
        }

        $cat = Request::post('cat', null);
        $extraQuery = ($seccion === self::SECCION_PREGUNTAS && $cat !== null) ? '&cat=' . (int)$cat : '';
        $id = (int)Request::post('id', 0);

        $datos = $this->datosValidos();
        if ($datos === null) {
            $this->terminar($seccion, $extraQuery);
        }

        if (!$this->preguntaModel->editar($id, $datos, $estadoEsperado)) {
            $this->avisar('error', 'La pregunta ya no está disponible para editar (otro editor pudo haberla resuelto o dado de baja).');
            $this->terminar($seccion, $extraQuery);
        }

        if ($seccion === self::SECCION_REPORTES) {
            $this->avisar('ok', 'Cambios guardados. El reporte sigue pendiente hasta que elijas "Mantener activa" o "Dar de baja".');
        } else {
            $this->avisar('ok', 'Cambios guardados correctamente.');
        }
        $this->terminar($seccion, $extraQuery);
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

    // Borrado lógico de una pregunta (estado = 'baja'): sale del juego sin eliminarse físicamente
    public function baja()
    {
        $this->soloPost(self::SECCION_REPORTES);

        $seccion = Request::post('seccion') === self::SECCION_PREGUNTAS
            ? self::SECCION_PREGUNTAS
            : self::SECCION_REPORTES;
        $cat = Request::post('cat', null);
        $extraQuery = ($seccion === self::SECCION_PREGUNTAS && $cat !== null) ? '&cat=' . (int)$cat : '';

        $id = (int)Request::post('id', 0);
        if ($this->preguntaModel->darDeBaja($id)) {
            $this->reporteModel->resolverPendientes($id);
            $this->avisar('ok', 'Pregunta dada de baja lógicamente: ya no aparece en el juego.');
        } else {
            $this->avisar('error', 'La pregunta ya no estaba activa.');
        }
        $this->terminar($seccion, $extraQuery);
    }

    // ---- GESTIÓN DE CATEGORÍAS ----

    public function crearCategoria()
    {
        $this->soloPost(self::SECCION_CATEGORIAS);

        $nombre = trim((string)Request::post('nombre', ''));
        $color = $this->colorValido((string)Request::post('color', '#1e56a0'));

        if ($nombre === '' || mb_strlen($nombre) > 50) {
            $this->avisar('error', 'Ingresá un nombre de categoría válido (entre 1 y 50 caracteres).');
            $this->terminar(self::SECCION_CATEGORIAS);
        }

        if ($this->preguntaModel->crearCategoria($nombre, $color) === false) {
            $this->avisar('error', 'Ya existe una categoría con ese nombre.');
            $this->terminar(self::SECCION_CATEGORIAS);
        }

        $this->avisar('ok', "Categoría «{$nombre}» creada correctamente.");
        $this->terminar(self::SECCION_CATEGORIAS);
    }

    public function editarCategoria()
    {
        $this->soloPost(self::SECCION_CATEGORIAS);

        $id = (int)Request::post('id', 0);
        $nombre = trim((string)Request::post('nombre', ''));
        $color = $this->colorValido((string)Request::post('color', '#1e56a0'));

        if ($id <= 0 || $nombre === '' || mb_strlen($nombre) > 50) {
            $this->avisar('error', 'El nombre de la categoría debe tener entre 1 y 50 caracteres.');
            $this->terminar(self::SECCION_CATEGORIAS);
        }

        if (!$this->preguntaModel->editarCategoria($id, $nombre, $color)) {
            $this->avisar('error', 'No se pudo actualizar: verificá que no exista otra categoría con el mismo nombre.');
            $this->terminar(self::SECCION_CATEGORIAS);
        }

        $this->avisar('ok', "Categoría actualizada a «{$nombre}».");
        $this->terminar(self::SECCION_CATEGORIAS);
    }

    // Borrar categoría requiere confirmación adicional y creación obligatoria de una nueva categoría de reemplazo
    public function eliminarCategoria()
    {
        $this->soloPost(self::SECCION_CATEGORIAS);

        $id = (int)Request::post('id', 0);
        $confirmar = Request::post('confirmar', '');
        $nuevoNombre = trim((string)Request::post('nuevo_nombre', ''));
        $nuevoColor = $this->colorValido((string)Request::post('nuevo_color', '#1e56a0'));

        if ($confirmar !== '1') {
            $this->avisar('error', 'Debés marcar la casilla de confirmación adicional para eliminar la categoría.');
            $this->terminar(self::SECCION_CATEGORIAS);
        }

        if ($id <= 0 || $nuevoNombre === '' || mb_strlen($nuevoNombre) > 50) {
            $this->avisar('error', 'Para eliminar una categoría es obligatorio crear una nueva categoría válida (1 a 50 caracteres) donde reasignar sus preguntas.');
            $this->terminar(self::SECCION_CATEGORIAS);
        }

        if ($this->preguntaModel->existeNombreCategoria($nuevoNombre)) {
            $this->avisar('error', 'El nombre de la nueva categoría de reemplazo ya existe. Elegí un nombre nuevo.');
            $this->terminar(self::SECCION_CATEGORIAS);
        }

        $nuevaId = $this->preguntaModel->eliminarCategoriaYReasignar($id, $nuevoNombre, $nuevoColor);
        if ($nuevaId === false) {
            $this->avisar('error', 'No se pudo eliminar la categoría. Verificá que todavía exista.');
            $this->terminar(self::SECCION_CATEGORIAS);
        }

        $this->avisar('ok', "Categoría eliminada y todas sus preguntas fueron reasignadas a la nueva categoría «{$nuevoNombre}».");
        $this->terminar(self::SECCION_CATEGORIAS);
    }

    // ---- auxiliares ----

    private function colorValido($color)
    {
        $color = trim($color);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtoupper($color) : '#1E56A0';
    }

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

    private function terminar($seccion, $extraQuery = '')
    {
        Redirect::to('/editor/show?seccion=' . $seccion . $extraQuery);
    }
}
