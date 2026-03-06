<?php

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Response;

class ConfiguracionController extends Controller
{
    public function index(): Response
    {
        return $this->render('pages/placeholder', ['title' => 'Configuración General']);
    }
}
