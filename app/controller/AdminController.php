<?php
namespace app\controller;
use Request;
class AdminController
{
    private $render;
    private $model;
    const PERIODOS = [
        'dia'    => 'Hoy (Día)',
        'semana' => 'Últimos 7 días (Semana)',
        'mes'    => 'Últimos 30 días (Mes)',
        'anio'   => 'Último año',
        'todos'  => 'Histórico completo',
    ];
    public function __construct($render, $model)
    {
        $this->render = $render;
        $this->model = $model;
    }
    public function show()
    {
        $rol = $_SESSION['rol'] ?? 'admin';
        // 1. Período seleccionado
        $periodo = Request::get('periodo', 'mes');
        if (!array_key_exists($periodo, self::PERIODOS)) {
            $periodo = 'mes';
        }
        // 2. Consultar datos en el modelo
        $metricas          = $this->model->obtenerMetricasPeriodo($periodo);
        $usuariosPais      = $this->model->obtenerUsuariosPorPais($periodo);
        $usuariosSexo      = $this->model->obtenerUsuariosPorSexo($periodo);
        $usuariosEdad      = $this->model->obtenerUsuariosPorEdad($periodo);
        $efectividad       = $this->model->obtenerEfectividadUsuarios($periodo, 10);
        $evolucionPartidas = $this->model->obtenerEvolucionPartidas($periodo);
        $preguntasCat      = $this->model->obtenerPreguntasPorCategoria();
        // 3. Preparar JSON para Chart.js
        // Gráfico País
        $paisLabels = array_column($usuariosPais, 'pais');
        $paisData   = array_column($usuariosPais, 'total');
        // Gráfico Sexo
        $sexoLabels = array_column($usuariosSexo, 'genero');
        $sexoData   = array_column($usuariosSexo, 'total');
        // Gráfico Edad
        $edadLabels = array_column($usuariosEdad, 'grupo');
        $edadData   = array_column($usuariosEdad, 'total');
        $edadColors = array_column($usuariosEdad, 'color');
        // Gráfico Efectividad (% de acierto por usuario)
        $efectividadLabels = array_column($efectividad, 'usuario');
        $efectividadData   = array_column($efectividad, 'porcentaje_acierto');
        // Gráfico Evolución Partidas
        $partidasLabels = array_column($evolucionPartidas, 'fecha');
        $partidasData   = array_column($evolucionPartidas, 'total');
        // Gráfico Preguntas por Categoría
        $catLabels = array_column($preguntasCat, 'categoria');
        $catData   = array_column($preguntasCat, 'total');
        $catColors = array_column($preguntasCat, 'color');


        // 4. Armar data para Mustache
        $data = [
            'usuario'             => $_SESSION['usuario'] ?? 'admin',
            'rol'                 => $rol,
            'es_maestro'          => $rol === 'maestro',
            'periodo_actual'      => $periodo,
            'periodo_nombre'      => self::PERIODOS[$periodo],
            'es_dia'              => $periodo === 'dia',
            'es_semana'           => $periodo === 'semana',
            'es_mes'              => $periodo === 'mes',
            'es_anio'             => $periodo === 'anio',
            'es_todos'            => $periodo === 'todos',
            // Tarjetas de Métricas
            'total_jugadores'     => $metricas['total_jugadores'],
            'usuarios_nuevos'     => $metricas['usuarios_nuevos'],
            'partidas_jugadas'    => $metricas['partidas_jugadas'],
            'preguntas_en_juego'  => $metricas['preguntas_en_juego'],
            'preguntas_creadas'   => $metricas['preguntas_creadas'],
            'tasa_acierto_global' => $metricas['tasa_acierto_global'],
            // Tablas de Datos
            'tabla_paises'        => $usuariosPais,
            'hay_paises'          => count($usuariosPais) > 0,
            'tabla_sexo'          => $usuariosSexo,
            'hay_sexo'            => count($usuariosSexo) > 0,
            'tabla_edad'          => $usuariosEdad,
            'tabla_efectividad'   => $efectividad,
            'hay_efectividad'     => count($efectividad) > 0,
            'tabla_evolucion'     => $evolucionPartidas,
            'hay_evolucion'       => count($evolucionPartidas) > 0,
            'tabla_categorias'    => $preguntasCat,
            // Datos JSON crudos para Chart.js (se usan con {{{triple llave}}} en Mustache)
            'json_pais_labels'    => json_encode($paisLabels, JSON_UNESCAPED_UNICODE),
            'json_pais_data'      => json_encode($paisData),
            'json_sexo_labels'    => json_encode($sexoLabels, JSON_UNESCAPED_UNICODE),
            'json_sexo_data'      => json_encode($sexoData),
            'json_edad_labels'    => json_encode($edadLabels, JSON_UNESCAPED_UNICODE),
            'json_edad_data'      => json_encode($edadData),
            'json_edad_colors'    => json_encode($edadColors),
            'json_efectividad_labels' => json_encode($efectividadLabels, JSON_UNESCAPED_UNICODE),
            'json_efectividad_data'   => json_encode($efectividadData),
            'json_partidas_labels'=> json_encode($partidasLabels, JSON_UNESCAPED_UNICODE),
            'json_partidas_data'  => json_encode($partidasData),
            'json_cat_labels'     => json_encode($catLabels, JSON_UNESCAPED_UNICODE),
            'json_cat_data'       => json_encode($catData),
            'json_cat_colors'     => json_encode($catColors),
        ];
        $this->render->renderiza("admin", $data);
    }
}