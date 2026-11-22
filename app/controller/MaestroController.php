<?php

namespace app\controller;

use Redirect;
use Request;

class MaestroController
{
    private $render;
    private $model;

    public function __construct($render, $model)
    {
        $this->render = $render;
        $this->model = $model;
    }

    private function formatearUsuario($u, $maestroId)
    {
        $rol = $u['rol'] ?? 'usuario';
        $estado = $u['estado'] ?? 'activo';
        $esMaestro = $rol === 'maestro' || (int)$u['id'] === $maestroId;

        $etiquetasRol = [
            'maestro' => 'Control Maestro',
            'admin' => 'Administrador',
            'editor' => 'Editor',
            'usuario' => 'Usuario',
        ];

        $badgeRolColor = '#64748b';
        if ($rol === 'maestro') $badgeRolColor = '#7c3aed';
        elseif ($rol === 'admin') $badgeRolColor = '#d9534f';
        elseif ($rol === 'editor') $badgeRolColor = '#0284c7';

        $badgeEstadoColor = '#16a34a';
        if ($estado === 'baneado') $badgeEstadoColor = '#dc2626';
        elseif ($estado === 'pendiente') $badgeEstadoColor = '#d97706';

        return [
            'id' => (int)$u['id'],
            'usuario' => $u['usuario'],
            'nombre_completo' => $u['nombre_completo'],
            'email' => $u['email'],
            'rol' => $rol,
            'rol_label' => $etiquetasRol[$rol] ?? ucfirst($rol),
            'estado' => $estado,
            'partidas_jugadas' => (int)($u['partidas_jugadas'] ?? 0),
            'fecha_registro' => date('d/m/Y', strtotime($u['fecha_registro'])),
            'es_maestro' => $esMaestro,
            'es_usuario' => $rol === 'usuario',
            'es_editor' => $rol === 'editor',
            'es_admin' => $rol === 'admin',
            'tiene_privilegio' => in_array($rol, ['editor', 'admin'], true),
            'esta_baneado' => $estado === 'baneado',
            'badge_rol_color' => $badgeRolColor,
            'badge_estado_color' => $badgeEstadoColor,
        ];
    }

    public function show()
    {
        $maestroId = (int)($_SESSION['usuario_id'] ?? 0);
        $resumen = $this->model->obtenerResumenUsuarios();
        $filasStaff = $this->model->obtenerStaff();
        $filasUsuarios = $this->model->obtenerHistorialUsuarios();

        $staff = [];
        foreach ($filasStaff as $s) {
            $staff[] = $this->formatearUsuario($s, $maestroId);
        }

        $usuarios = [];
        foreach ($filasUsuarios as $u) {
            $usuarios[] = $this->formatearUsuario($u, $maestroId);
        }

        // Búsqueda de usuario por nombre / usuario / email
        $busqueda = trim((string)Request::get('q', ''));
        $resultadosBusqueda = [];
        if ($busqueda !== '') {
            foreach ($this->model->buscarUsuarios($busqueda) as $b) {
                $resultadosBusqueda[] = $this->formatearUsuario($b, $maestroId);
            }
        }

        $mensajesOk = [
            'rol' => 'Rol actualizado correctamente.',
            'baneado' => 'Usuario baneado. Ya no podrá iniciar sesión.',
            'desbaneado' => 'Usuario desbaneado y reactivado.',
            'creado' => 'Nuevo integrante de equipo creado correctamente.',
        ];
        $mensajesError = [
            'protegido' => 'No se puede modificar ni banear una cuenta de Control Maestro.',
            'datos' => 'Completá todos los campos (contraseña mínima de 6 caracteres).',
            'duplicado' => 'Ya existe una cuenta con ese usuario o email.',
            'rol' => 'El rol seleccionado no es válido.',
        ];

        $okKey = Request::get('ok', '');
        $errKey = Request::get('error', '');

        $this->render->renderiza("maestro", [
            'usuario' => $_SESSION['usuario'] ?? 'maestro',
            'resumen' => $resumen,
            'staff' => $staff,
            'hay_staff' => count($staff) > 0,
            'usuarios' => $usuarios,
            'busqueda' => $busqueda,
            'hay_busqueda' => $busqueda !== '',
            'resultados_busqueda' => $resultadosBusqueda,
            'hay_resultados_busqueda' => count($resultadosBusqueda) > 0,
            'mensaje_ok' => $mensajesOk[$okKey] ?? null,
            'mensaje_error' => $mensajesError[$errKey] ?? null,
        ]);
    }

    public function cambiarRol()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/maestro/show');
            return;
        }

        $maestroId = (int)($_SESSION['usuario_id'] ?? 0);
        $usuarioId = (int)Request::post('usuario_id', 0);
        $nuevoRol = trim((string)Request::post('rol', ''));
        $q = trim((string)Request::post('q', ''));
        $sufijoQ = $q !== '' ? '&q=' . urlencode($q) : '';

        if ($usuarioId === $maestroId || $usuarioId <= 0) {
            Redirect::to('/maestro/show?error=protegido' . $sufijoQ);
            return;
        }

        if (!$this->model->cambiarRol($usuarioId, $nuevoRol)) {
            Redirect::to('/maestro/show?error=rol' . $sufijoQ);
            return;
        }

        Redirect::to('/maestro/show?ok=rol' . $sufijoQ);
    }

    public function banear()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/maestro/show');
            return;
        }

        $maestroId = (int)($_SESSION['usuario_id'] ?? 0);
        $usuarioId = (int)Request::post('usuario_id', 0);
        $accion = Request::post('accion', 'banear');
        $q = trim((string)Request::post('q', ''));
        $sufijoQ = $q !== '' ? '&q=' . urlencode($q) : '';

        if ($usuarioId === $maestroId || $usuarioId <= 0) {
            Redirect::to('/maestro/show?error=protegido' . $sufijoQ);
            return;
        }

        $banear = ($accion === 'banear');
        $this->model->cambiarEstadoBaneo($usuarioId, $banear);

        Redirect::to('/maestro/show?ok=' . ($banear ? 'baneado' : 'desbaneado') . $sufijoQ);
    }

    public function crearStaff()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/maestro/show');
            return;
        }

        $usuario = trim((string)Request::post('usuario', ''));
        $nombreCompleto = trim((string)Request::post('nombre_completo', ''));
        $email = trim((string)Request::post('email', ''));
        $password = (string)Request::post('password', '');
        $rol = trim((string)Request::post('rol', 'editor'));

        if ($usuario === '' || $nombreCompleto === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            Redirect::to('/maestro/show?error=datos');
            return;
        }

        if ($this->model->existeUsuarioOEmail($usuario, $email)) {
            Redirect::to('/maestro/show?error=duplicado');
            return;
        }

        if (!$this->model->crearUsuarioConRol($usuario, $email, $nombreCompleto, $password, $rol)) {
            Redirect::to('/maestro/show?error=rol');
            return;
        }

        Redirect::to('/maestro/show?ok=creado');
    }
}
