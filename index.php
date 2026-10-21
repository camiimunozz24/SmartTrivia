<?php
use app\Configuration;

session_start();
require_once("app/Configuration.php");

$configuration = new Configuration();
$router = $configuration->getRouter();

$router->dispatch(
    Request::get("controller", "login"), // Indica que controlador usar
    Request::get("method", "show") // Indica que metodo ejecutar
);