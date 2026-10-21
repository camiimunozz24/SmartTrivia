<?php

namespace app\validator;
class UbicacionValida implements Regla
{
    // Revisa país y ciudad: que no estén vacíos y no más de 100 caracteres
    public function validar(array $datos)
    {
        $pais = $datos['pais'] ?? '';
        $ciudad = $datos['ciudad'] ?? '';

        if ($pais === '' || $ciudad === '') {
            return "Seleccioná tu ubicación en el mapa.";
        }

        if (mb_strlen($pais) > 100 || mb_strlen($ciudad) > 100) {
            return "La ubicación seleccionada no es válida.";
        }

        return null;
    }
}