<?php

use App\Core\View\View;

$errors = $errors ?? [];
$old = $old ?? [];
?>
<section class="auth-shell">
    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="auth-panel card h-100">
                <div class="card-body p-4 p-lg-5 d-flex flex-column gap-4">
                    <div>
                        <span class="auth-panel__brand mb-3">
                            <i class="fa-solid fa-building-shield"></i>
                            Acceso MRP
                        </span>
                        <h1 class="auth-panel__headline mb-3">Ingreso centralizado por empresa</h1>
                        <p class="auth-panel__lede mb-0">El ingreso a una empresa se realiza usando tu correo y contraseña.</p>
                    </div>

                    <ul class="auth-rules">
                        <li>
                            <i class="fa-solid fa-envelope"></i>
                            Inicias sesión con <strong>correo y contraseña</strong>.
                        </li>
                        <li>
                            <i class="fa-solid fa-sitemap"></i>
                            Si el usuario está asignado a más de una empresa, se muestra un desplegable para elegir cuál abrir.
                        </li>
                        <li>
                            <i class="fa-solid fa-door-open"></i>
                            Para cambiar de empresa, es obligatorio cerrar sesión y volver a loguearse.
                        </li>
                    </ul>

                    <div class="auth-enterprise-note">
                        <strong>Importante:</strong> no hay cambio de empresa dentro de una sesión activa.
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="auth-card card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <?php if (!empty($errors['general'] ?? null)) : ?>
                        <div class="alert alert-danger" role="alert">
                            <?= View::escape($errors['general']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-2 mb-4">
                        <div>
                            <h2 class="h5 mb-1"><?= isset($companies) ? 'Selecciona la empresa a la que deseas acceder' : 'Inicia sesión con tus credenciales' ?></h2>
                            <p class="auth-step-hint mb-0"><?= isset($companies) ? 'Se detectaron varias empresas asociadas a tu usuario.' : 'Accede con mail y contraseña para ingresar a tu empresa.' ?></p>
                        </div>
                        <?php if (isset($companies)) : ?>
                            <div class="auth-user-pill">
                                <i class="fa-solid fa-user"></i>
                                <?= View::escape($user_name ?? '') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form class="d-flex flex-column gap-4" method="post" action="<?= url('login') ?>">
                        <?php if (isset($companies)) : ?>
                            <!-- Paso 2: seleccion de empresa cuando hay mas de una -->
                            <div>
                                <label class="form-label" for="tenant">Empresa disponible</label>
                                <select class="form-select form-select-lg<?= isset($errors['tenant']) ? ' is-invalid' : '' ?>" name="tenant" id="tenant" required autofocus>
                                    <?php foreach ($companies as $company) : ?>
                                        <option value="<?= View::escape($company['slug']) ?>">
                                            <?= View::escape($company['name']) ?> (<?= View::escape($company['slug']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['tenant'])) : ?>
                                    <div class="invalid-feedback"><?= View::escape($errors['tenant']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="auth-actions">
                                <button class="btn btn-primary btn-lg" type="submit">
                                    Ingresar a empresa seleccionada
                                </button>
                                <a href="<?= url('login') ?>" class="small text-muted">Volver y usar otro correo</a>
                            </div>

                        <?php else : ?>
                            <!-- Paso 1: credenciales -->
                            <div>
                                <label class="form-label" for="email">Mail</label>
                                <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" type="email" name="email" id="email" placeholder="tu@empresa.com" value="<?= View::escape($old['email'] ?? '') ?>" required autofocus autocomplete="username">
                                <?php if (isset($errors['email'])) : ?>
                                    <div class="invalid-feedback"><?= View::escape($errors['email']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="form-label" for="password">Contraseña</label>
                                <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" type="password" name="password" id="password" placeholder="••••••••" value="" required autocomplete="current-password">
                                <?php if (isset($errors['password'])) : ?>
                                    <div class="invalid-feedback"><?= View::escape($errors['password']) ?></div>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-primary btn-lg" type="submit">Ingresar</button>
                        <?php endif; ?>

                        <div class="auth-footer-note">
                            Si necesitas cambiar de empresa durante el día, primero cierra sesión y luego vuelve a ingresar.
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
