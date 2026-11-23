<?php

// Lee del POST los datos de una pregunta (con sus 4 respuestas y la categoria) y los deja limpios.
// Lo usan el formulario de "Sugerir pregunta" y el formulario de edicion del editor: los dos piden lo mismo.
class FormularioPregunta
{
    const CAMPOS = ['pregunta', 'correcta', 'incorrecta1', 'incorrecta2', 'incorrecta3', 'categoria_id'];

    public static function desdePost(){
        $datos = [];
        foreach(self::CAMPOS as $campo){
            $valor = Request::post($campo, '');
            // Si llega algo que no es texto (un arreglo armado a mano) se toma como vacio
            $limpio = is_string($valor) ? preg_replace('/\s+/u', ' ', $valor) : '';
            $datos[$campo] = trim((string)$limpio);
        }
        return $datos;
    }
}