<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\CentroTrabajo;
use RuntimeException;

final class CentrosTrabajoController extends Controller
{
    private CentroTrabajo $centros;

    public function __construct(?CentroTrabajo $centros = null)
    {
        $this->centros = $centros ?? new CentroTrabajo();
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) ($request->query['q'] ?? '')),
        ];

        $records = $this->centros->search($filters['q']);

        return $this->render('pages/admin/centros/index', [
            'title' => 'Centros de trabajo',
            'centros' => $records,
            'filters' => $filters,
            'editing' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function edit(Request $request, $id): Response
    {
        $id = (int) $id;
        $filters = [
            'q' => trim((string) ($request->query['q'] ?? '')),
        ];
        $records = $this->centros->search($filters['q']);

        $editing = $this->centros->find($id);
        if ($editing === null) {
            return Response::redirect(url('/produccion/centros'));
        }

        return $this->render('pages/admin/centros/index', [
            'title' => 'Editar centro de trabajo',
            'centros' => $records,
            'filters' => $filters,
            'editing' => $editing,
            'errors' => [],
            'old' => $editing,
        ]);
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->validateData($request);

        if ($errors !== []) {
            $records = $this->centros->search('');
            return $this->render('pages/admin/centros/index', [
                'title' => 'Centros de trabajo',
                'centros' => $records,
                'filters' => ['q' => ''],
                'editing' => null,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        try {
            $this->centros->create($data);
        } catch (RuntimeException $exception) {
            $records = $this->centros->search('');
            return $this->render('pages/admin/centros/index', [
                'title' => 'Centros de trabajo',
                'centros' => $records,
                'filters' => ['q' => ''],
                'editing' => null,
                'errors' => ['general' => $exception->getMessage()],
                'old' => $request->body,
            ]);
        }

        return Response::redirect(url('/produccion/centros'));
    }

    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        $record = $this->centros->find($id);
        if ($record === null) {
            return Response::redirect(url('/produccion/centros'));
        }

        [$data, $errors] = $this->validateData($request);
        if ($errors !== []) {
            $filters = ['q' => trim((string) ($request->query['q'] ?? ''))];
            $records = $this->centros->search($filters['q']);
            return $this->render('pages/admin/centros/index', [
                'title' => 'Editar centro de trabajo',
                'centros' => $records,
                'filters' => $filters,
                'editing' => $record,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->centros->update($id, $data);
        return Response::redirect(url('/produccion/centros'));
    }
    public function destroy(Request $request, $id): Response
    {
        $this->centros->delete((int) $id);
        return Response::redirect(url('/produccion/centros'));
    }

    private function validateData(Request $request): array
    {
        $body = $request->body;
        $data = [
            'codigo' => strtoupper(trim((string) ($body['codigo'] ?? ''))),
            'nombre' => trim((string) ($body['nombre'] ?? '')),
            'descripcion' => trim((string) ($body['descripcion'] ?? '')),
            'tipo' => trim((string) ($body['tipo'] ?? 'manual')),
            'capacidad_horas_dia' => (float) ($body['capacidad_horas_dia'] ?? 8),
            'eficiencia_porcentaje' => (float) ($body['eficiencia_porcentaje'] ?? 100),
            'costo_hora' => (float) ($body['costo_hora'] ?? 0),
            'activo' => isset($body['activo']) ? 1 : 0,
        ];

        $errors = [];
        if ($data['codigo'] === '') {
            $errors['codigo'] = 'Ingresa un código.';
        }
        if ($data['nombre'] === '') {
            $errors['nombre'] = 'Ingresa un nombre.';
        }
        if (!in_array($data['tipo'], ['manual', 'semi_automatico', 'automatico'], true)) {
            $errors['tipo'] = 'Tipo inválido.';
        }

        return [$data, $errors];
    }
}
