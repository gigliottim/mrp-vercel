<?php
?>
<div class="home-landing">
    <!-- Hero Section -->
    <header class="py-5 bg-light rounded-4 mb-5 border shadow-sm position-relative overflow-hidden">
        <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary opacity-10" style="background: linear-gradient(135deg, rgba(13,110,253,0.1) 0%, rgba(255,255,255,0) 100%);"></div>
        <div class="container px-5 pb-5">
            <div class="row align-items-center justify-content-center text-center text-lg-start">
                <div class="col-lg-8 col-xl-7 col-xxl-6">
                    <div class="my-5 position-relative z-1">
                        <div class="badge bg-primary bg-gradient rounded-pill mb-3 px-3 py-2 text-white">
                            🚀 La evolución de tu gestión industrial
                        </div>
                        <h1 class="display-4 fw-bolder mb-3 text-dark">El MRP diseñado para PyMEs en crecimiento</h1>
                        <p class="lead fw-normal text-muted mb-4 text-break">
                            Despídete de los correos cruzados y planillas de cálculo interminables. Controla tu inventario, abastecimiento y producción en una única plataforma colaborativa. Adelántate a los problemas y enfocate en crecer.
                        </p>
                        <div class="d-grid gap-3 d-sm-flex justify-content-sm-center justify-content-lg-start">
                            <a class="btn btn-primary btn-lg px-4 me-sm-3 fw-medium shadow-sm" href="<?= url('register') ?>">Comenzar Gratis</a>
                            <a class="btn btn-outline-dark btn-lg px-4 fw-medium" href="<?= url('login') ?>">Iniciar Sesión</a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 col-xxl-6 d-none d-xl-block text-center position-relative z-1">
                    <img class="img-fluid rounded-3 my-5 shadow-lg border" src="<?= url('assets/img/hero-mockup.webp') ?>" alt="MRP Dashboard" onerror="this.src='https://dummyimage.com/600x400/ced4da/6c757d.png&text=Dashboard+Preview'" />
                </div>
            </div>
        </div>
    </header>

    <!-- Problema vs Solución -->
    <section class="py-5" id="features">
        <div class="container px-5 my-5">
            <div class="row gx-5">
                <div class="col-lg-4 mb-5 mb-lg-0">
                    <h2 class="fw-bolder mb-3">La diferencia es clara</h2>
                    <p class="text-muted mb-4">Mantener el control con herramientas obsoletas te hace perder tiempo y dinero. Unifica tu información y toma decisiones informadas en tiempo real.</p>
                    <a class="btn btn-link px-0 text-decoration-none text-primary fw-medium" href="#beneficios">Conoce los beneficios <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="col-lg-8">
                    <div class="row gx-5 row-cols-1 row-cols-md-2">
                        <div class="col mb-5 h-100">
                            <div class="feature bg-danger bg-gradient text-white rounded-3 mb-3 p-3 d-inline-block shadow"><i class="fa-solid fa-file-excel fs-4"></i></div>
                            <h2 class="h5 fw-bold">El caos del Excel</h2>
                            <p class="text-muted mb-0">Fórmulas que se rompen, múltiples versiones del mismo archivo, control de stock desactualizado y errores de carga manual.</p>
                        </div>
                        <div class="col mb-5 h-100">
                            <div class="feature bg-success bg-gradient text-white rounded-3 mb-3 p-3 d-inline-block shadow"><i class="fa-solid fa-check-double fs-4"></i></div>
                            <h2 class="h5 fw-bold">El orden del MRP</h2>
                            <p class="text-muted mb-0">Datos centralizados, historial de movimientos preciso, trazabilidad end-to-end y sugerencias de compra automáticas.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Cards -->
    <section class="py-5 bg-light rounded-4 mb-5 shadow-sm" id="beneficios">
        <div class="container px-5 my-5">
            <div class="text-center mb-5">
                <h2 class="fw-bolder">Todo lo que necesitas para operar</h2>
                <p class="lead text-muted mb-0">Diseñado específicamente para las necesidades de la industria manufacturera.</p>
            </div>
            <div class="row gx-5 row-cols-1 row-cols-sm-2 row-cols-xl-4 justify-content-center">
                <div class="col mb-5">
                    <div class="card h-100 shadow-sm border-0 bg-white">
                        <div class="card-body p-4 text-center">
                            <div class="text-primary mb-3">
                                <i class="fa-solid fa-cubes-stacked fs-1"></i>
                            </div>
                            <h5 class="fw-bold">Gestión de Stock</h5>
                            <p class="text-muted small">Control riguroso de depósitos, inventario, reservas y trazabilidad total por movimientos.</p>
                        </div>
                    </div>
                </div>
                <div class="col mb-5">
                    <div class="card h-100 shadow-sm border-0 bg-white">
                        <div class="card-body p-4 text-center">
                            <div class="text-success mb-3">
                                <i class="fa-solid fa-industry fs-1"></i>
                            </div>
                            <h5 class="fw-bold">Producción</h5>
                            <p class="text-muted small">Crea recetas (BOMs), emite órdenes de producción y registra mermas de forma estructurada.</p>
                        </div>
                    </div>
                </div>
                <div class="col mb-5">
                    <div class="card h-100 shadow-sm border-0 bg-white">
                        <div class="card-body p-4 text-center">
                            <div class="text-warning mb-3">
                                <i class="fa-solid fa-truck-loading fs-1"></i>
                            </div>
                            <h5 class="fw-bold">Abastecimiento</h5>
                            <p class="text-muted small">Alertas de stock mínimo, gestión de proveedores y órdenes de compra integradas.</p>
                        </div>
                    </div>
                </div>
                <div class="col mb-5">
                    <div class="card h-100 shadow-sm border-0 bg-white">
                        <div class="card-body p-4 text-center">
                            <div class="text-info mb-3">
                                <i class="fa-solid fa-network-wired fs-1"></i>
                            </div>
                            <h5 class="fw-bold">Multiempresa</h5>
                            <p class="text-muted small">Administra varias unidades de negocio o sucursales de producción desde un único acceso.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cómo Funciona (Timeline) -->
    <section class="py-5 mb-5">
        <div class="container px-5">
            <div class="card border-0 shadow-lg overflow-hidden rounded-4">
                <div class="row g-0">
                    <div class="col-lg-5 col-xl-4 bg-dark text-white p-5 d-flex flex-column justify-content-center">
                        <h2 class="fw-bolder mb-3 text-white">Flujo de Trabajo</h2>
                        <p class="text-white-50 mb-0">Un circuito inteligente y unificado que organiza a todos los equipos, desde compras hasta despacho.</p>
                    </div>
                    <div class="col-lg-7 col-xl-8 p-5 bg-white">
                        <div class="d-flex mb-4 align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">1</div>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Detección de Necesidades</h5>
                                <p class="text-muted mb-0 small">El MRP analiza el stock disponible y comprometido para sugerir compras o fabricación.</p>
                            </div>
                        </div>
                        <div class="d-flex mb-4 align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">2</div>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Abastecimiento y Recepción</h5>
                                <p class="text-muted mb-0 small">Generas las órdenes de compra. Al recibir la mercadería, el stock se actualiza al instante.</p>
                            </div>
                        </div>
                        <div class="d-flex mb-4 align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">3</div>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Ejecución de Órdenes</h5>
                                <p class="text-muted mb-0 small">Planta recibe instrucciones claras. Consumen materiales y reportan los productos terminados.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">4</div>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1">Mejora Continua</h5>
                                <p class="text-muted mb-0 small">Dirección analiza costos, eficiencias y mermas a lo largo de todo el proceso productivo.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Menú Completo / Módulos -->
    <section class="py-5 bg-light mb-5 rounded-4 shadow-sm" id="modulos">
        <div class="container px-5 my-5">
            <div class="text-center mb-5">
                <h2 class="fw-bolder">Explora todos nuestros Módulos</h2>
                <p class="lead text-muted mb-0">Una estructura completa y organizada para cada área de tu empresa.</p>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">

                <!-- Panel -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-primary text-white fw-bold">
                            <i class="fa-solid fa-gauge me-2"></i>Panel
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Panel inicial:</strong> Resumen general, alertas y métricas principales del sistema.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Productos y BOM -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-success text-white fw-bold">
                            <i class="fa-solid fa-box-open me-2"></i>Productos y BOM
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Listado de Partes:</strong> Catálogo completo de materias primas, insumos y productos terminados.</li>
                                <li class="mb-2"><strong>Gestor de partes:</strong> Interfaz avanzada para la creación y edición masiva de artículos.</li>
                                <li class="mb-2"><strong>BOM activas:</strong> Listas de materiales (recetas) vigentes para la producción.</li>
                                <li class="mb-2"><strong>Composicion de variantes:</strong> Gestión de configuraciones y características adicionales de los productos.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Planeamiento MRP -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-info text-white fw-bold">
                            <i class="fa-solid fa-brain me-2"></i>Planeamiento MRP
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Sugerencias MRP:</strong> Recomendaciones automáticas sobre qué comprar o fabricar basadas en el stock y la demanda.</li>
                                <li class="mb-2"><strong>Ordenes planificadas:</strong> Proyecciones y planificación a futuro de las órdenes a ejecutar.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Produccion -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark fw-bold">
                            <i class="fa-solid fa-industry me-2"></i>Producción
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Dashboard de Operaciones:</strong> Vista gerencial del estado de la fábrica en tiempo real.</li>
                                <li class="mb-2"><strong>Centros de Trabajo:</strong> Definición de máquinas, líneas o áreas operativas.</li>
                                <li class="mb-2"><strong>Rutas de Produccion:</strong> Secuencia de operaciones y tiempos estándar requeridos.</li>
                                <li class="mb-2"><strong>Ordenes de Produccion:</strong> Órdenes de trabajo activas en planta con seguimiento de avance.</li>
                                <li class="mb-2"><strong>Planificacion de Recursos:</strong> Asignación de capacidad y cargas de trabajo.</li>
                                <li class="mb-2"><strong>Vista Gantt:</strong> Cronograma interactivo y visual de ejecución de órdenes.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Transacciones -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-danger text-white fw-bold">
                            <i class="fa-solid fa-exchange-alt me-2"></i>Transacciones
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Movimientos de Partes:</strong> Registro histórico de entradas, salidas, ajustes y transferencias.</li>
                                <li class="mb-2"><strong>Gestion de Compras:</strong> Administración del flujo de abastecimiento y órdenes a proveedores.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Inventario y stock -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-secondary text-white fw-bold">
                            <i class="fa-solid fa-cubes me-2"></i>Inventario y stock
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Stock critico:</strong> Monitoreo de seguridad y artículos con necesidad de reabastecimiento urgente.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Reportes -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header" style="background-color: #6c757d; color: white; font-weight: bold;">
                            <i class="fa-solid fa-chart-bar me-2"></i>Reportes
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Destino de Partes:</strong> Análisis del uso y consumo de componentes.</li>
                                <li class="mb-2"><strong>Listado de Ingenieria:</strong> Documentación y reportes técnicos de productos.</li>
                                <li class="mb-2"><strong>Planificacion de Produccion:</strong> Resúmenes consolidados de la actividad de la planta.</li>
                                <li class="mb-2"><strong>Resumen por grupos:</strong> Análisis de movimientos agrupados por familias de artículos.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Parámetros y catálogos -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header" style="background-color: #343a40; color: white; font-weight: bold;">
                            <i class="fa-solid fa-cogs me-2"></i>Parámetros y catálogos
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Configuracion:</strong> Ajustes generales y preferencias del entorno.</li>
                                <li class="mb-2"><strong>Unidades de medida:</strong> Definición de magnitudes (kg, un, lt) locales.</li>
                                <li class="mb-2"><strong>Tipos de partes:</strong> Clasificación operativa (Materia prima, Semielaborado, etc.).</li>
                                <li class="mb-2"><strong>Tipos de deposito:</strong> Categorización y configuración de los almacenes.</li>
                                <li class="mb-2"><strong>Validaciones de movimientos:</strong> Reglas operativas para asegurar la consistencia.</li>
                                <li class="mb-2"><strong>Grupos de partes:</strong> Agrupación para facilitar la búsqueda y contabilidad.</li>
                                <li class="mb-2"><strong>Clientes y proveedores:</strong> Directorio de entidades comerciales asociadas.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Empresa y Usuarios -->
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header" style="background-color: #495057; color: white; font-weight: bold;">
                            <i class="fa-solid fa-users me-2"></i>Empresa y Usuarios
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-2"><strong>Empresa:</strong> Datos institucionales y fiscales de la compañía.</li>
                                <li class="mb-2"><strong>Usuarios:</strong> Administración de acceso de los colaboradores.</li>
                                <li class="mb-2"><strong>Roles:</strong> Declaración de perfiles de seguridad en el sistema.</li>
                                <li class="mb-2"><strong>Permisos:</strong> Asignación granulada de acceso por módulo y acción.</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <aside class="bg-primary bg-gradient rounded-4 shadow-lg mb-4">
        <div class="container px-5 py-5 text-center">
            <h2 class="text-white display-6 fw-bold mb-3">Da el salto digital en tu fábrica</h2>
            <p class="text-white-50 mb-4 fs-5">Configuración ágil, diseño moderno y 100% cloud. Olvídate de los servidores locales.</p>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <a class="btn btn-light btn-lg px-5 fw-bold d-flex align-items-center" href="<?= url('register') ?>"><i class="fa-solid fa-play me-2"></i> Crear cuenta empresa</a>
            </div>
        </div>
    </aside>
</div>
