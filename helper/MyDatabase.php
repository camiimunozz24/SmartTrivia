<?php

class MyDatabase
{
    private $conexion;

    public function __construct($host, $user, $pass, $db)
    {
        // Conexion a la bd
        $this->conexion = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    }
    // Devuelve una sola fila o null
    public function queryOne(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        $fila = $resultado->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $fila;
    }
    // Devuelve todas la filas o un arreglo vacio
    public function queryAll(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        return $resultado->fetchAll(PDO::FETCH_ASSOC);
    }
    // Ejecuta consultas que modifican datos. Devuelve cant de filas afectadas
    public function execute(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        return $resultado->rowCount();
    }
    // Devuelve id de la ultima fila insertada
    public function lastInsertId()
    {
        return $this->conexion->lastInsertId();
    }
}

