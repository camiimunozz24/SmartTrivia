<?php

namespace app\validator;
class GeneroValido implements Regla
{
    // Comprueba que sea una de las opciones del select
    public function validar(array $datos)
    {
        $permitidos = ['Masculino', 'Femenino', 'Prefiero no cargarlo'];

        if (!in_array($datos['genero'] ?? '', $permitidos, true)) {
            return "Elegí una opción de género válida.";
        }

        return null;
    }
}