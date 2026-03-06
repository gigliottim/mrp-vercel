<?php

/**
 * Vista: Crear Centro de Trabajo
 */
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Nuevo Centro de Trabajo</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('produccion/centros-trabajo') ?>" id="centroForm">
                        <?php include __DIR__ . '/_centro_form.php'; ?>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= url('produccion/centros-trabajo') ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
