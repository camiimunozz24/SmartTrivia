<?php
use app\Configuration;

session_start();
require_once("app/Configuration.php");

$configuration = new Configuration();
$router = $configuration->getRouter();

$router->dispatch(
    Request::get("controller", "equipo"),
    Request::get("method", "show")
);