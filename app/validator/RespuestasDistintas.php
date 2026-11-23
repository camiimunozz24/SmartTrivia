<?php

namespace app\validator;

class RespuestasDistintas implements Regla
{

    public function validar(array $datos)
    {
        $respuestas = [];
        foreach (['correcta', 'incorrecta1', 'incorrecta2', 'incorrecta3'] as $campo) {
            $respuestas[] = mb_strtolower($datos[$campo]);
        }

        if (count(array_unique($respuestas)) !== count($respuestas)) {
            return "Las cuatro respuestas tienen que ser distintas.";
        }

        return null;
    }
}