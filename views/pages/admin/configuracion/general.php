<?php

use App\Core\View\View;

$settings = $settings ?? [];
$errors = $errors ?? [];
$saved = $saved ?? false;
$oldValue = $oldValue ?? static fn(string $key, $default = '') => $default;
$dateFormatOptions = $dateFormatOptions ?? ['d/m/Y', 'm/d/Y', 'Y-m-d'];
$timeFormatOptions = $timeFormatOptions ?? ['H:i', 'H:i:s', 'h:i A'];
$roundingModeOptions = $roundingModeOptions ?? ['half_up', 'half_down', 'half_even', 'truncate'];
$separatorOptions = $separatorOptions ?? ['.', ',', ' '];

$labelsRoundingMode = [
    'half_up' => 'Mitad hacia arriba',
    'half_down' => 'Mitad hacia abajo',
    'half_even' => 'Mitad al par (bancario)',
    'truncate' => 'Truncar',
];

$formatExampleNumber = static fn(string $thousandSep, string $decimalSep): string => '12' . $thousandSep . '345' . $decimalSep . '6789';
?>

<section class="mb-4">
    <h1 class="h3 mb-1">Configuración General</h1>
    <p class="text-muted mb-0">Parámetros de formato para números, fecha y hora de la empresa actual.</p>
</section>

<?php if ($saved): ?>
    <div class="alert alert-success" role="alert">
        Configuración guardada correctamente.
    </div>
<?php endif; ?>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger" role="alert">
        <?= View::escape((string) $errors['general']) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= url('/configuracion/general') ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="decimal_places">Cantidad de decimales (1-6)</label>
                    <input
                        type="number"
                        class="form-control<?= isset($errors['decimal_places']) ? ' is-invalid' : '' ?>"
                        id="decimal_places"
                        name="decimal_places"
                        min="1"
                        max="6"
                        value="<?= View::escape((string) $oldValue('decimal_places', $settings['decimal_places'] ?? 4)) ?>"
                        required>
                    <?php if (isset($errors['decimal_places'])): ?>
                        <div class="invalid-feedback"><?= View::escape((string) $errors['decimal_places']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="rounding_mode">Modo de redondeo</label>
                    <?php $selectedRoundingMode = (string) $oldValue('rounding_mode', $settings['rounding_mode'] ?? 'half_up'); ?>
                    <select class="form-select<?= isset($errors['rounding_mode']) ? ' is-invalid' : '' ?>" id="rounding_mode" name="rounding_mode" required>
                        <?php foreach ($roundingModeOptions as $mode): ?>
                            <option value="<?= View::escape((string) $mode) ?>" <?= $selectedRoundingMode === (string) $mode ? 'selected' : '' ?>>
                                <?= View::escape($labelsRoundingMode[$mode] ?? (string) $mode) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['rounding_mode'])): ?>
                        <div class="invalid-feedback"><?= View::escape((string) $errors['rounding_mode']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="thousand_separator">Separador de miles</label>
                    <?php $selectedThousandSeparator = (string) $oldValue('thousand_separator', $settings['thousand_separator'] ?? '.'); ?>
                    <select class="form-select<?= isset($errors['thousand_separator']) ? ' is-invalid' : '' ?>" id="thousand_separator" name="thousand_separator" required>
                        <?php foreach ($separatorOptions as $separator): ?>
                            <?php
                            $separatorLabel = $separator === ' ' ? 'Espacio' : $separator;
                            $separatorPreview = $formatExampleNumber($separator, ',');
                            ?>
                            <option value="<?= View::escape((string) $separator) ?>" <?= $selectedThousandSeparator === (string) $separator ? 'selected' : '' ?>>
                                <?= View::escape($separatorLabel . ' (' . $separatorPreview . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['thousand_separator'])): ?>
                        <div class="invalid-feedback"><?= View::escape((string) $errors['thousand_separator']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="decimal_separator">Separador decimal</label>
                    <?php $selectedDecimalSeparator = (string) $oldValue('decimal_separator', $settings['decimal_separator'] ?? ','); ?>
                    <select class="form-select<?= isset($errors['decimal_separator']) ? ' is-invalid' : '' ?>" id="decimal_separator" name="decimal_separator" required>
                        <?php foreach ($separatorOptions as $separator): ?>
                            <?php
                            $separatorLabel = $separator === ' ' ? 'Espacio' : $separator;
                            $separatorPreview = $formatExampleNumber('.', $separator);
                            ?>
                            <option value="<?= View::escape((string) $separator) ?>" <?= $selectedDecimalSeparator === (string) $separator ? 'selected' : '' ?>>
                                <?= View::escape($separatorLabel . ' (' . $separatorPreview . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['decimal_separator'])): ?>
                        <div class="invalid-feedback"><?= View::escape((string) $errors['decimal_separator']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="date_format">Formato de fecha</label>
                    <?php $selectedDateFormat = (string) $oldValue('date_format', $settings['date_format'] ?? 'd/m/Y'); ?>
                    <select class="form-select<?= isset($errors['date_format']) ? ' is-invalid' : '' ?>" id="date_format" name="date_format" required>
                        <?php foreach ($dateFormatOptions as $format): ?>
                            <option value="<?= View::escape((string) $format) ?>" <?= $selectedDateFormat === (string) $format ? 'selected' : '' ?>>
                                <?= View::escape((string) $format) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['date_format'])): ?>
                        <div class="invalid-feedback"><?= View::escape((string) $errors['date_format']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="time_format">Formato de hora</label>
                    <?php $selectedTimeFormat = (string) $oldValue('time_format', $settings['time_format'] ?? 'H:i'); ?>
                    <select class="form-select<?= isset($errors['time_format']) ? ' is-invalid' : '' ?>" id="time_format" name="time_format" required>
                        <?php foreach ($timeFormatOptions as $format): ?>
                            <option value="<?= View::escape((string) $format) ?>" <?= $selectedTimeFormat === (string) $format ? 'selected' : '' ?>>
                                <?= View::escape((string) $format) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['time_format'])): ?>
                        <div class="invalid-feedback"><?= View::escape((string) $errors['time_format']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary">Guardar configuración</button>
            </div>
        </form>
    </div>
</div>
