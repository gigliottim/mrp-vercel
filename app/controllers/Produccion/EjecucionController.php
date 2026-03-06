<?php

namespace App\Controllers\Produccion;

use App\Core\Controllers\Controller;
use App\Core\Http\Response;

class EjecucionController extends Controller
{
    public function index(): Response
    {
        return $this->render('pages/placeholder', ['title' => 'Ejecución y Movimientos']);
    }
}
