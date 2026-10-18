<?php

class MyDatabase
{
    private $conexion;

    public function __construct($host, $user, $pass, $db)
    {
        $this->conexion = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    }

    public function queryOne(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        $fila = $resultado->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $fila;
    }

    public function queryAll(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        return $resultado->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        return $resultado->rowCount();
    }
    public function lastInsertId()
    {
        return $this->conexion->lastInsertId();
    }
}

