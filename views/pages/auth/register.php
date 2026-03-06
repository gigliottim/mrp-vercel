<?php

use App\Core\Support\AssetHelper;
use App\Core\View\View;

$errors = $errors ?? [];
$old = $old ?? [];
$success = !empty($success);
$previewDatabaseName = (string) ($preview_database_name ?? '');
$provisionedDatabaseName = (string) ($provisioned_database_name ?? '');
$provisionedAdminEmail = (string) ($provisioned_admin_email ?? '');
$provisioningStatus = $provisioning_status ?? [
    'database_created' => false,
    'admin_user_created' => false,
    'company_binding_created' => false,
];
$databaseCreated = !empty($provisioningStatus['database_created']);
$adminUserCreated = !empty($provisioningStatus['admin_user_created']);
$companyBindingCreated = !empty($provisioningStatus['company_binding_created']);
?>

<section class="row justify-content-center py-4">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <span class="badge bg-primary-subtle text-primary-emphasis mb-3">Wizard</span>
                <h1 class="h3 mb-2">Alta autoservicio de empresa</h1>
                <p class="text-muted mb-4">Completa el wizard y se creara una base exclusiva para tu empresa con el esquema de MRP y catalogos iniciales.</p>

                <?php if ($success) : ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fa-solid fa-circle-check me-2"></i>
                        Empresa creada correctamente.
                    </div>

                    <div class="border rounded-3 p-3 bg-light">
                        <div class="small text-muted mb-1">Base creada</div>
                        <div class="fw-semibold"><?= View::escape($provisionedDatabaseName) ?></div>
                        <div class="small text-muted mt-2">Usuario administrador</div>
                        <div class="fw-semibold"><?= View::escape($provisionedAdminEmail) ?></div>
                    </div>

                    <div class="border rounded-3 p-3 mt-3">
                        <div class="small text-muted mb-2">Estado de alta</div>
                        <div class="d-flex flex-column gap-1">
                            <div>
                                <strong>Base de datos:</strong>
                                <span class="badge <?= $databaseCreated ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= $databaseCreated ? 'Creada' : 'No creada' ?>
                                </span>
                            </div>
                            <div>
                                <strong>Usuario administrador:</strong>
                                <span class="badge <?= $adminUserCreated ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= $adminUserCreated ? 'Creado' : 'No creado' ?>
                                </span>
                            </div>
                            <div>
                                <strong>Vinculacion en auth:</strong>
                                <span class="badge <?= $companyBindingCreated ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= $companyBindingCreated ? 'OK' : 'Faltante' ?>
                                </span>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">Ya puedes iniciar sesion con el email y la contrasena que acabas de registrar.</div>
                    </div>

                    <div class="mt-4 d-flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="<?= url('login') ?>">Ir a login</a>
                        <a class="btn btn-outline-secondary" href="<?= url('/') ?>">Volver al inicio</a>
                    </div>
                <?php else : ?>
                    <?php if (!empty($errors['general'] ?? null)) : ?>
                        <div class="alert alert-danger" role="alert">
                            <?= View::escape($errors['general']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="progress mb-4" role="progressbar" aria-label="Progreso wizard" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" data-wizard-progress style="width: 25%">Paso 1 de 3</div>
                    </div>

                    <form class="row g-3" action="<?= url('register') ?>" method="post" data-register-wizard novalidate>
                        <div class="col-12" data-wizard-step="1">
                            <h2 class="h5 mb-3">Paso 1. Datos de cuenta</h2>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="admin_name">Nombre</label>
                                    <input class="form-control<?= isset($errors['admin_name']) ? ' is-invalid' : '' ?>" name="admin_name" id="admin_name" type="text" value="<?= View::escape($old['admin_name'] ?? '') ?>" required>
                                    <?php if (isset($errors['admin_name'])) : ?>
                                        <div class="invalid-feedback"><?= View::escape($errors['admin_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="admin_lastname">Apellido</label>
                                    <input class="form-control<?= isset($errors['admin_lastname']) ? ' is-invalid' : '' ?>" name="admin_lastname" id="admin_lastname" type="text" value="<?= View::escape($old['admin_lastname'] ?? '') ?>" required>
                                    <?php if (isset($errors['admin_lastname'])) : ?>
                                        <div class="invalid-feedback"><?= View::escape($errors['admin_lastname']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="email">Email</label>
                                    <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" name="email" id="email" type="email" placeholder="admin@tuempresa.com" value="<?= View::escape($old['email'] ?? '') ?>" required autocomplete="username">
                                    <?php if (isset($errors['email'])) : ?>
                                        <div class="invalid-feedback"><?= View::escape($errors['email']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="password">Contrasena</label>
                                    <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" name="password" id="password" type="password" minlength="8" required autocomplete="new-password">
                                    <?php if (isset($errors['password'])) : ?>
                                        <div class="invalid-feedback"><?= View::escape($errors['password']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="password_confirmation">Confirmar contrasena</label>
                                    <input class="form-control<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" name="password_confirmation" id="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
                                    <?php if (isset($errors['password_confirmation'])) : ?>
                                        <div class="invalid-feedback"><?= View::escape($errors['password_confirmation']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-none" data-wizard-step="2">
                            <h2 class="h5 mb-3">Paso 2. Datos de empresa</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="company_name">Nombre de empresa</label>
                                    <input class="form-control<?= isset($errors['company_name']) ? ' is-invalid' : '' ?>" name="company_name" id="company_name" type="text" value="<?= View::escape($old['company_name'] ?? '') ?>" required>
                                    <?php if (isset($errors['company_name'])) : ?>
                                        <div class="invalid-feedback"><?= View::escape($errors['company_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="tax_id">CUIT/RUC</label>
                                    <input class="form-control<?= isset($errors['tax_id']) ? ' is-invalid' : '' ?>" name="tax_id" id="tax_id" type="text" value="<?= View::escape($old['tax_id'] ?? '') ?>">
                                    <?php if (isset($errors['tax_id'])) : ?>
                                        <div class="invalid-feedback"><?= View::escape($errors['tax_id']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="country">Pais/region (opcional)</label>
                                    <input class="form-control" name="country" id="country" type="text" value="<?= View::escape($old['country'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-none" data-wizard-step="3">
                            <h2 class="h5 mb-3">Paso 3. Confirmacion</h2>
                            <div class="border rounded-3 p-3 bg-light mb-3">
                                <div class="small text-muted">Base a crear</div>
                                <div class="fw-semibold" data-db-preview><?= View::escape($previewDatabaseName !== '' ? $previewDatabaseName : 'mrp_empresa') ?></div>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input<?= isset($errors['terms']) ? ' is-invalid' : '' ?>" type="checkbox" name="terms" id="terms" value="1" <?= !empty($old['terms']) ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="terms">
                                    Confirmo los terminos y autorizo la creacion de la base de datos para mi empresa.
                                </label>
                                <?php if (isset($errors['terms'])) : ?>
                                    <div class="invalid-feedback d-block"><?= View::escape($errors['terms']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2 mt-4">
                            <button class="btn btn-outline-secondary d-none" type="button" data-wizard-prev>Anterior</button>
                            <button class="btn btn-primary" type="button" data-wizard-next>Siguiente</button>
                            <button class="btn btn-success d-none" type="submit" data-wizard-submit>Crear empresa y finalizar</button>
                            <a class="btn btn-link" href="<?= url('login') ?>">Ya tengo cuenta</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if (!$success) : ?>
    <script src="<?= AssetHelper::js('modules/auth/register-wizard.js') ?>" defer></script>
<?php endif; ?>
