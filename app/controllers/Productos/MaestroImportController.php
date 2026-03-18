<?php

declare(strict_types=1);

namespace App\Controllers\Productos;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\Bom;
use App\Services\Bom\MaestroImportExportService;

final class MaestroImportController extends Controller
{
    private MaestroImportExportService $service;

    public function __construct(?MaestroImportExportService $service = null)
    {
        $this->service = $service ?? new MaestroImportExportService(new Bom());
    }

    public function index(Request $request): Response
    {
        return $this->render('pages/productos/composicion/import', [
            'title'  => 'Importar / Exportar Maestro BOM',
            'report' => null,
        ]);
    }

    public function downloadTemplate(Request $request): Response
    {
        $csv = $this->service->buildTemplateCsv();

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="plantilla_maestro_bom.csv"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function export(Request $request): Response
    {
        $csv = $this->service->exportToCsv();

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="maestro_bom_export.csv"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function import(Request $request): Response
    {
        $uploadedFile = $_FILES['archivo_importacion'] ?? null;

        if (!is_array($uploadedFile)) {
            return $this->render('pages/productos/composicion/import', [
                'title'  => 'Importar / Exportar Maestro BOM',
                'report' => [
                    'fatal_error'   => 'Debes seleccionar un archivo CSV para importar.',
                    'rows'          => [],
                    'total_rows'    => 0,
                    'ok_rows'       => 0,
                    'error_rows'    => 0,
                    'skipped_rows'  => 0,
                    'created_links' => 0,
                ],
            ]);
        }

        $report = $this->service->importFromUpload($uploadedFile);

        return $this->render('pages/productos/composicion/import', [
            'title'  => 'Importar / Exportar Maestro BOM',
            'report' => $report,
        ]);
    }
}
