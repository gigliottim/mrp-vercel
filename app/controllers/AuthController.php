<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth\AuthManager;
use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\AuthService;
use App\Services\TenantProvisioningService;
use RuntimeException;

use App\Core\Support\SessionManager;

final class AuthController extends Controller
{
    private AuthService $authService;
    private TenantProvisioningService $tenantProvisioningService;

    public function __construct(?AuthService $authService = null, ?TenantProvisioningService $tenantProvisioningService = null)
    {
        $this->authService = $authService ?? new AuthService();
        $this->tenantProvisioningService = $tenantProvisioningService ?? new TenantProvisioningService();
    }

    public function showLogin(Request $request): Response
    {
        if (AuthManager::check()) {
            return Response::redirect(url('dashboard'));
        }

        // Clear any pending login
        SessionManager::start();
        unset($_SESSION['pending_login_user']);

        $data = [
            'title' => 'Ingreso a empresa',
        ];

        return $this->render('pages/auth/login', $data, 'layouts/auth');
    }

    public function showRegister(Request $request): Response
    {
        if (AuthManager::check()) {
            return Response::redirect(url('dashboard'));
        }

        return $this->render('pages/auth/register', [
            'title' => 'Registrarse en MRP',
            'errors' => [],
            'old' => [],
            'success' => false,
            'preview_database_name' => '',
            'provisioned_database_name' => '',
        ], 'layouts/public');
    }

    public function register(Request $request): Response
    {
        if (AuthManager::check()) {
            return Response::redirect(url('dashboard'));
        }

        $input = [
            'admin_name' => trim((string) $request->input('admin_name', '')),
            'admin_lastname' => trim((string) $request->input('admin_lastname', '')),
            'email' => trim((string) $request->input('email', '')),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'company_name' => trim((string) $request->input('company_name', '')),
            'tax_id' => trim((string) $request->input('tax_id', '')),
            'country' => trim((string) $request->input('country', '')),
            'terms' => (string) $request->input('terms', ''),
        ];

        $previewDatabaseName = $this->tenantProvisioningService->previewDatabaseName($input['company_name']);

        try {
            $result = $this->tenantProvisioningService->provision($input);
        } catch (RuntimeException $exception) {
            return $this->render('pages/auth/register', [
                'title' => 'Registrarse en MRP',
                'errors' => ['general' => $exception->getMessage()],
                'old' => $this->withoutPassword($input),
                'success' => false,
                'preview_database_name' => $previewDatabaseName,
                'provisioned_database_name' => '',
            ], 'layouts/public');
        }

        if ($result['success'] === false) {
            return $this->render('pages/auth/register', [
                'title' => 'Registrarse en MRP',
                'errors' => $result['errors'],
                'old' => $this->withoutPassword($input),
                'success' => false,
                'preview_database_name' => $previewDatabaseName,
                'provisioned_database_name' => '',
                'provisioned_admin_email' => '',
                'provisioning_status' => [
                    'database_created' => false,
                    'admin_user_created' => false,
                    'company_binding_created' => false,
                ],
            ], 'layouts/public');
        }

        $status = $result['provisioning_status'] ?? [
            'database_created' => false,
            'admin_user_created' => false,
            'company_binding_created' => false,
        ];

        $isProvisioningComplete =
            !empty($status['database_created'])
            && !empty($status['admin_user_created'])
            && !empty($status['company_binding_created']);

        if (!$isProvisioningComplete) {
            return $this->render('pages/auth/register', [
                'title' => 'Registrarse en MRP',
                'errors' => [
                    'general' => 'El wizard finalizo, pero no se pudo confirmar el alta completa (usuario, base o vinculacion). Revisa el log y vuelve a intentar.',
                ],
                'old' => $this->withoutPassword($input),
                'success' => false,
                'preview_database_name' => (string) ($result['database_name'] ?? $previewDatabaseName),
                'provisioned_database_name' => '',
                'provisioned_admin_email' => '',
                'provisioning_status' => $status,
            ], 'layouts/public');
        }

        return $this->render('pages/auth/register', [
            'title' => 'Registrarse en MRP',
            'errors' => [],
            'old' => [],
            'success' => true,
            'preview_database_name' => '',
            'provisioned_database_name' => (string) ($result['database_name'] ?? ''),
            'provisioned_admin_email' => (string) ($result['admin_email'] ?? ''),
            'provisioning_status' => $status,
        ], 'layouts/public');
    }

    public function authenticate(Request $request): Response
    {
        SessionManager::start();

        // 1. STEP: Tenant Selection (Finalize)
        if ($request->input('tenant') && isset($_SESSION['pending_login_user'])) {
            $user = $_SESSION['pending_login_user'];
            $tenantSlug = trim((string) $request->input('tenant'));

            try {
                $result = $this->authService->loginWithTenant($user, $tenantSlug);
                AuthManager::login($result);
                unset($_SESSION['pending_login_user']);
                return Response::redirect(url('dashboard'));
            } catch (RuntimeException $exception) {
                // Return to selection
                $companies = $this->authService->validateUser($user['email'], 'ignore_revalidate')['companies'] ?? [];
                return $this->render('pages/auth/login', [
                    'title' => 'Seleccion de empresa',
                    'errors' => ['tenant' => $exception->getMessage()],
                    'companies' => $companies,
                    'user_name' => $user['name']
                ], 'layouts/auth');
            }
        }

        // 2. STEP: Credentials Validation
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            return $this->render('pages/auth/login', [
                'title' => 'Ingreso a empresa',
                'errors' => ['general' => 'Credenciales incompletas.'],
                'old' => ['email' => $email]
            ], 'layouts/auth');
        }

        try {
            $result = $this->authService->validateUser($email, $password);

            // If user has only 1 company, auto-login
            if (count($result['companies']) === 1) {
                $tenantSlug = $result['companies'][0]['slug'];
                $finalResult = $this->authService->loginWithTenant($result['user'], $tenantSlug);
                AuthManager::login($finalResult);
                return Response::redirect(url('dashboard'));
            }

            // Store user for next step
            $_SESSION['pending_login_user'] = $result['user'];
            $_SESSION['pending_login_companies'] = $result['companies']; // Cache companies

            return $this->render('pages/auth/login', [
                'title' => 'Seleccion de empresa',
                'companies' => $result['companies'],
                'user_name' => $result['user']['name']
            ], 'layouts/auth');
        } catch (RuntimeException $exception) {
            return $this->render('pages/auth/login', [
                'title' => 'Ingreso a empresa',
                'errors' => ['general' => $exception->getMessage()],
                'old' => ['email' => $email]
            ], 'layouts/auth');
        }
    }

    public function logout(Request $request): Response
    {
        AuthManager::logout();
        return Response::redirect(url('login'));
    }

    /**
     * @param array{tenant: string, email: string, password: string} $input
     * @return array<string, string>
     */
    private function validateInput(array $input): array
    {
        $errors = [];

        if ($input['tenant'] === '') {
            $errors['tenant'] = 'Indica el alias o slug del tenant.';
        }

        if ($input['email'] === '') {
            $errors['email'] = 'El correo es obligatorio.';
        }

        if ($input['password'] === '') {
            $errors['password'] = 'La contraseña es obligatoria.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function withoutPassword(array $input): array
    {
        unset($input['password']);
        return $input;
    }
}
