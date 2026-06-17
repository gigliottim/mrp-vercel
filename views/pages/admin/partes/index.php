<?php

use App\Core\View\View;
use App\Core\Support\AssetHelper;

$formError = $_SESSION['form_error'] ?? null;
$formSuccess = $_SESSION['form_success'] ?? null;
$needsParteDeletion = $_SESSION['needs_parte_deletion'] ?? false;
$deleteVarianteId = $_SESSION['delete_variante_id'] ?? null;
$deleteParteId = $_SESSION['delete_parte_id'] ?? null;
unset($_SESSION['form_error'], $_SESSION['form_success'], $_SESSION['needs_parte_deletion'], $_SESSION['delete_variante_id'], $_SESSION['delete_parte_id']);

$parts = $parts ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15];
$search = $search ?? '';
$currentPerPage = (int) ($parts['per_page'] ?? 15);
$perPage = $currentPerPage;

$partItems = $parts['items'] ?? [];
$totalParts = $parts['total'] ?? count($partItems);

$currentPage = max(1, (int) ($parts['page'] ?? 1));
$showAll = $currentPerPage <= 0;
$totalPages = $showAll ? 1 : max(1, (int) ceil($totalParts / max(1, $currentPerPage)));
$currentPage = min($currentPage, $totalPages);

$prevPage = max(1, $currentPage - 1);
$nextPage = min($totalPages, $currentPage + 1);

$clearUrl = url('productos/partes?' . ($currentPerPage > 0 ? 'per_page=' . $currentPerPage : 'per_page=all'));

$buildUrl = static function (array $params) use ($search): string {
    $query = $params;
    if ($search !== '') {
        $query['q'] = $search;
    }
    return url('productos/partes?' . http_build_query($query));
};
?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Productos</p>
            <h1 class="h3 mb-0">Partes y variantes</h1>
        </div>
        <ul class="nav nav-pills d-flex gap-2">
            <li class="nav-item">
                <a class="btn btn-primary" href="<?= url('productos/partes/manager') ?>">
                    <i class="fa-solid fa-plus me-1"></i> Nueva Parte / Variante
                </a>
            </li>
            <li class="nav-item">
                <a class="btn btn-outline-secondary" href="<?= url('productos/partes/importar') ?>">
                    <i class="fa-solid fa-file-import me-1"></i> Importar
                </a>
            </li>
        </ul>
    </div>
</section>

<?php if ($formError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        <?= View::escape($formError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($formSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>
        <?= View::escape($formSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Barra de Búsqueda -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="get" action="<?= url('productos/partes') ?>" id="search-form-partes" data-clear-url="<?= $clearUrl ?>" class="row g-2 align-items-center">
            <div class="col-sm-8 col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="fa-solid fa-search"></i>
                    </span>
                    <input type="text"
                        class="form-control border-start-0 ps-0"
                        id="search-input-partes"
                        name="q"
                        value="<?= View::escape($search) ?>"
                        data-has-search="<?= $search !== '' ? 'true' : 'false' ?>"
                        placeholder="Buscar por código o detalle de parte / variante..."
                        autocomplete="off" />
                </div>
            </div>
            <div class="col-sm-4 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Buscar</button>
                <?php if ($search !== ''): ?>
                    <button type="button" id="clear-search-partes" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($currentPerPage > 0): ?>
                <input type="hidden" name="per_page" value="<?= $currentPerPage ?>">
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Listado Acordeón -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <?php if (empty($partItems)): ?>
            <div class="p-5 text-center text-muted">
                <i class="fa-solid fa-inbox fs-1 mb-3 opacity-50"></i>
                <p class="mb-0">No se encontraron partes<?= $search !== '' ? ' para su búsqueda' : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="accordion accordion-flush" id="accordionPartesList">
                <?php foreach ($partItems as $index => $parte): ?>
                    <?php
                    $variantes = $parte['variantes'] ?? [];
                    $collapseId = 'collapse_parte_' . $parte['id'];
                    $headingId = 'heading_parte_' . $parte['id'];
                    ?>
                    <div class="accordion-item border-bottom">
                        <!-- HEADER: Datos de la Parte -->
                        <h2 class="accordion-header" id="<?= $headingId ?>">
                            <div class="d-flex align-items-center w-100 px-3 py-2 custom-accordion-hover">
                                <button class="accordion-button collapsed flex-grow-1 p-2 bg-transparent shadow-none"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#<?= $collapseId ?>"
                                    aria-expanded="false"
                                    aria-controls="<?= $collapseId ?>">
                                    <div class="d-flex flex-wrap w-100 justify-content-between align-items-center me-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="fw-bold fs-5 text-dark"><?= View::escape($parte['codigo']) ?></span>
                                            <span class="text-secondary border-start ps-3 fs-6"><?= View::escape($parte['detalle']) ?></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if ($parte['grupo_nombre']): ?>
                                                <span class="badge bg-light text-dark border"><i class="fa-solid fa-layer-group text-muted me-1"></i> <?= View::escape($parte['grupo_nombre']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($parte['tipo_nombre']): ?>
                                                <span class="badge bg-info text-dark bg-opacity-10 border border-info"><i class="fa-solid fa-tag text-info me-1"></i> <?= View::escape($parte['tipo_nombre']) ?></span>
                                            <?php endif; ?>
                                            <span class="badge bg-secondary rounded-pill"><?= count($variantes) ?> variante<?= count($variantes) !== 1 ? 's' : '' ?></span>
                                        </div>
                                    </div>
                                </button>
                                <!-- Acciones de la PARTE -->
                                <div class="ms-2">
                                    <a href="<?= url('productos/partes/manager/' . $parte['id']) ?>"
                                        class="btn btn-sm btn-outline-primary"
                                        title="Editar / Gestionar Parte">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                </div>
                            </div>
                        </h2>

                        <!-- BODY: Tabla de Variante(s) -->
                        <div id="<?= $collapseId ?>"
                            class="accordion-collapse collapse bg-light"
                            aria-labelledby="<?= $headingId ?>"
                            data-bs-parent="#accordionPartesList">
                            <div class="accordion-body p-3">
                                <?php if (empty($variantes)): ?>
                                    <div class="alert alert-warning mb-0 py-2">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Esta parte no tiene variantes definidas.
                                        <a href="<?= url('productos/partes/manager/' . $parte['id']) ?>" class="alert-link ms-2">Crear variante</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive bg-white rounded border">
                                        <table class="table table-hover table-sm align-middle mb-0">
                                            <thead class="table-light text-secondary">
                                                <tr>
                                                    <th class="ps-3 py-2">Código Variante</th>
                                                    <th>Detalle</th>
                                                    <th>Estado</th>
                                                    <th class="text-end pe-3">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($variantes as $variante) : ?>
                                                    <?php
                                                    $estadoColors = [
                                                        'activa' => 'success',
                                                        'desarrollo' => 'info',
                                                        'obsoleta' => 'warning',
                                                        'descontinuada' => 'danger'
                                                    ];
                                                    $color = $estadoColors[$variante['estado']] ?? 'secondary';
                                                    ?>
                                                    <tr>
                                                        <td class="ps-3 py-2 fw-medium"><?= View::escape($variante['codigo_variante']) ?></td>
                                                        <td><?= View::escape($variante['detalle']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?>">
                                                                <?= ucfirst($variante['estado']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-3">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <a href="<?= url("productos/partes/manager/{$parte['id']}/variantes/{$variante['id']}") ?>"
                                                                    class="btn btn-outline-primary"
                                                                    title="Editar variante">
                                                                    <i class="fa-solid fa-pencil"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-outline-danger" title="Eliminar variante"
                                                                    onclick="confirmDeleteVariante(<?= $parte['id'] ?>, <?= $variante['id'] ?>, '<?= View::escape($variante['codigo_variante']) ?>')">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                                <a href="<?= url('reportes/destino-partes?id_variante=' . $variante['id']) ?>"
                                                                    class="btn btn-outline-secondary"
                                                                    title="Destino de partes"
                                                                    target="_blank">
                                                                    <i class="fa-solid fa-layer-group"></i>
                                                                </a>
                                                                <a href="<?= url('productos/maestro?id_variante=' . $variante['id']) ?>"
                                                                    class="btn btn-outline-primary"
                                                                    title="Cargar como Maestro">
                                                                    <i class="fa-solid fa-network-wired"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if (!$showAll && $totalPages > 1): ?>
        <div class="card-footer bg-white pt-3 pb-2 border-top">
            <nav aria-label="Navegación de partes">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl(['page' => $prevPage, 'per_page' => $perPage]) ?>">
                            <i class="fa-solid fa-chevron-left text-xs"></i> Prev
                        </a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p === 1 || $p === $totalPages || abs($currentPage - $p) <= 2): ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $buildUrl(['page' => $p, 'per_page' => $perPage]) ?>"><?= $p ?></a>
                            </li>
                        <?php elseif (abs($currentPage - $p) === 3): ?>
                            <li class="page-item disabled"><span class="page-link px-2">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl(['page' => $nextPage, 'per_page' => $perPage]) ?>">
                            Next <i class="fa-solid fa-chevron-right text-xs"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<style>
    .custom-accordion-hover:hover {
        background-color: var(--bs-light) !important;
    }
</style>

<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>" defer></script>
<script src="<?= AssetHelper::js('partes-search.js') ?>" defer></script>
<script>
async function confirmDeleteVariante(parteId, varianteId, codigo) {
    try {
        const response = await fetch('<?= url('api/v1/variantes') ?>/' + parteId + '/' + varianteId + '/can-delete');
        const data = await response.json();

        if (!data.can_delete) {
            const errorList = data.errors.map(e => '• ' + e).join('\n');
            alert('No se puede eliminar la variante "' + codigo + '":\n\n' + errorList);
            return;
        }

        if (data.is_last_variant) {
            const confirmed = confirm(
                'Esta es la única variante de la parte "' + data.parte_codigo + '".\n' +
                'Al eliminarla se eliminará también la parte, ya que no puede existir una parte sin variantes.\n\n' +
                '¿Desea continuar?'
            );
            if (!confirmed) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= url('productos/partes/') ?>' + parteId + '/variantes/' + varianteId;
            const methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);
            const forceField = document.createElement('input');
            forceField.type = 'hidden';
            forceField.name = 'force_delete_parte';
            forceField.value = '1';
            form.appendChild(forceField);
            document.body.appendChild(form);
            form.submit();
        } else {
            const confirmed = confirm('¿Eliminar la variante "' + codigo + '"? Esta acción no se puede deshacer.');
            if (!confirmed) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= url('productos/partes/') ?>' + parteId + '/variantes/' + varianteId;
            const methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);
            document.body.appendChild(form);
            form.submit();
        }
    } catch (e) {
        alert('Error al verificar la variante. Intente nuevamente.');
    }
}
</script>
