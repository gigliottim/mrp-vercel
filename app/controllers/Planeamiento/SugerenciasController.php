<?php

namespace App\Controllers\Planeamiento;

use App\Core\Controllers\Controller;
use App\Core\Http\Response;

class SugerenciasController extends Controller
{
    public function index(): Response
    {
        return $this->render('pages/placeholder', ['title' => 'Sugerencias MRP']);
    }
}
