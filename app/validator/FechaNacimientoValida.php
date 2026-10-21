<?php

namespace app\validator;
class FechaNacimientoValida implements Regla
{
    // Revisa la fecha de nacimiento: formato, no fechas futuras, no más de 120 años
    public function validar(array $datos)
    {
        $texto = $datos['fecha_nacimiento'] ?? '';
        $fecha = \DateTime::createFromFormat('Y-m-d', $texto);

        if (!$fecha || $fecha->format('Y-m-d') !== $texto) {
            return "Ingresá una fecha de nacimiento válida.";
        }

        $hoy = new \DateTime('today');
        if ($fecha > $hoy) {
            return "La fecha de nacimiento no puede ser futura.";
        }

        if ($fecha->diff($hoy)->y > 120) {
            return "Ingresá una fecha de nacimiento válida.";
        }

        return null;
    }
}