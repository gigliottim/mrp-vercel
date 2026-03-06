<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\TipoDeposito;

final class TiposDepositosController extends Controller
{
    private TipoDeposito $tipos;

    public function __construct(?TipoDeposito $tipos = null)
    {
        $this->tipos = $tipos ?? new TipoDeposito();
    }

    public function index(Request $request): Response
    {
        return $this->render('pages/admin/catalogo/depositos/index', [
            'title' => 'Tipos de depósito',
            'tipos' => $this->tipos->activos(),
            'editing' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function edit(Request $request, $id): Response
    {
        $id = (int) $id;
        $editing = $this->tipos->find($id);
        if ($editing === null) {
            return Response::redirect(url('/configuracion/tipos-depositos'));
        }

        // Los tipos del sistema no se pueden editar
        if ((bool) $editing['es_sistema']) {
            return Response::redirect(url('/configuracion/tipos-depositos'));
        }

        return $this->render('pages/admin/catalogo/depositos/index', [
            'title' => 'Editar tipo de depósito',
            'tipos' => $this->tipos->activos(),
            'editing' => $editing,
            'errors' => [],
            'old' => $editing,
        ]);
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->validateData($request);
        if ($errors !== []) {
            return $this->render('pages/admin/catalogo/depositos/index', [
                'title' => 'Tipos de depósito',
                'tipos' => $this->tipos->activos(),
                'editing' => null,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->tipos->create($data);
        return Response::redirect(url('/configuracion/tipos-depositos'));
    }

    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        $existing = $this->tipos->find($id);
        if ($existing === null) {
            return Response::redirect(url('/configuracion/tipos-depositos'));
        }

        // Los tipos del sistema no se pueden editar
        if ((bool) $existing['es_sistema']) {
            return Response::redirect(url('/configuracion/tipos-depositos'));
        }

        [$data, $errors] = $this->validateData($request);
        if ($errors !== []) {
            return $this->render('pages/admin/catalogo/depositos/index', [
                'title' => 'Editar tipo de depósito',
                'tipos' => $this->tipos->activos(),
                'editing' => $existing,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->tipos->update($id, $data);
        return Response::redirect(url('/configuracion/tipos-depositos'));
    }

    public function destroy(Request $request, $id): Response
    {
        $id = (int) $id;

        // No permitir eliminar tipos del sistema
        if ($this->tipos->esSistema($id)) {
            return Response::redirect(url('/configuracion/tipos-depositos'));
        }

        $this->tipos->delete($id);
        return Response::redirect(url('/configuracion/tipos-depositos'));
    }

    private function validateData(Request $request): array
    {
        $body = $request->body;
        $data = [
            'codigo' => strtoupper(trim((string) ($body['codigo'] ?? ''))),
            'nombre' => trim((string) ($body['nombre'] ?? '')),
            'descripcion' => trim((string) ($body['descripcion'] ?? '')),
            'orden' => (int) ($body['orden'] ?? 0),
            'activo' => isset($body['activo']) ? 1 : 0,
        ];

        $errors = [];

        if ($data['codigo'] === '') {
            $errors['codigo'] = 'Código requerido.';
        }
        if ($data['nombre'] === '') {
            $errors['nombre'] = 'Nombre requerido.';
        }

        return [$data, $errors];
    }
}
