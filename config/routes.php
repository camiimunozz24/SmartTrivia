<?php

/**
 * Mapa de rutas y permisos.
*/
return [
    // Rutas públicas (no requieren sesión)
    'login' => 'publica',
    'registro' => 'publica',
    // Rutas para cualquier usuario con sesión
    'equipo' => 'usuario',
    'perfil' => 'usuario',
    // Rutas exclusivas para editores (también accesibles por admin)
    // 'preguntas' => 'editor',
    // Rutas exclusivas para administradores
    'admin'     => 'admin'
];