<?php

namespace app;
use app\controller\Error404Controller;
use app\controller\AdminController;
use app\controller\EditorController;
use app\controller\HomeController;
use app\controller\LoginController;
use app\controller\MaestroController;
use app\controller\PerfilController;
use app\controller\RankingController;
use app\controller\RegistroController;
use app\controller\PartidaController;
use app\controller\ReporteController;
use app\controller\SugerenciaController;
use app\model\AdminModel;
use app\model\MaestroModel;
use app\model\PartidaModel;
use app\model\LoginModel;
use app\model\PerfilModel;
use app\model\PreguntaModel;
use app\model\RankingModel;
use app\model\RegistroModel;
use app\model\ReporteModel;
use app\validator\LongitudesPregunta;
use app\validator\CamposObligatorios;
use app\validator\CategoriaExistente;
use app\validator\EmailDisponible;
use app\validator\EmailValido;
use app\validator\FechaNacimientoValida;
use app\validator\FotoPerfilValida;
use app\validator\GeneroValido;
use app\validator\NombreCompletoValido;
use app\validator\PasswordMinima;
use app\validator\PasswordsCoinciden;
use app\validator\RespuestasDistintas;
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
    // Crea el controlador del home: render(vistas)
    public function getHomeController()
    {
        return new HomeController($this->getRender(), new PartidaModel($this->getDatabase()));
    }
    // Crea el controlador del login render(vistas), modelo(bd)
    public function getLoginController()
    {
        return new LoginController($this->getRender(), new LoginModel($this->getDatabase()));
    }
    // Crea el controlador de la partida: render(vistas), modelo(bd)
    public function getPartidaController()
    {
        return new PartidaController($this->getRender(), new PartidaModel($this->getDatabase()));
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

    public function getAdminController()
    {
        return new AdminController($this->getRender(), new AdminModel($this->getDatabase()));
    }

    // Editor: render(vistas), modelo de preguntas, modelo de reportes, validador de la pregunta editada.
    // Los dos modelos comparten la misma conexión a la bd
    public function getEditorController()
    {
        $database = $this->getDatabase();
        $preguntaModel = new PreguntaModel($database);

        return new EditorController(
            $this->getRender(),
            $preguntaModel,
            new ReporteModel($database),
            $this->getPreguntaValidador($preguntaModel)
        );
    }

    public function getMaestroController()
    {
        return new MaestroController($this->getRender(), new MaestroModel($this->getDatabase()));
    }

    // Sugerir pregunta: render(vistas), modelo de preguntas, validador
    public function getSugerenciaController()
    {
        $model = new PreguntaModel($this->getDatabase());

        return new SugerenciaController($this->getRender(), $model, $this->getPreguntaValidador($model));
    }

    // Reportar pregunta: modelo de reportes + modelo de partidas (para saber qué pregunta le tocó al usuario)
    public function getReporteController()
    {
        $database = $this->getDatabase();

        return new ReporteController(new ReporteModel($database), new PartidaModel($database));
    }


    public function getRankingController()
    {
        return new RankingController($this->getRender(), new RankingModel($this->getDatabase()));
    }

    public function getError404Controller(){
        return new Error404Controller($this->getRender());
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

    // Arma el validador de una pregunta (sugerida o editada). Mismas reglas para los dos formularios
    private function getPreguntaValidador($model)
    {
        return new Validador([
            new CamposObligatorios(),
            new LongitudesPregunta(),
            new RespuestasDistintas(),
            new CategoriaExistente($model),
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
