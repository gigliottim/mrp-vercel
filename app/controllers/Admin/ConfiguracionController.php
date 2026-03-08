<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\ConfiguracionGeneral;
use App\Services\EmpresaUsuariosService;

class ConfiguracionController extends Controller
{
    private const DATE_FORMAT_OPTIONS = ['d/m/Y', 'm/d/Y', 'Y-m-d'];
    private const TIME_FORMAT_OPTIONS = ['H:i', 'H:i:s', 'h:i A'];
    private const ROUNDING_MODES = ['half_up', 'half_down', 'half_even', 'truncate'];
    private const SEPARATOR_OPTIONS = ['.', ',', ' '];

    private EmpresaUsuariosService $empresaUsuariosService;
    private ConfiguracionGeneral $configuracionGeneral;

    public function __construct(?EmpresaUsuariosService $empresaUsuariosService = null, ?ConfiguracionGeneral $configuracionGeneral = null)
    {
        $this->empresaUsuariosService = $empresaUsuariosService ?? new EmpresaUsuariosService();
        $this->configuracionGeneral = $configuracionGeneral ?? new ConfiguracionGeneral();
    }

    public function index(Request $request): Response
    {
        if (!$this->empresaUsuariosService->isCurrentUserCompanyAdmin()) {
            return Response::redirect(url('/dashboard'));
        }

        return $this->renderGeneral([], [], (bool) ($request->query['saved'] ?? false));
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

    private function renderGeneral(array $old, array $errors, bool $saved): Response
    {
        $settings = $this->configuracionGeneral->getSettings();
        $oldValue = static fn(string $key, $default = '') => array_key_exists($key, $old) ? $old[$key] : $default;

        return $this->render('pages/admin/configuracion/general', [
            'title' => 'Configuración General',
            'settings' => $settings,
            'errors' => $errors,
            'saved' => $saved,
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

        if ($data['decimal_places'] < 1 || $data['decimal_places'] > 6) {
            $errors['decimal_places'] = 'La cantidad de decimales debe estar entre 1 y 6.';
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
