<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartTrivia</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
</head>
<body>

<div class="w3-bar w3-green">
    <a href="/" class="w3-bar-item w3-button">🏠 Inicio</a>

    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="/login/logout" class="w3-bar-item w3-button w3-right">Cerrar sesión</a>
        <span class="w3-bar-item w3-right">👤 <?= htmlspecialchars($_SESSION['usuario']) ?></span>
    <?php endif; ?>
</div>
