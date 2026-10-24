<?php

namespace app;

use app\controller\EquipoController;
use app\controller\LoginController;
use app\controller\PerfilController;
use app\controller\RegistroController;
use app\model\LoginModel;
use app\model\PerfilModel;
use app\model\RegistroModel;
use app\validator\CamposObligatorios;
use app\validator\EmailDisponible;
use app\validator\EmailValido;
use app\validator\FechaNacimientoValida;
use app\validator\FotoPerfilValida;
use app\validator\GeneroValido;
use app\validator\NombreCompletoValido;
use app\validator\PasswordMinima;
use app\validator\PasswordsCoinciden;
use app\validator\UbicacionValida;
use app\validator\UsuarioDisponible;
use app\validator\UsuarioValido;
use app\validator\Validador;
use MustacheRender;
use MyDatabase;
use Router;
// PHP encuentra solo el archivo de cada clase cuando se la usa
require_once("helper/Autoloader.php");

class Configuration
{
    // Crea el controlador de equipo: render(vistas)
    public function getEquipoController()
    {
        return new EquipoController($this->getRender());
    }
    // Crea el controlador del login render(vistas), modelo(bd)
    public function getLoginController()
    {
        return new LoginController($this->getRender(), new LoginModel($this->getDatabase()));
    }
    // Crea el controlador de perfil: render(vistas), modelo(bd)
    public function getPerfilController()
    {
        return new PerfilController($this->getRender(), new PerfilModel($this->getDatabase()));
    }
    // Crea el controlador del registro render(vistas), modelo(bd), validador(reglas)
    public function getRegistroController()
    {
        $model = new RegistroModel($this->getDatabase());

        return new RegistroController($this->getRender(), $model, $this->getRegistroValidador($model));
    }
    // Arma el validador del registro con las reglas. Se ejecutan en orden y frenan en la primera que falla
    private function getRegistroValidador($model)
    {
        return new Validador([
            new CamposObligatorios(),
            new NombreCompletoValido(),
            new FechaNacimientoValida(),
            new GeneroValido(),
            new UbicacionValida(),
            new FotoPerfilValida(),
            new UsuarioValido(),
            new EmailValido(),
            new PasswordMinima(),
            new PasswordsCoinciden(),
            new UsuarioDisponible($model),
            new EmailDisponible($model),
        ]);
    }

    // Crea la conexión a la bd con los datos de config.ini
    private function getDatabase()
    {
        $config = parse_ini_file("config/config.ini");

        return new MyDatabase($config["db_host"],
            $config["db_user"],
            $config["db_pass"],
            $config["db_name"]
        );
    }

    // Crea el objeto que dibuja las vistas
    private function getRender()
    {
        return new MustacheRender();
    }

    // Crea el Router (lee la URL y decide qué controlador ejecutar)
    public function getRouter()
    {
        return new Router($this, "login", "show"); // Muestra el login por defecto
    }
}
