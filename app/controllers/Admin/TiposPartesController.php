<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\TipoParte;

final class TiposPartesController extends Controller
{
    private TipoParte $tipos;

    public function __construct(?TipoParte $tipos = null)
    {
        $this->tipos = $tipos ?? new TipoParte();
    }

    public function index(Request $request): Response
    {
        return $this->render('pages/admin/catalogo/tipos/index', [
            'title' => 'Tipos de partes',
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
            return Response::redirect(url('/configuracion/tipos-partes'));
        }

        return $this->render('pages/admin/catalogo/tipos/index', [
            'title' => 'Editar tipo',
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
            return $this->render('pages/admin/catalogo/tipos/index', [
                'title' => 'Tipos de partes',
                'tipos' => $this->tipos->activos(),
                'editing' => null,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->tipos->create($data);
        return Response::redirect(url('/configuracion/tipos-partes'));
    }

    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        if ($this->tipos->find($id) === null) {
            return Response::redirect(url('/configuracion/tipos-partes'));
        }

        [$data, $errors] = $this->validateData($request);
        if ($errors !== []) {
            return $this->render('pages/admin/catalogo/tipos/index', [
                'title' => 'Editar tipo',
                'tipos' => $this->tipos->activos(),
                'editing' => $this->tipos->find($id),
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->tipos->update($id, $data);
        return Response::redirect(url('/configuracion/tipos-partes'));
    }

    public function destroy(Request $request, $id): Response
    {
        $this->tipos->delete((int) $id);
        return Response::redirect(url('/configuracion/tipos-partes'));
    }

    private function validateData(Request $request): array
    {
        $body = $request->body;
        $data = [
            'codigo' => strtoupper(trim((string) ($body['codigo'] ?? ''))),
            'nombre' => trim((string) ($body['nombre'] ?? '')),
            'descripcion' => trim((string) ($body['descripcion'] ?? '')),
            'requiere_stock' => isset($body['requiere_stock']) ? 1 : 0,
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
