<?php

declare(strict_types=1);

namespace App\Controllers\Productos;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\Bom;
use App\Models\Variante;

final class HerramientasBomController extends Controller
{
    private Bom $bom;
    private Variante $variante;

    public function __construct(?Bom $bom = null, ?Variante $variante = null)
    {
        $this->bom      = $bom      ?? new Bom();
        $this->variante = $variante ?? new Variante();
    }

    // ─── Copiar Componentes ───────────────────────────────────────────────────

    public function copiarComponentes(Request $request): Response
    {
        return $this->render('pages/productos/herramientas/copiar-componentes', [
            'title'    => 'Copiar Componentes de BOM',
            'resultado' => null,
            'errors'   => [],
        ]);
    }

    public function ejecutarCopiarComponentes(Request $request): Response
    {
        $origenId  = (int) $request->input('id_variante_origen');
        $destinoId = (int) $request->input('id_variante_destino');
        $errors    = [];
        $resultado = null;

        if ($origenId <= 0 || $destinoId <= 0) {
            $errors[] = 'Debe seleccionar la pieza de origen y la pieza de destino.';
        } elseif ($origenId === $destinoId) {
            $errors[] = 'La pieza de origen y de destino no pueden ser la misma.';
        } else {
            $origenBom = $this->bom->getActiveByVariante($origenId);

            if ($origenBom === null) {
                $errors[] = 'La pieza de origen no tiene una BOM activa con componentes para copiar.';
            } else {
                $origenDetails = $this->bom->getDetalles((int) $origenBom['id']);

                if ($origenDetails === []) {
                    $errors[] = 'La BOM de la pieza de origen no contiene componentes de nivel 1.';
                } else {
                    $destinoBom   = $this->bom->getActiveByVariante($destinoId);
                    $destinoBomId = $destinoBom !== null
                        ? (int) $destinoBom['id']
                        : $this->bom->createHeader($destinoId);

                    $eliminados = $this->bom->deleteAllDetails($destinoBomId);
                    $copiados   = 0;
                    $saltados   = [];

                    foreach ($origenDetails as $item) {
                        $componentId = (int) $item['variante_componente_id'];
                        $validation  = $this->bom->validateAddComponent($destinoId, $componentId);

                        if (!$validation['valid']) {
                            $codigo    = ($item['parte_codigo'] ?? '') . '-' . ($item['componente_codigo'] ?? '');
                            $saltados[] = trim($codigo, '-') . ': ' . ($validation['error'] ?? 'No válido');
                            continue;
                        }

                        $this->bom->addDetail(
                            $destinoBomId,
                            $componentId,
                            (float) $item['cantidad_necesaria'],
                            (int) $item['unidad_medida_id']
                        );
                        $copiados++;
                    }

                    $resultado = [
                        'copiados'   => $copiados,
                        'eliminados' => $eliminados,
                        'saltados'   => $saltados,
                    ];
                }
            }
        }

        return $this->render('pages/productos/herramientas/copiar-componentes', [
            'title'               => 'Copiar Componentes de BOM',
            'resultado'           => $resultado,
            'errors'              => $errors,
            'id_variante_origen'  => $origenId  > 0 ? $origenId  : null,
            'id_variante_destino' => $destinoId > 0 ? $destinoId : null,
        ]);
    }

    // ─── Reemplazar Partes en el Maestro ─────────────────────────────────────

    public function reemplazarPartes(Request $request): Response
    {
        return $this->render('pages/productos/herramientas/reemplazar-partes', [
            'title'      => 'Reemplazar Partes en el Maestro',
            'whereUsed'  => [],
            'origenInfo' => null,
            'nuevaInfo'  => null,
            'resultado'  => null,
            'errors'     => [],
            'step'       => 'form',
        ]);
    }

    public function ejecutarReemplazarPartes(Request $request): Response
    {
        $action    = (string) ($request->input('action') ?? 'preview');
        $origenId  = (int) $request->input('id_variante_origen');
        $nuevaId   = (int) $request->input('id_variante_nueva');

        $errors     = [];
        $resultado  = null;
        $whereUsed  = [];
        $origenInfo = null;
        $nuevaInfo  = null;
        $step       = 'form';

        if ($origenId <= 0) {
            $errors[] = 'Debe seleccionar la pieza a reemplazar (X).';
        } else {
            $origenInfo = $this->variante->getFullDetails($origenId);

            if ($origenInfo === null) {
                $errors[] = 'La pieza a reemplazar no existe.';
            } else {
                $whereUsed = $this->bom->getWhereUsed($origenId);
            }
        }

        if ($errors !== []) {
            return $this->renderReemplazar($step, $errors, $whereUsed, $origenInfo, $nuevaInfo, $resultado, $origenId, $nuevaId);
        }

        if ($action === 'preview') {
            if ($nuevaId > 0) {
                $nuevaInfo = $this->variante->getFullDetails($nuevaId);
            }
            $step = 'preview';
        } elseif ($action === 'ejecutar') {
            if ($nuevaId <= 0) {
                $errors[] = 'Debe seleccionar la pieza de reemplazo (H).';
            } elseif ($nuevaId === $origenId) {
                $errors[] = 'La pieza a reemplazar y la de reemplazo no pueden ser la misma.';
            } else {
                $nuevaInfo = $this->variante->getFullDetails($nuevaId);

                if ($nuevaInfo === null) {
                    $errors[] = 'La pieza de reemplazo no existe.';
                }
            }

            if ($errors === []) {
                $bomIdsInput = $request->input('bom_ids', []);
                $bomIds = is_array($bomIdsInput)
                    ? array_values(array_filter(array_map('intval', $bomIdsInput), static fn($id) => $id > 0))
                    : array_values(array_column($whereUsed, 'bom_id'));

                if ($bomIds === []) {
                    $bomIds = array_values(array_column($whereUsed, 'bom_id'));
                }

                if ($bomIds === []) {
                    $errors[] = 'La pieza seleccionada no aparece en ningún maestro activo.';
                } else {
                    $reemplazados = $this->bom->replaceComponentInBoms($origenId, $nuevaId, $bomIds);
                    $resultado    = [
                        'reemplazados'   => $reemplazados,
                        'boms_afectadas' => count($bomIds),
                    ];
                    $step = 'resultado';
                }
            }

            if ($errors !== []) {
                $step = 'preview';
            }
        }

        return $this->renderReemplazar($step, $errors, $whereUsed, $origenInfo, $nuevaInfo, $resultado, $origenId, $nuevaId);
    }

    /** @param array<int, array<string,mixed>> $whereUsed */
    private function renderReemplazar(
        string $step,
        array $errors,
        array $whereUsed,
        ?array $origenInfo,
        ?array $nuevaInfo,
        ?array $resultado,
        int $origenId,
        int $nuevaId
    ): Response {
        return $this->render('pages/productos/herramientas/reemplazar-partes', [
            'title'              => 'Reemplazar Partes en el Maestro',
            'whereUsed'          => $whereUsed,
            'origenInfo'         => $origenInfo,
            'nuevaInfo'          => $nuevaInfo,
            'resultado'          => $resultado,
            'errors'             => $errors,
            'step'               => $step,
            'id_variante_origen' => $origenId > 0 ? $origenId : null,
            'id_variante_nueva'  => $nuevaId  > 0 ? $nuevaId  : null,
        ]);
    }
}
