<?php
namespace app\controller;

namespace app\controller;

class Error404Controller
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function notFound()
    {
        http_response_code(404);
        $this->render->renderiza("404", []);
    }
}