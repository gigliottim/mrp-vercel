<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Response;

final class EmpresaUsuariosController extends Controller
{
    public function empresa(): Response
    {
        return $this->render('pages/placeholder', [
            'title' => 'Empresa',
        ]);
    }

    public function usuarios(): Response
    {
        return $this->render('pages/placeholder', [
            'title' => 'Usuarios',
        ]);
    }

    public function roles(): Response
    {
        return $this->render('pages/placeholder', [
            'title' => 'Roles',
        ]);
    }

    public function permisos(): Response
    {
        return $this->render('pages/placeholder', [
            'title' => 'Permisos',
        ]);
    }
}
