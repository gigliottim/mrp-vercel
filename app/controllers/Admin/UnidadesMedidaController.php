<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\UnidadMedida;

final class UnidadesMedidaController extends Controller
{
    private UnidadMedida $unidades;

    public function __construct(?UnidadMedida $unidades = null)
    {
        $this->unidades = $unidades ?? new UnidadMedida();
    }

    public function index(Request $request): Response
    {
        return $this->render('pages/admin/catalogo/unidades/index', [
            'title' => 'Unidades de medida',
            'unidades' => $this->unitsForView(),
            'editing' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function edit(Request $request, $id): Response
    {
        $id = (int) $id;
        $editing = $this->unidades->find($id);
        if ($editing === null) {
            return Response::redirect(url('/configuracion/unidades'));
        }

        return $this->render('pages/admin/catalogo/unidades/index', [
            'title' => 'Editar unidad',
            'unidades' => $this->unitsForView(),
            'editing' => $editing,
            'errors' => [],
            'old' => $editing,
        ]);
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->validateData($request, null);
        if ($errors !== []) {
            return $this->render('pages/admin/catalogo/unidades/index', [
                'title' => 'Unidades de medida',
                'unidades' => $this->unitsForView(),
                'editing' => null,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->unidades->create($data);
        return Response::redirect(url('/configuracion/unidades'));
    }

    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        $existing = $this->unidades->find($id);
        if ($existing === null) {
            return Response::redirect(url('/configuracion/unidades'));
        }

        if ($this->unidades->isProtectedById($id)) {
            return $this->render('pages/admin/catalogo/unidades/index', [
                'title' => 'Editar unidad',
                'unidades' => $this->unitsForView(),
                'editing' => $existing,
                'errors' => ['general' => 'La unidad está protegida por el sistema y no puede editarse.'],
                'old' => $existing,
            ]);
        }

        [$data, $errors] = $this->validateData($request, $id);
        if ($errors !== []) {
            return $this->render('pages/admin/catalogo/unidades/index', [
                'title' => 'Editar unidad',
                'unidades' => $this->unitsForView(),
                'editing' => $this->unidades->find($id),
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->unidades->update($id, $data);
        return Response::redirect(url('/configuracion/unidades'));
    }

    public function destroy(Request $request, $id): Response
    {
        $id = (int) $id;
        $record = $this->unidades->find($id);
        if ($record === null) {
            return Response::redirect(url('/configuracion/unidades'));
        }

        if ($this->unidades->isProtectedById($id)) {
            return $this->render('pages/admin/catalogo/unidades/index', [
                'title' => 'Unidades de medida',
                'unidades' => $this->unitsForView(),
                'editing' => null,
                'errors' => ['general' => 'La unidad seleccionada está protegida por el sistema y no puede eliminarse.'],
                'old' => [],
            ]);
        }

        if ($this->unidades->hasReferences($id)) {
            return $this->render('pages/admin/catalogo/unidades/index', [
                'title' => 'Unidades de medida',
                'unidades' => $this->unitsForView(),
                'editing' => null,
                'errors' => ['general' => 'La unidad está en uso y no puede eliminarse.'],
                'old' => [],
            ]);
        }

        $this->unidades->delete($id);
        return Response::redirect(url('/configuracion/unidades'));
    }

    private function validateData(Request $request, ?int $editingId = null): array
    {
        $body = $request->body;
        $data = [
            'tipo' => trim((string) ($body['tipo'] ?? 'longitud')),
            'unidad' => trim((string) ($body['unidad'] ?? '')),
            'simbolo' => trim((string) ($body['simbolo'] ?? '')),
            'equivalencia_base' => (float) ($body['equivalencia_base'] ?? 1),
            'es_base' => isset($body['es_base']) ? 1 : 0,
            'activo' => isset($body['activo']) ? 1 : 0,
        ];

        $errors = [];
        $validTypes = ['longitud', 'superficie', 'volumen', 'masa', 'tiempo', 'temperatura', 'unidad'];
        if (!in_array($data['tipo'], $validTypes, true)) {
            $errors['tipo'] = 'Tipo inválido.';
        }
        if ($data['unidad'] === '') {
            $errors['unidad'] = 'Nombre requerido.';
        }
        if ($data['simbolo'] === '') {
            $errors['simbolo'] = 'Símbolo requerido.';
        }
        if ($data['equivalencia_base'] == 0) {
            $errors['equivalencia_base'] = 'La equivalencia no puede ser cero.';
        }

        // Validar que solo puede haber una unidad base por tipo
        if ($data['es_base'] === 1) {
            $existingBase = $this->unidades->findBaseByTipo($data['tipo'], $editingId);
            if ($existingBase !== null) {
                $errors['es_base'] = 'Ya existe una unidad base para este tipo: ' . $existingBase['unidad'] . ' (' . $existingBase['simbolo'] . ')';
            }
        }

        return [$data, $errors];
    }

    private function unitsForView(): array
    {
        return $this->unidades->decorateEffectiveFlags($this->unidades->all(200, 0));
    }
}
