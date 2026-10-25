<?php
use app\Configuration;

session_start();
require_once("app/Configuration.php");

// Lee el mapa de rutas y permisos desde el archivo de configuración
$routes = require("config/routes.php");

// Capturamos el controlador y el método de la URL
$controller = Request::get("controller", null);
$method     = Request::get("method", null);

// 1. Si entra a la raíz de la app (sin controlador en la URL)
if ($controller === null) {
    if (isset($_SESSION['usuario_id'])) {
        Redirect::to('/equipo'); // Si está logueado, va al home
    } else {
        Redirect::to('/login');  // Si no está logueado, va al login
    }
    exit;
}

// 2. Si el controlador NO existe en el mapa de rutas, mostramos el Error 404 limpio
if (!array_key_exists($controller, $routes)) {
    $configuration = new Configuration();
    $errorController = $configuration->getError404Controller();
    $errorController->notFound();
    exit;
}

// Si se especificó un controlador existente, buscamos su permiso en el mapa
$permiso = $routes[$controller];

// 3. Ruta pública (login/registro) con sesión ya iniciada → redirigir al home (excepto si quiere salir)
if ($permiso === 'publica' && isset($_SESSION['usuario_id']) && $method !== 'logout') {
    Redirect::to('/equipo');
    exit;
}

// 4. Ruta protegida sin sesión iniciada → redirigir AL LOGIN
if ($permiso !== 'publica' && !isset($_SESSION['usuario_id'])) {
    Redirect::to('/login');
    exit;
}

// 5. Validación centralizada de roles y jerarquías
$rolesPermitidos = [
    'admin'   => ['admin'],                    // Solo administradores
    'editor'  => ['editor', 'admin'],          // Editores y administradores
    'usuario' => ['usuario', 'editor', 'admin']// Cualquier rol con sesión activa
];

if (isset($rolesPermitidos[$permiso])) {
    $rolActual = $_SESSION['rol'] ?? '';
    if (!in_array($rolActual, $rolesPermitidos[$permiso])) {
        Redirect::to('/equipo');
        exit;
    }
}
// Si pasó todas las validaciones de seguridad, despachamos el controlador y método correspondiente
$configuration = new Configuration();
$router = $configuration->getRouter();

$router->dispatch(
    $controller,
    $method ?? 'show' // Si no viene método por URL, por defecto usamos 'show'
);