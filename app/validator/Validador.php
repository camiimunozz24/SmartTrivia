<?php

namespace app\validator;
class Validador
{
    private $reglas;

    public function __construct(array $reglas)
    {
        $this->reglas = $reglas;
    }
    // Crea el metodo validador
    public function validar(array $datos)
    {
        foreach ($this->reglas as $regla) {
            $error = $regla->validar($datos);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }
}