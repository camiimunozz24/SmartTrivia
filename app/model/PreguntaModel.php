<?php

namespace app\model;

// Preguntas y sus respuestas: sugerencias de los usuarios, revisión del editor y edición del contenido.
// El juego (PartidaModel) solo lee las preguntas con estado 'activa', así que una sugerida nunca entra a una partida.
class PreguntaModel
{
    private $database;

    const SUGERIDA = 'sugerida';
    const ACTIVA = 'activa';
    const RECHAZADA = 'rechazada';
    const BAJA = 'baja';

    public function __construct($database){
        $this->database = $database;
    }

    //CATEGORIAS

    public function obtenerCategorias(){
        return $this->database->queryAll("SELECT id, nombre, color FROM categorias ORDER BY id");
    }

    public function existeCategoria($categoriaId){
        return $this->database->queryOne("SELECT id FROM categorias WHERE id = ?", [(int)$categoriaId]) !== null;
    }

    //SUGERENCIAS (cualquier usuario)

    //guarda la pregunta con sus 4 respuestas en estado 'sugerida'. O se guarda todo no se guarda nada
    public function crearSugerida($usuarioId, $datos){
       return $this->enTransaccion(function () use ($usuarioId, $datos){
            $this->database->execute(
                "INSERT INTO preguntas (categoria_id, enunciado, estado, creada_por) VALUES (?, ?, ?, ?)",
                [(int)$datos['categoria_id'], $datos['pregunta'], self::SUGERIDA, (int)$usuarioId]);
            $preguntaId = (int)$this->database->lastInsertId();

            $this->database->execute(
                "INSERT INTO respuestas (pregunta_id, texto, es_correcto) VALUES (?, ?, 1)",
                [$preguntaId, $datos['correcta']]);
            foreach (['incorrecta1', 'incorrecta2', 'incorrecta3'] as $campo) {
                $this->database->execute(
                    "INSERT INTO respuestas (pregunta_id, texto, es_correcto) VALUES (?, ?, 0)",
                    [$preguntaId, $datos[$campo]]
                );
            }
            return $preguntaId;
        });
    }

    //CONSULTAS PARA EL EDITOR

    //sugerencias pendientes de revisión, las más viejas primero
    public function obtenerSugeridas()
    {
        return $this->consultar("p.estado = ?", [self::SUGERIDA], "p.fecha_creacion ASC, p.id ASC");
    }

    //Devuelve las preguntas pedidas, indexadas por id
    public function obtenerPorIds(array $ids){
        if(count($ids) === 0){
            return[];
        }
        $ids = array_map('intval', array_values($ids));
        $marcas = implode(', ', array_fill(0, count($ids), '?'));

        $indexadas = [];
        foreach($this->consultar("p.id IN ($marcas)", $ids, "p.id ASC") as $pregunta){
            $indexadas[$pregunta['id']] = $pregunta;
        }
        return $indexadas;
    }

    //Trae preguntas con sus categoria, su autor y sus respuestas (correcta + 3 incorrectas)
    private function consultar($where, array $params, $orden)
    {
        $preguntas = $this->database->queryAll(
            "SELECT p.id, p.enunciado, p.categoria_id, p.estado, p.fecha_creacion,
            c.nombre AS categoria, c.color, u.usuario AS autor
            FROM preguntas p
            JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN usuarios u ON u.id = p.creada_por
            WHERE $where
            ORDER BY $orden",
            $params
        );
        if(count($preguntas) === 0){
            return [];
        }

        $ids = array_column($preguntas, 'id');
        $marcas = implode(', ', array_fill(0, count($ids), '?'));
        $respuestas = $this->database->queryAll(
            "SELECT pregunta_id, texto, es_correcto FROM respuestas
            WHERE pregunta_id IN ($marcas) ORDER BY es_correcto DESC, id ASC",
            $ids
        );
        $porPregunta = [];
        foreach ($respuestas as $r){
            $porPregunta[$r['pregunta_id']][] = $r;
        }
        foreach ($preguntas as &$pregunta) {
            $lista = $porPregunta[$pregunta['id']] ?? [];
            $pregunta['correcta'] = $lista[0]['texto'] ?? '';
            $pregunta['incorrecta1'] = $lista[1]['texto'] ?? '';
            $pregunta['incorrecta2'] = $lista[2]['texto'] ?? '';
            $pregunta['incorrecta3'] = $lista[3]['texto'] ?? '';
        }
        return $preguntas;
    }

    //ACCIONES DEL EDITOR (cada una devuelve true si se aplicó y false si la pregunta ya no estaba en el estado esperado)

    //guarda los cambios de una pregunta que este en $estadoEsperado ('sugerida' o 'activa')
    public function editar($preguntaId, $datos, $estadoEsperado){
        return $this->enTransaccion(function () use ($preguntaId, $datos, $estadoEsperado) {
                return $this->escribirContenido($preguntaId, $datos, $estadoEsperado);
            }) === true;
    }

    // Guarda los cambios del editor y deja la sugerencia 'activa' (entra al juego)
    public function aprobar($preguntaId, $datos)
    {
        return $this->enTransaccion(function () use ($preguntaId, $datos) {
                if (!$this->escribirContenido($preguntaId, $datos, self::SUGERIDA)) {
                    return false;}
                return $this->cambiarEstado($preguntaId, self::SUGERIDA, self::ACTIVA);
            }) === true;
    }

    public function rechazar($preguntaId)
    {
        return $this->cambiarEstado($preguntaId, self::SUGERIDA, self::RECHAZADA);}
    public function darDeBaja($preguntaId)
    {
        return $this->cambiarEstado($preguntaId, self::ACTIVA, self::BAJA);}

    //cambia el estado si la pregunta todavia esta en el estado esperado (si otro editor ya lo resolvio, no hace nada)
    private function cambiarEstado($preguntaId, $desde, $hacia){
        return $this->database->execute(
                "UPDATE preguntas SET estado = ? WHERE id = ? AND estado = ?",
                [$hacia, (int)$preguntaId, $desde]
            ) === 1;
    }
    // Actualiza enunciado, categoria y las 4 respuestas. Las respuestas se actualizan EN SU LUGAR (mismo id):
    // si alguien esta jugando justo esa pregunta, el id de la opcion que eligio sigue existiendo.
    // Debe llamarse dentro de una transacción
    private function escribirContenido($preguntaId, $datos, $estadoEsperado)
    {
        // FOR UPDATE bloquea la fila: si dos editores resuelven la misma pregunta, el segundo espera y ve el estado nuevo
        $pregunta = $this->database->queryOne("SELECT estado FROM preguntas WHERE id = ? FOR UPDATE", [(int)$preguntaId]);
        if ($pregunta === null || $pregunta['estado'] !== $estadoEsperado) {
            return false;
        }

        // Primero la correcta, despues las incorrectas
        $respuestas = $this->database->queryAll(
            "SELECT id FROM respuestas WHERE pregunta_id = ? ORDER BY es_correcto DESC, id ASC",
            [(int)$preguntaId]
        );
        if (count($respuestas) !== 4) {
            return false;
        }

        $this->database->execute(
            "UPDATE preguntas SET enunciado = ?, categoria_id = ? WHERE id = ?",
            [$datos['pregunta'], (int)$datos['categoria_id'], (int)$preguntaId]
        );

        $textos = [$datos['correcta'], $datos['incorrecta1'], $datos['incorrecta2'], $datos['incorrecta3']];
        foreach ($respuestas as $i => $respuesta) {
            $this->database->execute("UPDATE respuestas SET texto = ? WHERE id = ?", [$textos[$i], (int)$respuesta['id']]);
        }
        return true;
    }

    // Ejecuta $accion dentro de una transaccion. Si devuelve false o falla, se deshace todo
    private function enTransaccion(callable $accion)
    {
        $this->database->beginTransaction();
        try {
            $resultado = $accion();
            if ($resultado === false) {
                $this->database->rollBack();
                return false;
            }
            $this->database->commit();
            return $resultado;
        } catch (\Throwable $e) {
            $this->database->rollBack();
            throw $e;
        }
    }
}
