<?php

namespace app;

use app\controller\EquipoController;
use app\controller\LoginController;
use app\controller\RegistroController;
use app\model\LoginModel;
use app\model\RegistroModel;
use app\validator\CamposObligatorios;
use app\validator\EmailDisponible;
use app\validator\EmailValido;
use app\validator\PasswordMinima;
use app\validator\PasswordsCoinciden;
use app\validator\UsuarioDisponible;
use app\validator\UsuarioValido;
use app\validator\Validador;
use MustacheRender;
use MyDatabase;
use Router;

require_once("helper/Autoloader.php");

class Configuration
{
    public function getEquipoController()
    {
        return new EquipoController($this->getRender());
    }
    public function getLoginController()
    {
        return new LoginController($this->getRender(), new LoginModel($this->getDatabase()));
    }

    public function getRegistroController()
    {
        $model = new RegistroModel($this->getDatabase());

        return new RegistroController($this->getRender(), $model, $this->getRegistroValidador($model));
    }

    private function getRegistroValidador($model)
    {
        return new Validador([
            new CamposObligatorios(),
            new UsuarioValido(),
            new EmailValido(),
            new PasswordMinima(),
            new PasswordsCoinciden(),
            new UsuarioDisponible($model),
            new EmailDisponible($model),
        ]);
    }

    private function getDatabase()
    {
        $config = parse_ini_file("config/config.ini");

        return new MyDatabase($config["db_host"],
            $config["db_user"],
            $config["db_pass"],
            $config["db_name"]
        );
    }

    private function getRender()
    {
        return new MustacheRender();
    }

    public function getRouter()
    {
        return new Router($this, "login", "show");
    }
}