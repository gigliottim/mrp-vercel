<?php

namespace App\Controllers\Productos;

use App\Core\Controllers\Controller;
use App\Core\Http\Response;
use App\Models\Bom;

class BomController extends Controller
{
    private Bom $bomModel;

    public function __construct(?Bom $bom = null)
    {
        $this->bomModel = $bom ?? new Bom();
    }

    public function index(): Response
    {
        $boms = $this->bomModel->getAllActive();

        return $this->render('pages/productos/bom/index', [
            'title' => 'Listado de BOMs Activas',
            'boms' => $boms
        ]);
    }
}
