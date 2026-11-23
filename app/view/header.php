<?php
$rutaActual = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$activo = function ($prefijo) use ($rutaActual) {
    return strpos($rutaActual, $prefijo) === 0 ? ' activo' : '';
};
$logueado = isset($_SESSION['usuario_id']);
$rolActual = $_SESSION['rol'] ?? '';
$esMaestro = $rolActual === 'maestro';
$esAdmin = in_array($rolActual, ['admin', 'maestro'], true);
$esEditor = in_array($rolActual, ['editor', 'admin', 'maestro'], true);
$enMenuPerfil = strpos($rutaActual, '/perfil') === 0
    || strpos($rutaActual, '/editor') === 0
    || strpos($rutaActual, '/admin') === 0
    || strpos($rutaActual, '/maestro') === 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartTrivia</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="/public/css/app.css?v=<?= @filemtime(__DIR__ . '/../../public/css/app.css') ?: 2 ?>">
    <?php if ($logueado): ?>
        <script src="/public/js/sonidos.js"></script>
    <?php else: ?>
        <script>try { sessionStorage.removeItem('st_pos'); } catch (e) {}</script>
    <?php endif; ?>
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
            <a href="/ranking/show" class="nav-link<?= $activo('/ranking') ?>">Rankings</a>
            <a href="/partida/iniciar" class="nav-link<?= $activo('/partida') ?>">Jugar</a>
            <a href="/sugerencia/show" class="nav-link<?= $activo('/sugerencia') ?>">Sugerir pregunta</a>
            <span class="nav-separador"></span>

            <div class="nav-perfil-wrap" id="nav-perfil-wrap">
                <button type="button" class="nav-perfil<?= $enMenuPerfil ? ' activo' : '' ?>" id="nav-perfil-btn" aria-expanded="false" aria-haspopup="true">
                    <?php if (!empty($_SESSION['foto_perfil'])): ?>
                        <img src="/public/img/perfiles/<?= htmlspecialchars($_SESSION['foto_perfil']) ?>" alt="Foto de perfil">
                    <?php else: ?>
                        <span class="nav-avatar">👤</span>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($_SESSION['usuario'] ?? 'Mi perfil') ?></span>
                    <span class="nav-caret" aria-hidden="true">▾</span>
                </button>

                <div class="nav-dropdown" id="nav-perfil-menu" role="menu">
                    <a href="/perfil/show" class="nav-dropdown-item<?= $rutaActual === '/perfil/show' || $rutaActual === '/perfil' ? ' activo' : '' ?>" role="menuitem">👤 Mi perfil</a>
                    <a href="/perfil/editar" class="nav-dropdown-item<?= $activo('/perfil/editar') ?>" role="menuitem">✏️ Editar perfil</a>
                    <a href="/perfil/historial" class="nav-dropdown-item<?= $activo('/perfil/historial') ?>" role="menuitem">📜 Ver historial de partidas</a>

                    <?php if ($esEditor): ?>
                        <div class="nav-dropdown-sep"></div>
                        <a href="/editor/show" class="nav-dropdown-item<?= $activo('/editor') ?>" role="menuitem">🛠️ Panel de editor</a>
                    <?php endif; ?>

                    <?php if ($esAdmin): ?>
                        <a href="/admin/show" class="nav-dropdown-item<?= $activo('/admin') ?>" role="menuitem">⚙️ Panel de administración</a>
                    <?php endif; ?>

                    <?php if ($esMaestro): ?>
                        <a href="/maestro/show" class="nav-dropdown-item nav-dropdown-maestro<?= $activo('/maestro') ?>" role="menuitem">👑 Control maestro</a>
                    <?php endif; ?>
                </div>
            </div>

            <a href="/login/logout" class="nav-salir">Salir</a>
        </nav>

        <script>
            (function () {
                var wrap = document.getElementById('nav-perfil-wrap');
                var btn = document.getElementById('nav-perfil-btn');
                if (!wrap || !btn) { return; }
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var abierto = wrap.classList.toggle('abierto');
                    btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                });
                document.addEventListener('click', function (e) {
                    if (!wrap.contains(e.target)) {
                        wrap.classList.remove('abierto');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        wrap.classList.remove('abierto');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });
            })();
        </script>
        <?php endif; ?>
    </div>
</header>

<main class="contenido">
