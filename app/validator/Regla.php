<?php

namespace app\validator;
interface Regla
{
    // Devuelve el mensaje de error si la regla no se cumple, o null si está todo bien.
    public function validar(array $datos);
}