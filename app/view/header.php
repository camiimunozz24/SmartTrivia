<?php
$rutaActual = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$activo = function ($prefijo) use ($rutaActual) {
    return strpos($rutaActual, $prefijo) === 0 ? ' activo' : '';
};
$logueado = isset($_SESSION['usuario_id']);
$esAdmin = ($_SESSION['rol'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartTrivia</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="/public/css/app.css">
</head>
<body>

<header class="nav">
    <div class="nav-interior">
        <a href="<?= $logueado ? '/home/show' : '/login' ?>" class="nav-marca">🧠 Smart<span>Trivia</span></a>

        <?php if ($logueado): ?>
            <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Abrir menú">
            <label for="nav-toggle" class="nav-hamburguesa" aria-hidden="true"><span></span><span></span><span></span></label>

            <nav class="nav-links">
                <a href="/home/show" class="nav-link<?= $activo('/home') ?>">Inicio</a>
                <a href="/partida/iniciar" class="nav-link<?= $activo('/partida') ?>">Jugar</a>
                <span class="nav-separador"></span>
                <a href="/perfil/show" class="nav-perfil<?= $activo('/perfil') ?>">
                    <?php if (!empty($_SESSION['foto_perfil'])): ?>
                        <img src="/public/img/perfiles/<?= htmlspecialchars($_SESSION['foto_perfil']) ?>" alt="Foto de perfil">
                    <?php else: ?>
                        <span class="nav-avatar">👤</span>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($_SESSION['usuario'] ?? 'Mi perfil') ?></span>
                </a>
                <a href="/login/logout" class="nav-salir">Salir</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="contenido">