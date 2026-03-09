<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\Entidad;

final class EntidadesController extends Controller
{
    private Entidad $entidades;

    public function __construct(?Entidad $entidades = null)
    {
        $this->entidades = $entidades ?? new Entidad();
    }

    public function index(Request $request): Response
    {
        return $this->render('pages/admin/catalogo/entidades/index', [
            'title' => 'Clientes y proveedores',
            'entidades' => $this->entidades->all(500),
            'editing' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function edit(Request $request, $id): Response
    {
        $id = (int) $id;
        $editing = $this->entidades->find($id);
        if ($editing === null) {
            return Response::redirect(url('/configuracion/entidades'));
        }

        return $this->render('pages/admin/catalogo/entidades/index', [
            'title' => 'Editar entidad',
            'entidades' => $this->entidades->all(500),
            'editing' => $editing,
            'errors' => [],
            'old' => $editing,
        ]);
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->validateData($request, false);
        if ($errors !== []) {
            return $this->render('pages/admin/catalogo/entidades/index', [
                'title' => 'Clientes y proveedores',
                'entidades' => $this->entidades->all(500),
                'editing' => null,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->entidades->create($data);
        return Response::redirect(url('/configuracion/entidades'));
    }

    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        $existing = $this->entidades->find($id);
        if ($existing === null) {
            return Response::redirect(url('/configuracion/entidades'));
        }

        [$data, $errors] = $this->validateData($request, true);
        if ($errors !== []) {
            return $this->render('pages/admin/catalogo/entidades/index', [
                'title' => 'Editar entidad',
                'entidades' => $this->entidades->all(500),
                'editing' => $existing,
                'errors' => $errors,
                'old' => $request->body,
            ]);
        }

        $this->entidades->update($id, $data);
        return Response::redirect(url('/configuracion/entidades'));
    }

    public function destroy(Request $request, $id): Response
    {
        $id = (int) $id;

        try {
            $this->entidades->delete($id);
            return Response::redirect(url('/configuracion/entidades'));
        } catch (\Throwable $e) {
            return $this->render('pages/admin/catalogo/entidades/index', [
                'title' => 'Clientes y proveedores',
                'entidades' => $this->entidades->all(500),
                'editing' => null,
                'errors' => ['general' => 'No se pudo eliminar la entidad. Verifique que no tenga compras relacionadas.'],
                'old' => [],
            ]);
        }
    }

    private function validateData(Request $request, bool $isUpdate): array
    {
        $body = $request->body;

        $data = [
            'razon_social' => trim((string) ($body['razon_social'] ?? '')),
            'tipo' => strtoupper(trim((string) ($body['tipo'] ?? 'PROVEEDOR'))),
            'identificacion_tributaria' => trim((string) ($body['identificacion_tributaria'] ?? '')),
            'contacto_email' => trim((string) ($body['contacto_email'] ?? '')),
            'contacto_telefono' => trim((string) ($body['contacto_telefono'] ?? '')),
            'direccion' => trim((string) ($body['direccion'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!$isUpdate) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        $errors = [];

        if ($data['razon_social'] === '') {
            $errors['razon_social'] = 'Razon social requerida.';
        }

        if (!in_array($data['tipo'], ['PROVEEDOR', 'CLIENTE', 'AMBOS'], true)) {
            $errors['tipo'] = 'Tipo invalido.';
        }

        if ($data['contacto_email'] !== '' && filter_var($data['contacto_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['contacto_email'] = 'Email invalido.';
        }

        return [$data, $errors];
    }
}
