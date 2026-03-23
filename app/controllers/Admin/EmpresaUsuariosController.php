<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\EmpresaUsuariosAclService;
use App\Services\EmpresaUsuariosService;
use RuntimeException;

final class EmpresaUsuariosController extends Controller
{
    private EmpresaUsuariosService $service;
    private EmpresaUsuariosAclService $aclService;

    public function __construct(?EmpresaUsuariosService $service = null, ?EmpresaUsuariosAclService $aclService = null)
    {
        $this->service = $service ?? new EmpresaUsuariosService();
        $this->aclService = $aclService ?? new EmpresaUsuariosAclService();
    }

    public function empresa(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $company = $this->service->findCompany($this->service->currentCompanyId());
        if ($company === null) {
            return Response::redirect(url('/dashboard'));
        }

        return $this->renderEmpresa($company, [], $company ?? []);
    }

    public function empresaEdit(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $editing = $this->service->findCompany((int) $id);
        if ($editing === null) {
            return Response::redirect(url('/empresa-usuarios/empresa'));
        }

        return $this->renderEmpresa($editing, [], $editing);
    }

    public function empresaStore(Request $request): Response
    {
        return Response::redirect(url('/empresa-usuarios/empresa'));
    }

    public function empresaUpdate(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $companyId = (int) $id;
        $editing = $this->service->findCompany($companyId);
        if ($editing === null) {
            return Response::redirect(url('/empresa-usuarios/empresa'));
        }

        [$data, $errors] = $this->validateEmpresaData($request);
        if ($errors !== []) {
            return $this->renderEmpresa($editing, $errors, $request->body);
        }

        try {
            $this->service->updateCompany($companyId, $data);
            return Response::redirect(url('/empresa-usuarios/empresa'));
        } catch (RuntimeException $exception) {
            return $this->renderEmpresa($editing, ['general' => $exception->getMessage()], $request->body);
        }
    }

    public function empresaDestroy(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserSuperAdmin()) {
            return Response::redirect(url('/empresa-usuarios/empresa'));
        }
        try {
            $this->service->deleteCompany((int) $id);
            return Response::redirect(url('/empresa-usuarios/empresa'));
        } catch (\RuntimeException $exception) {
            return $this->renderEmpresa(null, ['general' => $exception->getMessage()], []);
        }
    }

    public function usuarios(Request $request): Response
    {
        if ($this->service->isCurrentUserCompanyAdmin()) {
            return $this->renderUsuarios(null, [], []);
        }

        $companyId = $this->service->currentCompanyId();
        $currentUserId = $this->service->currentUserId();
        $editing = $this->service->findUserForCompany($companyId, $currentUserId);

        if ($editing === null) {
            return Response::redirect(url('/dashboard'));
        }

        return $this->renderUsuarios($editing, [], $editing);
    }

    public function usuariosEdit(Request $request, $id): Response
    {
        $companyId = $this->service->currentCompanyId();
        $userId = (int) $id;
        $isAdmin = $this->service->isCurrentUserCompanyAdmin();
        if (!$isAdmin && $userId !== $this->service->currentUserId()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $editing = $this->service->findUserForCompany($companyId, $userId);
        if ($editing === null) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        return $this->renderUsuarios($editing, [], $editing);
    }

    public function usuariosStore(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        [$data, $errors] = $this->validateUsuarioData($request, false);
        if ($errors !== []) {
            return $this->renderUsuarios(null, $errors, $request->body);
        }

        try {
            $this->service->createUserForCompany($this->service->currentCompanyId(), $data);
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        } catch (RuntimeException $exception) {
            return $this->renderUsuarios(null, ['general' => $exception->getMessage()], $request->body);
        }
    }

    public function usuariosUpdate(Request $request, $id): Response
    {
        $companyId = $this->service->currentCompanyId();
        $userId = (int) $id;
        $isAdmin = $this->service->isCurrentUserCompanyAdmin();

        if (!$isAdmin) {
            if ($userId !== $this->service->currentUserId()) {
                return Response::redirect(url('/empresa-usuarios/usuarios'));
            }

            [$data, $errors] = $this->validateSelfPasswordData($request);
            $editingSelf = $this->service->findUserForCompany($companyId, $userId);
            if ($errors !== []) {
                return $this->renderUsuarios($editingSelf, $errors, $request->body);
            }

            try {
                $this->service->updateOwnPasswordForCompany($companyId, $userId, $data['password']);
                return Response::redirect(url('/empresa-usuarios/usuarios'));
            } catch (RuntimeException $exception) {
                return $this->renderUsuarios($editingSelf, ['general' => $exception->getMessage()], $request->body);
            }
        }

        $editing = $this->service->findUserForCompany($companyId, $userId);
        if ($editing === null) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        [$data, $errors] = $this->validateUsuarioData($request, true);
        if ($errors !== []) {
            return $this->renderUsuarios($editing, $errors, $request->body);
        }

        try {
            $this->service->updateUserForCompany($companyId, $userId, $data);
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        } catch (RuntimeException $exception) {
            return $this->renderUsuarios($editing, ['general' => $exception->getMessage()], $request->body);
        }
    }

    public function usuariosDestroy(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        try {
            $this->service->deleteUserForCompany($this->service->currentCompanyId(), (int) $id);
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        } catch (RuntimeException $exception) {
            return $this->renderUsuarios(null, ['general' => $exception->getMessage()], []);
        }
    }

    public function roles(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        return $this->renderRoles(null, [], []);
    }

    public function rolesEdit(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $editing = $this->service->findRole($this->service->currentCompanyId(), (int) $id);
        if ($editing === null) {
            return Response::redirect(url('/empresa-usuarios/roles'));
        }

        return $this->renderRoles($editing, [], $editing);
    }

    public function rolesStore(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        [$data, $errors] = $this->validateRoleData($request);
        if ($errors !== []) {
            return $this->renderRoles(null, $errors, $request->body);
        }

        try {
            $this->service->createRole($this->service->currentCompanyId(), $data);
            return Response::redirect(url('/empresa-usuarios/roles'));
        } catch (RuntimeException $exception) {
            return $this->renderRoles(null, ['general' => $exception->getMessage()], $request->body);
        }
    }

    public function rolesUpdate(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $companyId = $this->service->currentCompanyId();
        $roleId = (int) $id;
        $editing = $this->service->findRole($companyId, $roleId);
        if ($editing === null) {
            return Response::redirect(url('/empresa-usuarios/roles'));
        }

        [$data, $errors] = $this->validateRoleData($request);
        if ($errors !== []) {
            return $this->renderRoles($editing, $errors, $request->body);
        }

        try {
            $this->service->updateRole($companyId, $roleId, $data);
            return Response::redirect(url('/empresa-usuarios/roles'));
        } catch (RuntimeException $exception) {
            return $this->renderRoles($editing, ['general' => $exception->getMessage()], $request->body);
        }
    }

    public function rolesDestroy(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        try {
            $this->service->deleteRole($this->service->currentCompanyId(), (int) $id);
            return Response::redirect(url('/empresa-usuarios/roles'));
        } catch (RuntimeException $exception) {
            return $this->renderRoles(null, ['general' => $exception->getMessage()], []);
        }
    }

    public function permisos(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        return $this->renderPermisos(null, [], []);
    }

    public function permisosEdit(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $companyId = $this->service->currentCompanyId();
        $editing = $this->aclService->findAclByIdForCompany($companyId, (int) $id);
        if ($editing === null) {
            return Response::redirect(url('/empresa-usuarios/permisos'));
        }

        return $this->renderPermisos($editing, [], $editing);
    }

    public function permisosStore(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        [$data, $errors] = $this->validateAclData($request);
        if ($errors !== []) {
            return $this->renderPermisos(null, $errors, $request->body);
        }

        try {
            $this->aclService->upsertAclForCompany($this->service->currentCompanyId(), $data);
            return Response::redirect(url('/empresa-usuarios/permisos'));
        } catch (RuntimeException $exception) {
            return $this->renderPermisos(null, ['general' => $exception->getMessage()], $request->body);
        }
    }

    public function permisosUpdate(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $companyId = $this->service->currentCompanyId();
        $aclId = (int) $id;
        $editing = $this->aclService->findAclByIdForCompany($companyId, $aclId);
        if ($editing === null) {
            return Response::redirect(url('/empresa-usuarios/permisos'));
        }

        [$data, $errors] = $this->validateAclData($request);
        if ($errors !== []) {
            return $this->renderPermisos($editing, $errors, $request->body);
        }

        try {
            $this->aclService->updateAclForCompany($companyId, $aclId, $data);
            return Response::redirect(url('/empresa-usuarios/permisos'));
        } catch (RuntimeException $exception) {
            return $this->renderPermisos($editing, ['general' => $exception->getMessage()], $request->body);
        }
    }

    public function permisosDestroy(Request $request, $id): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/empresa-usuarios/usuarios'));
        }

        $this->aclService->deleteAclForCompany($this->service->currentCompanyId(), (int) $id);
        return Response::redirect(url('/empresa-usuarios/permisos'));
    }

    public function permisosStoreBulk(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return $this->json(['ok' => false, 'error' => 'Sin permiso'], 403);
        }

        $body       = $request->body;
        $menuItemId = (int) ($body['menu_item_id'] ?? 0);
        $perms      = isset($body['perms']) && is_array($body['perms']) ? $body['perms'] : [];

        if ($menuItemId <= 0) {
            return $this->json(['ok' => false, 'error' => 'Debes seleccionar un ítem de menú válido.'], 422);
        }

        try {
            $this->aclService->upsertBulkAclForMenuNode(
                $this->service->currentCompanyId(),
                $menuItemId,
                $perms
            );
            return $this->json(['ok' => true]);
        } catch (RuntimeException $exception) {
            return $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
        }
    }

    public function permisosTree(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return $this->json(['data' => []]);
        }

        return $this->json([
            'data' => $this->aclService->listMenuTree(),
        ]);
    }

    public function permisosAcl(Request $request): Response
    {
        if (!$this->service->isCurrentUserCompanyAdmin()) {
            return $this->json(['data' => []]);
        }

        return $this->json([
            'data' => $this->aclService->listAclRowsByCompany($this->service->currentCompanyId()),
        ]);
    }

    private function renderEmpresa(?array $editing, array $errors, array $old): Response
    {
        return $this->render('pages/admin/empresa-usuarios/empresa', [
            'empresas' => $this->service->listCompanies(),
            'editing' => $editing,
            'old' => $old,
            'errors' => $errors,
            'currentCompanyId' => $this->service->currentCompanyId(),
            'isSuperAdmin' => $this->service->isCurrentUserSuperAdmin(),
        ]);
    }

    private function renderUsuarios(?array $editing, array $errors, array $old): Response
    {
        $companyId = $this->service->currentCompanyId();
        $isAdmin = $this->service->isCurrentUserCompanyAdmin();
        $currentUserId = $this->service->currentUserId();
        $usuarios = $isAdmin
            ? $this->service->listUsersByCompany($companyId)
            : array_values(array_filter(
                $this->service->listUsersByCompany($companyId),
                static fn(array $u): bool => (int) ($u['id'] ?? 0) === $currentUserId
            ));

        return $this->render('pages/admin/empresa-usuarios/usuarios', [
            'usuarios' => $usuarios,
            'roles' => $this->service->listRoles($companyId),
            'editing' => $editing,
            'old' => $old,
            'errors' => $errors,
            'isAdminCompany' => $isAdmin,
            'currentUserId' => $currentUserId,
        ]);
    }

    private function renderRoles(?array $editing, array $errors, array $old): Response
    {
        $companyId = $this->service->currentCompanyId();
        return $this->render('pages/admin/empresa-usuarios/roles', [
            'roles' => $this->service->listRoles($companyId),
            'editing' => $editing,
            'old' => $old,
            'errors' => $errors,
        ]);
    }

    private function renderPermisos(?array $editing, array $errors, array $old): Response
    {
        $companyId = $this->service->currentCompanyId();
        $hideSuperAdmin = !$this->service->isCurrentUserSuperAdmin();
        return $this->render('pages/admin/empresa-usuarios/permisos', [
            'aclRows' => $this->aclService->listAclRowsByCompany($companyId),
            'menuTree' => $this->aclService->listMenuTree(),
            'subjects' => $this->aclService->listSubjectsForCompany($companyId, $hideSuperAdmin),
            'editing' => $editing,
            'old' => $old,
            'errors' => $errors,
        ]);
    }

    private function validateEmpresaData(Request $request): array
    {
        $body = $request->body;
        $data = [
            'nombre' => trim((string) ($body['nombre'] ?? '')),
            'slug' => trim((string) ($body['slug'] ?? '')),
            'cuit' => trim((string) ($body['cuit'] ?? '')),
            'email' => mb_strtolower(trim((string) ($body['email'] ?? ''))),
            'activo' => isset($body['activo']) ? 1 : 0,
        ];

        $errors = [];
        if ($data['nombre'] === '') {
            $errors['nombre'] = 'El nombre es obligatorio.';
        }
        if ($data['slug'] === '') {
            $errors['slug'] = 'El slug es obligatorio.';
        }
        if ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email invalido.';
        }

        return [$data, $errors];
    }

    private function validateUsuarioData(Request $request, bool $isUpdate): array
    {
        $companyId = $this->service->currentCompanyId();
        $body = $request->body;
        $data = [
            'nombre' => trim((string) ($body['nombre'] ?? '')),
            'email' => mb_strtolower(trim((string) ($body['email'] ?? ''))),
            'password' => (string) ($body['password'] ?? ''),
            'role_id' => (int) ($body['role_id'] ?? 0),
        ];

        $errors = [];
        if ($data['nombre'] === '') {
            $errors['nombre'] = 'El nombre es obligatorio.';
        }
        if ($data['email'] === '' || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email invalido.';
        }
        if (!$isUpdate && $data['password'] === '') {
            $errors['password'] = 'La password es obligatoria.';
        }
        if ($data['role_id'] <= 0) {
            $errors['role_id'] = 'Debes seleccionar un rol valido.';
        } elseif (!$this->service->roleBelongsToCompany($companyId, $data['role_id'])) {
            $errors['role_id'] = 'El rol seleccionado no pertenece a tu empresa.';
        }

        return [$data, $errors];
    }

    private function validateSelfPasswordData(Request $request): array
    {
        $body = $request->body;
        $password = (string) ($body['password'] ?? '');
        $passwordConfirmation = (string) ($body['password_confirmation'] ?? '');

        $errors = [];
        if ($password === '') {
            $errors['password'] = 'La password es obligatoria.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Las passwords no coinciden.';
        }

        return [
            ['password' => $password],
            $errors,
        ];
    }

    private function validateRoleData(Request $request): array
    {
        $body = $request->body;
        $data = [
            'nombre' => trim((string) ($body['nombre'] ?? '')),
            'codigo' => trim((string) ($body['codigo'] ?? 'web')),
        ];

        $errors = [];
        if ($data['nombre'] === '') {
            $errors['nombre'] = 'El nombre es obligatorio.';
        }
        if ($data['codigo'] === '') {
            $errors['codigo'] = 'El codigo es obligatorio.';
        }

        return [$data, $errors];
    }

    private function validateAclData(Request $request): array
    {
        $body = $request->body;
        $data = [
            'menu_item_id' => (int) ($body['menu_item_id'] ?? 0),
            'subject_type' => trim((string) ($body['subject_type'] ?? '')),
            'subject_id' => (int) ($body['subject_id'] ?? 0),
            'scope' => trim((string) ($body['scope'] ?? '')),
            'permission_level' => trim((string) ($body['permission_level'] ?? 'read')),
        ];

        $errors = [];
        if ($data['menu_item_id'] <= 0) {
            $errors['menu_item_id'] = 'Debes seleccionar un item de menu valido.';
        }
        if (!in_array($data['subject_type'], ['role', 'user'], true)) {
            $errors['subject_type'] = 'Sujeto invalido.';
        }
        if ($data['subject_id'] <= 0) {
            $errors['subject_id'] = 'ID de sujeto invalido.';
        }
        if (!in_array($data['scope'], ['item', 'branch'], true)) {
            $errors['scope'] = 'Scope invalido.';
        }
        if (!in_array($data['permission_level'], ['read', 'write', 'deny'], true)) {
            $errors['permission_level'] = 'Permiso invalido.';
        }

        return [$data, $errors];
    }
}
