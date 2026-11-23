<?php

/**
 * Mapa de rutas y permisos.
*/
return [
    // Rutas públicas (no requieren sesión)
    'login' => 'publica',
    'registro' => 'publica',
    // Rutas para cualquier usuario con sesión
    'home' => 'usuario',

    // Perfil: 'show' es público (acceso por QR sin sesión); el resto requiere sesión
    'perfil' => [
        'show' => 'publica',  // Ver perfil vía QR sin estar logueado
        '*'    => 'usuario',  // Cualquier otro método sí requiere sesión
    ],


    'partida' => 'usuario',
    'ranking' => 'usuario',

    // Cualquier usuario con sesión puede sugerir una pregunta y reportar la que le tocó al perder
    'sugerencia' => 'usuario',
    'reporte'    => 'usuario',

    // Rutas exclusivas para editores (también accesibles por admin)
    'editor'    => 'editor',

    // Rutas exclusivas para administradores (y Control Maestro)
    'admin'     => 'admin',

    // Rutas exclusivas para Control Maestro (gestión de roles y baneos)
    'maestro'   => 'maestro'
];