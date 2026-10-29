<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartTrivia</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <style>
        .w3-bar.barra-flex {
            display: flex;
            align-items: center;
        }
        .w3-bar.barra-flex .w3-bar-item {
            float: none;
        }
        .w3-bar.barra-flex .usuario-header {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .usuario-header img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
        }
        body:has(.card-trivia-login) .btn-inicio,
        body:has(.card-trivia-registro) .btn-inicio {
            display: none !important;
        }
    </style>
</head>
<body>

<div class="w3-bar w3-green barra-flex">
    <a href="/home/show" class="w3-bar-item w3-button btn-inicio">🏠 Inicio</a>

    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="/perfil/show" class="w3-bar-item usuario-header w3-button" style="text-decoration:none;">
            <?php if (!empty($_SESSION['foto_perfil'])): ?>
                <img src="/public/img/perfiles/<?= htmlspecialchars($_SESSION['foto_perfil']) ?>"
                     alt="Foto de perfil">
            <?php else: ?>
                👤
            <?php endif; ?>
            Mi Perfil
        </a>
        <a href="/login/logout" class="w3-bar-item w3-button">Cerrar sesión</a>
    <?php endif; ?>
</div>
