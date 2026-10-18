<?php

namespace app\validator;
class Validador
{
    private $reglas;

    public function __construct(array $reglas)
    {
        $this->reglas = $reglas;
    }

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