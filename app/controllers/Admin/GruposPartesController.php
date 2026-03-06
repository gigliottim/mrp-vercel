<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\GrupoParte;

final class GruposPartesController extends Controller
{
    private GrupoParte $grupos;

    public function __construct(?GrupoParte $grupos = null)
    {
        $this->grupos = $grupos ?? new GrupoParte();
    }

    public function index(Request $request): Response
    {
        return $this->render('pages/admin/grupos/index', [
            'title' => 'Grupos de partes',
            'grupos' => $this->grupos->activos(),
            'editing' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function edit(Request $request, $id): Response
    {
        $id = (int) $id;
        $editing = $this->grupos->find($id);
        if ($editing === null) {
            return Response::redirect(url('/configuracion/grupos-partes'));
        }

        return $this->render('pages/admin/grupos/index', [
            'title' => 'Editar grupo',
            'grupos' => $this->grupos->activos(),
            'editing' => $editing,
            'errors' => [],
            'old' => $editing,
        ]);
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->validateData($request);
        if ($errors !== []) {
            return $this->render('pages/admin/grupos/index', [
                'title' => 'Grupos de partes',
                'grupos' => $this->grupos->activos(),
                'editing' => null,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->grupos->create($data);
        return Response::redirect(url('/configuracion/grupos-partes'));
    }

    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        if ($this->grupos->find($id) === null) {
            return Response::redirect(url('/configuracion/grupos-partes'));
        }

        [$data, $errors] = $this->validateData($request);
        if ($errors !== []) {
            return $this->render('pages/admin/grupos/index', [
                'title' => 'Editar grupo',
                'grupos' => $this->grupos->activos(),
                'editing' => $this->grupos->find($id),
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->grupos->update($id, $data);
        return Response::redirect(url('/configuracion/grupos-partes'));
    }

    public function destroy(Request $request, $id): Response
    {
        $this->grupos->delete((int) $id);
        return Response::redirect(url('/configuracion/grupos-partes'));
    }

    private function validateData(Request $request): array
    {
        $body = $request->body;
        $data = [
            'codigo' => strtoupper(trim((string) ($body['codigo'] ?? ''))),
            'nombre' => trim((string) ($body['nombre'] ?? '')),
            'descripcion' => trim((string) ($body['descripcion'] ?? '')),
            'color' => trim((string) ($body['color'] ?? '#0d6efd')),
            'activo' => isset($body['activo']) ? 1 : 0,
        ];

        $errors = [];
        if ($data['codigo'] === '') {
            $errors['codigo'] = 'Ingresa un código.';
        }
        if ($data['nombre'] === '') {
            $errors['nombre'] = 'Ingresa un nombre.';
        }
        if (preg_match('/^#([0-9A-Fa-f]{6})$/', $data['color']) !== 1) {
            $errors['color'] = 'Color inválido.';
        }

        return [$data, $errors];
    }
}
