<?php

declare(strict_types=1);

namespace App\Controllers\Reportes;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\Variante;
use App\Models\Bom;
use App\Models\GrupoParte;
use App\Models\TipoParte;
use App\Services\Reportes\ListadoIngenieriaExportService;
use App\Services\UnitConversionService;

final class ReportesController extends Controller
{
    private Variante $variantes;
    private Bom $bomModel;
    private GrupoParte $grupos;
    private TipoParte $tiposPartes;
    private UnitConversionService $unitConversion;
    private ListadoIngenieriaExportService $listadoExport;

    public function __construct(
        ?Variante $variantes = null,
        ?Bom $bomModel = null,
        ?GrupoParte $grupos = null,
        ?TipoParte $tiposPartes = null,
        ?UnitConversionService $unitConversion = null,
        ?ListadoIngenieriaExportService $listadoExport = null
    ) {
        $this->variantes = $variantes ?? new Variante();
        $this->bomModel = $bomModel ?? new Bom();
        $this->grupos = $grupos ?? new GrupoParte();
        $this->tiposPartes = $tiposPartes ?? new TipoParte();
        $this->unitConversion = $unitConversion ?? new UnitConversionService();
        $this->listadoExport = $listadoExport ?? new ListadoIngenieriaExportService();
    }

    public function destinoPartes(Request $request): Response
    {
        $selectedVarianteId = isset($request->query['id_variante']) ? (int) $request->query['id_variante'] : null;

        // Obtener todas las variantes disponibles
        $variantesList = $this->variantes->allWithPartes();
        $variantes = [];
        foreach ($variantesList as $v) {
            $variantes[$v['id']] = $v;
        }

        // Datos de la variante seleccionada
        $varianteSeleccionada = null;
        $composicionRama1 = [];
        $composicionPlana = [];
        $composicionArbol = [];
        $dondeSeUtiliza = [];

        if ($selectedVarianteId && isset($variantes[$selectedVarianteId])) {
            $varianteSeleccionada = $variantes[$selectedVarianteId];

            // Obtener la BOM activa de esta variante
            $bom = $this->bomModel->getActiveByVariante($selectedVarianteId);

            if ($bom) {
                // Rama 1: solo primer nivel (detalles de la BOM)
                $composicionRama1 = $this->bomModel->getDetalles((int) $bom['id']);

                // Composición en árbol: estructura jerárquica (formato plano con niveles)
                $composicionArbol = $this->bomModel->getTree($selectedVarianteId);

                // Composición plana: consolidar todos los niveles en componentes únicos
                $consolidado = [];
                foreach ($composicionArbol as $item) {
                    // Excluir el nivel 0 (el producto padre)
                    if (($item['nivel'] ?? 0) === 0) {
                        continue;
                    }

                    $varianteId = $item['variante_id'];

                    // Si ya existe este componente, sumar la cantidad
                    if (isset($consolidado[$varianteId])) {
                        $consolidado[$varianteId]['cantidad_necesaria'] += (float)$item['cantidad'];
                    } else {
                        // Primera aparición del componente
                        $consolidado[$varianteId] = [
                            'componente_codigo' => $item['codigo_variante'] ?? 'N/A',
                            'componente_detalle' => $item['variante_detalle'] ?? '',
                            'cantidad_necesaria' => (float)$item['cantidad'],
                            'unidad_simbolo' => $item['unidad'] ?? 'UN',
                        ];
                    }
                }
                $composicionPlana = array_values($consolidado);
            }

            // Dónde se utiliza: buscar en qué BOMs aparece esta variante
            $dondeSeUtiliza = $this->bomModel->getWhereUsed($selectedVarianteId);
        }

        return $this->render('pages/reportes/destino-partes', [
            'title' => 'Destino de Partes',
            'variantes' => $variantes,
            'selectedVarianteId' => $selectedVarianteId,
            'varianteSeleccionada' => $varianteSeleccionada,
            'composicionRama1' => $composicionRama1,
            'composicionPlana' => $composicionPlana,
            'composicionArbol' => $composicionArbol,
            'dondeSeUtiliza' => $dondeSeUtiliza,
        ]);
    }

    public function listadoIngenieria(Request $request): Response
    {
        $selectedVarianteId = isset($request->query['id_variante']) ? (int) $request->query['id_variante'] : null;
        $cantidad = isset($request->query['cantidad']) ? (float) $request->query['cantidad'] : 1.0;
        $tipoSalida = $request->query['tipo_salida'] ?? 'arbol'; // arbol, plana, rama1
        $conPrecios = isset($request->query['con_precios']) && $request->query['con_precios'] === '1';

        // Obtener todas las variantes disponibles para el selector
        $variantesList = $this->variantes->allWithPartes();
        $variantes = [];
        foreach ($variantesList as $v) {
            $variantes[$v['id']] = $v;
        }

        // Obtener tipos de partes
        $tiposPartesList = $this->tiposPartes->activos();
        $tiposPartesMap = [];
        $todosLosTiposIds = [];
        foreach ($tiposPartesList as $tp) {
            $tiposPartesMap[$tp['id']] = $tp['nombre'];
            $todosLosTiposIds[] = $tp['id'];
        }

        // Nuevos filtros
        $agruparTipo = isset($request->query['agrupar_tipo']) && $request->query['agrupar_tipo'] === '1';
        $ordenarTipo = isset($request->query['ordenar_tipo']) && $request->query['ordenar_tipo'] === '1';

        // Lógica de mostrar tipos:
        // Si viene el parámetro 'filtros_aplicados', usamos lo que traiga 'mostrar_tipos' (o vacío si desmarcó todo).
        // Si NO viene 'filtros_aplicados' (carga inicial), seleccionamos TODOS por defecto.
        $filtrosAplicados = isset($request->query['filtros_aplicados']);

        if ($filtrosAplicados) {
            $mostrarTipos = isset($request->query['mostrar_tipos']) && is_array($request->query['mostrar_tipos'])
                ? array_map('intval', $request->query['mostrar_tipos'])
                : [];
        } else {
            // Estado inicial: Todos seleccionados
            $mostrarTipos = $todosLosTiposIds;
        }

        // Datos de la variante seleccionada
        $varianteSeleccionada = null;
        $datosReporte = [];

        if ($selectedVarianteId && isset($variantes[$selectedVarianteId])) {
            $varianteSeleccionada = $variantes[$selectedVarianteId];

            // Obtener la BOM activa de esta variante
            $bom = $this->bomModel->getActiveByVariante($selectedVarianteId);

            if ($bom) {
                switch ($tipoSalida) {
                    case 'arbol':
                        $datosReporte = $this->bomModel->getTree($selectedVarianteId);
                        // Multiplicar cantidades por la cantidad solicitada
                        foreach ($datosReporte as &$item) {
                            $item['cantidad_ajustada'] = $item['cantidad'] * $cantidad;
                            // Asegurar que exista tipo_parte_id (depende de la consulta, asumimos que viene o lo buscamos)
                            // Si getTree no trae tipo_parte_id, no podremos filtrar/ordenar por tipo.
                            // Asumiremos que viene. Si no, habría que corregir getTree.
                        }
                        unset($item);
                        break;

                    case 'plana':
                        // Obtener el árbol completo y consolidar componentes
                        $arbolCompleto = $this->bomModel->getTree($selectedVarianteId);
                        $consolidado = [];

                        foreach ($arbolCompleto as $item) {
                            // Excluir el nivel 0 (el producto padre)
                            if (($item['nivel'] ?? 0) === 0) {
                                continue;
                            }

                            $varianteId = $item['variante_id'];

                            // Si ya existe este componente, sumar la cantidad
                            if (isset($consolidado[$varianteId])) {
                                $consolidado[$varianteId]['cantidad_total'] += (float)$item['cantidad'];
                            } else {
                                // Primera aparición del componente
                                $consolidado[$varianteId] = [
                                    'variante_id' => $varianteId,
                                    'parte_codigo' => $item['parte_codigo'] ?? '',
                                    'parte_detalle' => $item['parte_detalle'] ?? '',
                                    'codigo_variante' => $item['codigo_variante'] ?? 'N/A',
                                    'variante_detalle' => $item['variante_detalle'] ?? '',
                                    'tipo_codigo' => $item['tipo_codigo'] ?? '',
                                    'tipo_parte_id' => $item['tipo_parte_id'] ?? null, // Necesario para filtrar
                                    'unidad' => $item['unidad'] ?? 'UN',
                                    'cantidad_total' => (float)$item['cantidad'],
                                ];
                            }
                        }

                        // Convertir a array indexado y calcular cantidad ajustada
                        $datosReporte = array_values($consolidado);
                        foreach ($datosReporte as &$item) {
                            $item['cantidad_ajustada'] = $item['cantidad_total'] * $cantidad;
                        }
                        unset($item);
                        break;

                    case 'rama1':
                        // Solo el primer nivel: detalles de la BOM principal
                        $datosReporte = $this->bomModel->getDetalles((int) $bom['id']);
                        // Multiplicar cantidades por la cantidad solicitada
                        foreach ($datosReporte as &$item) {
                            $item['cantidad_ajustada'] = $item['cantidad_necesaria'] * $cantidad;
                            // getDetalles debería traer información de la parte/variante, incluido tipo_parte_id
                        }
                        unset($item);
                        break;
                }

                // Aplicar Filtros (Mostrar Solo)
                // Si hay filtros aplicados, filtramos estrictamente por la selección.
                // Si no hay filtros aplicados (carga inicial), filtramos por "todos" los tipos (que es lo que cargamos en $mostrarTipos).
                // Nota: Si $mostrarTipos está vacío (usuario desmarcó todo), el resultado será vacío.
                if ($filtrosAplicados || !empty($mostrarTipos)) {
                    $datosReporte = array_filter($datosReporte, function ($item) use ($mostrarTipos) {
                        // Si es nivel 0 (padre en árbol), siempre mostrar? O filtrar también?
                        // Generalmente el padre se muestra.
                        if (isset($item['nivel']) && $item['nivel'] == 0) return true;

                        $tipoId = $item['tipo_parte_id'] ?? null;
                        return in_array($tipoId, $mostrarTipos);
                    });
                    // Reindexar array si es necesario, pero array_filter mantiene keys si no se usa Flag.
                    // Para la vista plana/rama1 mejor reindexar. Para árbol, el índice no afecta orden pero sí acceso.
                    $datosReporte = array_values($datosReporte);
                }

                // Aplicar Ordenamiento
                if ($ordenarTipo) {
                    usort($datosReporte, function ($a, $b) use ($tiposPartesMap) {
                        // Mantener estructura de árbol valida es dificil con sort simple.
                        // Si es árbol, ordenar hijos dentro de padres? Eso requiere estructura recursiva, no lista plana.
                        // Si ordenamos la lista plana del árbol por tipo, rompemos el árbol visualmente.

                        // Solo aplicaremos ordenamiento si NO es árbol, O si el usuario entiende que rompe el árbol.
                        // O bien, implementamos un sort inteligente que respete niveles... muy complejo.
                        // Asumiremos orden simple por ahora.

                        $tipoA = $tiposPartesMap[$a['tipo_parte_id'] ?? 0] ?? '';
                        $tipoB = $tiposPartesMap[$b['tipo_parte_id'] ?? 0] ?? '';
                        return strcmp($tipoA, $tipoB);
                    });
                }

                // Aplicar Agrupamiento (Solo lógica de datos? O estructura?)
                // Si agrupamos, cambiamos la estructura de los datos. La vista debe soportarlo.
                // Si $agruparTipo es true, retornaremos un array estructurado por grupos.
                if ($agruparTipo) {
                    $agrupado = [];
                    foreach ($datosReporte as $item) {
                        $tipoId = $item['tipo_parte_id'] ?? 0;
                        $nombreTipo = $tiposPartesMap[$tipoId] ?? 'Sin Tipo';
                        $agrupado[$nombreTipo][] = $item;
                    }
                    // Sort groups by name
                    ksort($agrupado);
                    $datosReporte = $agrupado; // Estructura cambia a [ 'Tipo A' => [...items], ... ]
                }
            }
        }

        $exportType = isset($request->query['export']) ? strtolower((string) $request->query['export']) : '';
        if (($exportType === 'xlsx' || $exportType === 'pdf') && $varianteSeleccionada && !empty($datosReporte)) {
            $tabular = $this->listadoExport->buildTabularData($datosReporte, $tipoSalida, $conPrecios, $agruparTipo);
            $baseName = sprintf(
                'listado-ingenieria-%s-%s',
                preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($varianteSeleccionada['parte_codigo'] ?? 'variante')),
                date('Ymd-His')
            );

            if ($exportType === 'xlsx') {
                $xlsx = $this->listadoExport->generateXlsx($tabular['headers'], $tabular['rows']);
                return new Response($xlsx, 200, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $baseName . '.xlsx"',
                    'Content-Length' => (string) strlen($xlsx),
                ]);
            }

            $pdf = $this->listadoExport->generatePdf($tabular['headers'], $tabular['rows'], 'Listado de Ingenieria');
            return new Response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $baseName . '.pdf"',
                'Content-Length' => (string) strlen($pdf),
            ]);
        }

        return $this->render('pages/reportes/listado-ingenieria', [
            'title' => 'Listado de Ingeniería',
            'variantes' => $variantes,
            'selectedVarianteId' => $selectedVarianteId,
            'varianteSeleccionada' => $varianteSeleccionada,
            'cantidad' => $cantidad,
            'tipoSalida' => $tipoSalida,
            'conPrecios' => $conPrecios,
            'datosReporte' => $datosReporte,
            'tiposPartesList' => $tiposPartesList,
            'agruparTipo' => $agruparTipo,
            'ordenarTipo' => $ordenarTipo,
            'mostrarTipos' => $mostrarTipos,
            'tiposPartesMap' => $tiposPartesMap
        ]);
    }

    public function planificacionProduccion(Request $request): Response
    {
        // Obtener todas las variantes disponibles para el selector
        $variantesList = $this->variantes->allWithPartes();
        $variantes = [];
        foreach ($variantesList as $v) {
            $variantes[$v['id']] = $v;
        }

        // Obtener productos programados desde la sesión o request
        $productosProgramados = $request->query['productos'] ?? [];

        // Si viene del formulario de agregar
        if (isset($request->query['add_variante'])) {
            $varianteId = (int) $request->query['variante_id'];
            $cantidad = (float) ($request->query['cantidad'] ?? 1);

            if ($varianteId > 0 && $cantidad > 0) {
                $productosProgramados[$varianteId] = $cantidad;
            }
        }

        // Si viene del formulario de eliminar
        if (isset($request->query['remove_variante'])) {
            $varianteId = (int) $request->query['remove_variante'];
            unset($productosProgramados[$varianteId]);
        }

        // Obtener configuración de tipos de parte
        $tiposPartes = $this->tiposPartes->activos();
        $controlStockMap = [];
        foreach ($tiposPartes as $tp) {
            $code = strtolower(trim($tp['codigo']));
            $controlStockMap[$code] = isset($tp['requiere_stock']) ? (bool) $tp['requiere_stock'] : true;
        }

        // Calcular requerimientos consolidados
        $requerimientos = $this->calcularRequerimientos($productosProgramados, $controlStockMap);

        return $this->render('pages/reportes/planificacion-produccion', [
            'title' => 'Planificación de la Producción',
            'variantes' => $variantes,
            'productosProgramados' => $productosProgramados,
            'requerimientos' => $requerimientos,
        ]);
    }

    private function calcularRequerimientos(array $productosProgramados, array $controlStockMap = []): array
    {
        if (empty($productosProgramados)) {
            return [];
        }

        $consolidado = [];

        // Para cada producto programado, explotar su BOM
        foreach ($productosProgramados as $varianteId => $cantidad) {
            $arbol = $this->bomModel->getTree((int) $varianteId);

            foreach ($arbol as $item) {
                // Excluir el nivel 0 (el producto padre)
                if (($item['nivel'] ?? 0) === 0) {
                    continue;
                }

                $componenteId = $item['variante_id'];
                $cantidadNecesaria = (float) $item['cantidad'] * $cantidad;

                // Consolidar componentes
                if (isset($consolidado[$componenteId])) {
                    $consolidado[$componenteId]['programado'] += $cantidadNecesaria;
                } else {
                    // Primera aparición, usar datos del árbol y complementar con datos de variante
                    $varianteInfo = $this->variantes->getFullDetails($componenteId);

                    $consolidado[$componenteId] = [
                        'variante_id' => $componenteId,
                        'parte_id' => $varianteInfo ? (int) ($varianteInfo['parte_id'] ?? 0) : 0,
                        'codigo' => $item['codigo_variante'] ?? 'N/A',
                        'parte_codigo' => $varianteInfo ? ($varianteInfo['parte_codigo'] ?? '') : '',
                        'detalle' => $item['variante_detalle'] ?? '',
                        'unidad' => $item['unidad'] ?? 'UN',
                        'um_compra' => $varianteInfo['um_compra_simbolo'] ?? ($item['unidad'] ?? 'UN'),
                        'um_uso' => $varianteInfo['um_uso_simbolo'] ?? ($item['unidad'] ?? 'UN'),
                        'tipo' => $item['tipo_codigo'] ?? '',
                        'programado' => $cantidadNecesaria,
                        'stock' => $varianteInfo ? (float) ($varianteInfo['stock_actual'] ?? 0) : 0,
                        'lote_minimo' => $varianteInfo ? (float) ($varianteInfo['lote_minimo'] ?? 1) : 1,
                        'factor_conversion' => $varianteInfo ? (float) ($varianteInfo['factor_conversion'] ?? 1) : 1,
                    ];
                }
            }
        }

        // Calcular faltantes y cantidades a comprar
        foreach ($consolidado as &$item) {
            $tipoCodigo = strtolower(trim((string) ($item['tipo'] ?? '')));
            // Por defecto, asumimos que requiere stock si no se especifica lo contrario
            $requiereStock = $controlStockMap[$tipoCodigo] ?? true;

            $faltante = max(0, $item['programado'] - $item['stock']);

            if (!$requiereStock) {
                $faltante = 0;
            }

            $item['faltante'] = $faltante;

            // Calcular cantidad a comprar considerando lote mínimo
            if ($faltante > 0) {
                $loteMinimo = $item['lote_minimo'];

                $purchasePlan = $this->unitConversion->planPurchase(
                    $faltante,
                    (float) ($item['factor_conversion'] ?? 1),
                    $loteMinimo,
                    null
                );

                $item['a_comprar'] = $purchasePlan['purchase_qty'];
                $item['a_comprar_uso'] = $purchasePlan['usage_qty_from_purchase'];
                $item['a_comprar_um'] = $item['um_compra'];
                $item['stock_final'] = $item['stock'] + $purchasePlan['usage_qty_from_purchase'] - $item['programado'];
            } else {
                $item['a_comprar'] = 0;
                $item['a_comprar_uso'] = 0;
                $item['a_comprar_um'] = $item['um_compra'];
                $item['stock_final'] = $item['stock'] - $item['programado'];
            }

            // TODO: Obtener precio unitario real del sistema
            $item['precio_unitario'] = 0;
            $item['a_comprar_precio'] = $item['a_comprar'] * $item['precio_unitario'];
        }

        // Ordenar por código
        usort($consolidado, function ($a, $b) {
            return strcmp($a['codigo'], $b['codigo']);
        });

        return $consolidado;
    }

    public function resumenGrupos(Request $request): Response
    {
        $selectedGrupoId = isset($request->query['id_grupo']) ? (int) $request->query['id_grupo'] : null;

        // Obtener todos los grupos con estadísticas
        $grupos = $this->grupos->getResumenConItems();

        // Datos del grupo seleccionado
        $grupoSeleccionado = null;
        $partesDelGrupo = [];
        $agruparPorParte = true; // Por defecto, agrupar variantes por parte

        if ($selectedGrupoId) {
            // Buscar el grupo seleccionado
            foreach ($grupos as $grupo) {
                if ((int) $grupo['id'] === $selectedGrupoId) {
                    $grupoSeleccionado = $grupo;
                    break;
                }
            }

            // Obtener partes del grupo
            if ($grupoSeleccionado) {
                $items = $this->grupos->getPartesDeGrupo($selectedGrupoId);

                // Agrupar por parte
                foreach ($items as $item) {
                    $parteId = $item['parte_id'];
                    if (!isset($partesDelGrupo[$parteId])) {
                        $partesDelGrupo[$parteId] = [
                            'parte_id' => $item['parte_id'],
                            'parte_codigo' => $item['parte_codigo'],
                            'parte_detalle' => $item['parte_detalle'],
                            'tipo_nombre' => $item['tipo_nombre'],
                            'tipo_codigo' => $item['tipo_codigo'],
                            'variantes' => [],
                        ];
                    }

                    // Agregar variante si existe
                    if ($item['variante_id']) {
                        $partesDelGrupo[$parteId]['variantes'][] = [
                            'variante_id' => $item['variante_id'],
                            'codigo_variante' => $item['codigo_variante'],
                            'variante_detalle' => $item['variante_detalle'],
                            'variante_estado' => $item['variante_estado'],
                            'stock_actual' => $item['stock_actual'],
                            'punto_pedido' => $item['punto_pedido'],
                            'lote_minimo' => $item['lote_minimo'],
                            'unidad_medida' => $item['unidad_medida'],
                            'estado_stock' => $item['estado_stock'] ?? 'normal',
                        ];
                    }
                }
            }
        }

        // Obtener partes sin grupo
        $partesSinGrupo = $this->grupos->getPartesSinGrupo();
        $countSinGrupo = 0;
        $partesSinGrupoAgrupadas = [];

        foreach ($partesSinGrupo as $item) {
            $parteId = $item['parte_id'];
            if (!isset($partesSinGrupoAgrupadas[$parteId])) {
                $partesSinGrupoAgrupadas[$parteId] = [
                    'parte_id' => $item['parte_id'],
                    'parte_codigo' => $item['parte_codigo'],
                    'parte_detalle' => $item['parte_detalle'],
                    'tipo_nombre' => $item['tipo_nombre'],
                    'variantes' => [],
                ];
                $countSinGrupo++;
            }

            if ($item['variante_id']) {
                $partesSinGrupoAgrupadas[$parteId]['variantes'][] = [
                    'codigo_variante' => $item['codigo_variante'],
                    'variante_detalle' => $item['variante_detalle'],
                    'variante_estado' => $item['variante_estado'],
                    'stock_actual' => $item['stock_actual'],
                    'punto_pedido' => $item['punto_pedido'],
                    'unidad_medida' => $item['unidad_medida'],
                ];
            }
        }

        return $this->render('pages/reportes/resumen-grupos', [
            'title' => 'Resumen por Grupos',
            'grupos' => $grupos,
            'selectedGrupoId' => $selectedGrupoId,
            'grupoSeleccionado' => $grupoSeleccionado,
            'partesDelGrupo' => array_values($partesDelGrupo),
            'partesSinGrupo' => array_values($partesSinGrupoAgrupadas),
            'countSinGrupo' => $countSinGrupo,
        ]);
    }
}
