<?php

namespace app\validator;

use app\validator\Regla;

class LongitudesPregunta implements Regla
{
    // Los maximos son los de las columnas de la bd: preguntas.enunciado (255) y respuestas.texto (150)
    const PREGUNTA_MINIMA = 10;
    const PREGUNTA_MAXIMA = 255;
    const RESPUESTA_MAXIMA = 150;
    public function validar(array $datos)
    {
        $largo = mb_strlen($datos['pregunta']);
        if ($largo < self::PREGUNTA_MINIMA || $largo > self::PREGUNTA_MAXIMA) {
            return "La pregunta debe tener entre " . self::PREGUNTA_MINIMA . " y " . self::PREGUNTA_MAXIMA . " caracteres.";
        }

        foreach (['correcta', 'incorrecta1', 'incorrecta2', 'incorrecta3'] as $campo) {
            if (mb_strlen($datos[$campo]) > self::RESPUESTA_MAXIMA) {
                return "Cada respuesta puede tener hasta " . self::RESPUESTA_MAXIMA . " caracteres.";
            }
        }

        return null;
    }
}