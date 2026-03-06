<?php
?>
<div class="home-landing">
    <section class="home-hero card border-0 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5 home-reveal">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-xl-8">
                    <p class="home-kicker mb-3">MRP para pymes que quieren dejar de apagar incendios</p>
                    <h1 class="home-title mb-3">Deja el papel y Excel para informes: opera producción, compras y stock en un solo flujo</h1>
                    <p class="home-subtitle mb-4">Con este MRP pasas de planillas dispersas a decisiones en tiempo real. Menos errores de carga, menos retrabajos y más control diario de tu negocio.</p>
                    <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
                        <a class="btn btn-primary btn-lg px-4" href="<?= url('login') ?>">Ingresar al MRP</a>
                        <a class="btn btn-outline-secondary btn-lg px-4" href="<?= url('register') ?>">Registrarse</a>
                        <a class="btn btn-outline-dark btn-lg px-4" href="#comparativa" data-scroll>Ver comparativa</a>
                    </div>
                    <div class="home-chips">
                        <span><i class="fa-solid fa-check"></i> Sin fórmulas rotas</span>
                        <span><i class="fa-solid fa-check"></i> Trazabilidad por movimiento</span>
                        <span><i class="fa-solid fa-check"></i> Escalable multiempresa</span>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="home-hero__stats">
                        <p class="text-uppercase small mb-3">Lo que más valoran los equipos</p>
                        <ul class="list-unstyled mb-0">
                            <li><strong>Visibilidad completa</strong> de compras, producción y stock</li>
                            <li><strong>Alertas tempranas</strong> para evitar faltantes críticos</li>
                            <li><strong>Datos unificados</strong> para decidir sin adivinar</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="comparativa" class="mb-4 home-reveal">
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <article class="home-compare home-compare--legacy card border-0 h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><i class="fa-solid fa-file-lines me-2"></i>Papel</h2>
                        <ul class="list-unstyled mb-0">
                            <li>Datos aislados por persona y cuaderno</li>
                            <li>Errores de transcripción frecuentes</li>
                            <li>Sin historial confiable para auditoría</li>
                        </ul>
                    </div>
                </article>
            </div>
            <div class="col-12 col-lg-4">
                <article class="home-compare home-compare--legacy card border-0 h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><i class="fa-solid fa-table me-2"></i>Excel</h2>
                        <ul class="list-unstyled mb-0">
                            <li>Versiones duplicadas y archivos desactualizados</li>
                            <li>Fórmulas frágiles que rompen el proceso</li>
                            <li>Difícil crecer sin equipo administrativo</li>
                        </ul>
                    </div>
                </article>
            </div>
            <div class="col-12 col-lg-4">
                <article class="home-compare home-compare--mrp card border-0 h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><i class="fa-solid fa-rocket me-2"></i>MRP</h2>
                        <ul class="list-unstyled mb-0">
                            <li>Una sola fuente de verdad para toda la operación</li>
                            <li>Automatización de sugerencias y reposición</li>
                            <li>Escenarios por empresa con control centralizado</li>
                        </ul>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="mb-4 home-reveal">
        <div class="row g-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card__icon bg-primary-subtle text-primary"><i class="fa-solid fa-gauge-high"></i></div>
                    <div class="info-card__body">
                        <h2>Control en tiempo real</h2>
                        <p>Estado operativo actualizado para decidir compras y producción con confianza.</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card__icon bg-success-subtle text-success"><i class="fa-solid fa-gears"></i></div>
                    <div class="info-card__body">
                        <h2>Menos tareas manuales</h2>
                        <p>Flujos guiados que reducen la carga administrativa y el retrabajo.</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card__icon bg-warning-subtle text-warning"><i class="fa-solid fa-truck-fast"></i></div>
                    <div class="info-card__body">
                        <h2>Entrega más predecible</h2>
                        <p>Planificación más clara para cumplir fechas y sostener la calidad.</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card__icon bg-danger-subtle text-danger"><i class="fa-solid fa-building"></i></div>
                    <div class="info-card__body">
                        <h2>Preparado para crecer</h2>
                        <p>Arquitectura multiempresa para sumar unidades de negocio sin caos operativo.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-flow card border-0 shadow-sm mb-4 home-reveal">
        <div class="card-body p-4">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-lg-5">
                    <h2 class="h4 mb-3">Cómo cambia tu día de trabajo</h2>
                    <p class="text-muted mb-0">Pasas de buscar datos en varios lugares a trabajar sobre un circuito único que conecta compras, inventario y producción.</p>
                </div>
                <div class="col-12 col-lg-7">
                    <div class="timeline">
                        <div class="timeline__item">
                            <div class="timeline__badge">1</div>
                            <div>
                                <h3>Planificas</h3>
                                <p class="text-muted mb-0">El sistema sugiere necesidades según demanda y stock.</p>
                            </div>
                        </div>
                        <div class="timeline__item">
                            <div class="timeline__badge">2</div>
                            <div>
                                <h3>Compras y recibes</h3>
                                <p class="text-muted mb-0">Cada movimiento queda trazado y disponible para todos.</p>
                            </div>
                        </div>
                        <div class="timeline__item">
                            <div class="timeline__badge">3</div>
                            <div>
                                <h3>Producción ejecuta</h3>
                                <p class="text-muted mb-0">Órdenes claras, insumos correctos y menos interrupciones.</p>
                            </div>
                        </div>
                        <div class="timeline__item">
                            <div class="timeline__badge">4</div>
                            <div>
                                <h3>Dirección decide</h3>
                                <p class="text-muted mb-0">KPIs y reportes listos para actuar rápido.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-cta card border-0 shadow-sm mb-4 home-reveal">
        <div class="card-body p-4 p-lg-5 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <h2 class="h3 mb-2">Tu operación merece más que planillas sueltas</h2>
                <p class="mb-0">Empieza con una base ordenada hoy y crece con procesos claros mañana.</p>
            </div>
            <a class="btn btn-dark btn-lg px-4" href="<?= url('login') ?>">Quiero ver el MRP en acción</a>
        </div>
    </section>
</div>
