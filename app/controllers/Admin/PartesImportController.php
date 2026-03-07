<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\GrupoParte;
use App\Models\Parte;
use App\Models\TipoParte;
use App\Models\UnidadMedida;
use App\Models\Variante;
use App\Services\Partes\PartesVariantesImportCsvParser;
use App\Services\Partes\PartesVariantesImportRowMapper;
use App\Services\Partes\PartesVariantesImportService;
use App\Services\Partes\PartesVariantesImportTemplateService;

final class PartesImportController extends Controller
{
    private TipoParte $tipos;
    private GrupoParte $grupos;
    private UnidadMedida $unidades;
    private PartesVariantesImportService $importService;
    private PartesVariantesImportTemplateService $templateService;

    public function __construct(
        ?TipoParte $tipos = null,
        ?GrupoParte $grupos = null,
        ?UnidadMedida $unidades = null,
        ?PartesVariantesImportService $importService = null,
        ?PartesVariantesImportTemplateService $templateService = null
    ) {
        $this->tipos = $tipos ?? new TipoParte();
        $this->grupos = $grupos ?? new GrupoParte();
        $this->unidades = $unidades ?? new UnidadMedida();

        $tiposActivos = $this->tipos->activos();
        $gruposActivos = $this->grupos->activos();
        $unidadesActivas = $this->unidades->allActive(1000, 0);

        $this->importService = $importService ?? new PartesVariantesImportService(
            new Parte(),
            new Variante(),
            new PartesVariantesImportCsvParser(),
            new PartesVariantesImportRowMapper(),
            $tiposActivos,
            $gruposActivos,
            $unidadesActivas
        );

        $this->templateService = $templateService ?? new PartesVariantesImportTemplateService(
            $this->tipos,
            $this->grupos,
            $this->unidades
        );
    }

    public function index(Request $request): Response
    {
        return $this->render('pages/admin/partes/import', [
            'title' => 'Importar Partes y Variantes',
            'tipos' => $this->tipos->activos(),
            'grupos' => $this->grupos->activos(),
            'unidades' => $this->unidades->allActive(1000, 0),
            'report' => null,
        ]);
    }

    public function downloadTemplate(Request $request): Response
    {
        $csv = $this->templateService->buildTemplateCsv();

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="plantilla_import_partes_variantes.csv"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function import(Request $request): Response
    {
        $uploadedFile = $_FILES['archivo_importacion'] ?? null;

        if (!is_array($uploadedFile)) {
            return $this->render('pages/admin/partes/import', [
                'title' => 'Importar Partes y Variantes',
                'tipos' => $this->tipos->activos(),
                'grupos' => $this->grupos->activos(),
                'unidades' => $this->unidades->allActive(1000, 0),
                'report' => [
                    'fatal_error' => 'Debes seleccionar un archivo CSV para importar.',
                    'rows' => [],
                    'total_rows' => 0,
                    'ok_rows' => 0,
                    'error_rows' => 0,
                    'created_parts' => 0,
                    'updated_parts' => 0,
                    'created_variants' => 0,
                    'updated_variants' => 0,
                ],
            ]);
        }

        $report = $this->importService->importFromUpload($uploadedFile);

        return $this->render('pages/admin/partes/import', [
            'title' => 'Importar Partes y Variantes',
            'tipos' => $this->tipos->activos(),
            'grupos' => $this->grupos->activos(),
            'unidades' => $this->unidades->allActive(1000, 0),
            'report' => $report,
        ]);
    }
}
