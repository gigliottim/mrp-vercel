<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Response;

final class EmpresaUsuariosController extends Controller
{
    public function empresa(): Response
    {
        return $this->render('pages/admin/empresa-usuarios/empresa', [
            'empresas' => [],
            'editing' => null,
            'old' => [],
            'errors' => [],
        ]);
    }

    public function usuarios(): Response
    {
        return $this->render('pages/admin/empresa-usuarios/usuarios', [
            'usuarios' => [],
            'roles' => [],
            'editing' => null,
            'old' => [],
            'errors' => [],
        ]);
    }

    public function roles(): Response
    {
        return $this->render('pages/admin/empresa-usuarios/roles', [
            'roles' => [],
            'editing' => null,
            'old' => [],
            'errors' => [],
        ]);
    }

    public function permisos(): Response
    {
        return $this->render('pages/admin/empresa-usuarios/permisos', [
            'aclRows' => [],
            'menuTree' => [],
            'subjects' => [],
            'editing' => null,
            'old' => [],
            'errors' => [],
        ]);
    }
}
