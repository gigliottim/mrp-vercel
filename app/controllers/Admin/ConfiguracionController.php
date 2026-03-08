<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\ConfiguracionGeneral;
use App\Services\EmpresaUsuariosService;
use App\Services\Partes\PartesGeometryRecalculationService;

class ConfiguracionController extends Controller
{
    private const DATE_FORMAT_OPTIONS = ['d/m/Y', 'm/d/Y', 'Y-m-d'];
    private const TIME_FORMAT_OPTIONS = ['H:i', 'H:i:s', 'h:i A'];
    private const ROUNDING_MODES = ['half_up', 'half_down', 'half_even', 'truncate'];
    private const SEPARATOR_OPTIONS = ['.', ',', ' '];

    private EmpresaUsuariosService $empresaUsuariosService;
    private ConfiguracionGeneral $configuracionGeneral;
    private PartesGeometryRecalculationService $partesGeometryRecalculationService;

    public function __construct(
        ?EmpresaUsuariosService $empresaUsuariosService = null,
        ?ConfiguracionGeneral $configuracionGeneral = null,
        ?PartesGeometryRecalculationService $partesGeometryRecalculationService = null
    ) {
        $this->empresaUsuariosService = $empresaUsuariosService ?? new EmpresaUsuariosService();
        $this->configuracionGeneral = $configuracionGeneral ?? new ConfiguracionGeneral();
        $this->partesGeometryRecalculationService = $partesGeometryRecalculationService ?? new PartesGeometryRecalculationService();
    }

    public function index(Request $request): Response
    {
        if (!$this->empresaUsuariosService->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/dashboard'));
        }

        $recalculationResult = null;
        if ((string) ($request->query['recalculated'] ?? '') === '1') {
            $recalculationResult = [
                'total' => (int) ($request->query['recalc_total'] ?? 0),
                'updated' => (int) ($request->query['recalc_updated'] ?? 0),
                'unchanged' => (int) ($request->query['recalc_unchanged'] ?? 0),
                'skipped' => (int) ($request->query['recalc_skipped'] ?? 0),
                'only_complete_dimensions' => (string) ($request->query['recalc_only_complete'] ?? '0') === '1',
            ];
        }

        return $this->renderGeneral([], [], (bool) ($request->query['saved'] ?? false), $recalculationResult);
    }

    public function update(Request $request): Response
    {
        if (!$this->empresaUsuariosService->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/dashboard'));
        }

        [$data, $errors] = $this->validateSettings($request);
        if ($errors !== []) {
            return $this->renderGeneral($data, $errors, false);
        }

        $this->configuracionGeneral->saveSettings($data);
        return Response::redirect(url('/configuracion/general?saved=1'));
    }

    public function recalculatePartesGeometry(Request $request): Response
    {
        if (!$this->empresaUsuariosService->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/dashboard'));
        }

        $onlyCompleteDimensions = (string) $request->input('only_complete_dimensions', '0') === '1';

        try {
            $result = $this->partesGeometryRecalculationService->recalculateAll($onlyCompleteDimensions);
        } catch (\Throwable $exception) {
            return $this->renderGeneral([], ['general' => 'Error al recalcular superficies/volumenes: ' . $exception->getMessage()], false, null);
        }

        $query = http_build_query([
            'recalculated' => 1,
            'recalc_total' => (int) ($result['total'] ?? 0),
            'recalc_updated' => (int) ($result['updated'] ?? 0),
            'recalc_unchanged' => (int) ($result['unchanged'] ?? 0),
            'recalc_skipped' => (int) ($result['skipped'] ?? 0),
            'recalc_only_complete' => !empty($result['only_complete_dimensions']) ? 1 : 0,
        ]);

        return Response::redirect(url('/configuracion/general?' . $query));
    }

    private function renderGeneral(array $old, array $errors, bool $saved, ?array $recalculationResult = null): Response
    {
        $settings = $this->configuracionGeneral->getSettings();
        $oldValue = static fn(string $key, $default = '') => array_key_exists($key, $old) ? $old[$key] : $default;

        return $this->render('pages/admin/configuracion/general', [
            'title' => 'Configuración General',
            'settings' => $settings,
            'errors' => $errors,
            'saved' => $saved,
            'recalculationResult' => $recalculationResult,
            'oldValue' => $oldValue,
            'dateFormatOptions' => self::DATE_FORMAT_OPTIONS,
            'timeFormatOptions' => self::TIME_FORMAT_OPTIONS,
            'roundingModeOptions' => self::ROUNDING_MODES,
            'separatorOptions' => self::SEPARATOR_OPTIONS,
        ]);
    }

    private function validateSettings(Request $request): array
    {
        $data = [
            'decimal_places' => (int) $request->input('decimal_places', 4),
            'rounding_mode' => trim((string) $request->input('rounding_mode', 'half_up')),
            'thousand_separator' => (string) $request->input('thousand_separator', '.'),
            'decimal_separator' => (string) $request->input('decimal_separator', ','),
            'date_format' => trim((string) $request->input('date_format', 'd/m/Y')),
            'time_format' => trim((string) $request->input('time_format', 'H:i')),
        ];

        $errors = [];

        if ($data['decimal_places'] < 1 || $data['decimal_places'] > 10) {
            $errors['decimal_places'] = 'La cantidad de decimales debe estar entre 1 y 10.';
        }

        if (!in_array($data['rounding_mode'], self::ROUNDING_MODES, true)) {
            $errors['rounding_mode'] = 'Modo de redondeo inválido.';
        }

        if (!in_array($data['thousand_separator'], self::SEPARATOR_OPTIONS, true)) {
            $errors['thousand_separator'] = 'Separador de miles inválido.';
        }

        if (!in_array($data['decimal_separator'], self::SEPARATOR_OPTIONS, true)) {
            $errors['decimal_separator'] = 'Separador decimal inválido.';
        }

        if ($data['thousand_separator'] === $data['decimal_separator']) {
            $errors['decimal_separator'] = 'El separador decimal debe ser distinto al de miles.';
        }

        if (!in_array($data['date_format'], self::DATE_FORMAT_OPTIONS, true)) {
            $errors['date_format'] = 'Formato de fecha inválido.';
        }

        if (!in_array($data['time_format'], self::TIME_FORMAT_OPTIONS, true)) {
            $errors['time_format'] = 'Formato de hora inválido.';
        }

        return [$data, $errors];
    }
}
