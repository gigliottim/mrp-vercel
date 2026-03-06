<?php

/**
 * Vista: Gantt de Planificación
 */
?>

<div class="container-fluid py-4" x-data="ganttPlanificacion()">
    <!-- Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Gantt de Planificación</h5>
                <div>
                    <a href="<?= url('produccion/planificacion') ?>" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Vista Lista
                    </a>
                </div>
            </div>

            <!-- Controles -->
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" x-model="centroId" @change="cargarDatos">
                        <option value="">Todos los centros</option>
                        <template x-for="centro in centros" :key="centro.id">
                            <option :value="centro.id" x-text="centro.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" x-model="fechaInicio" @change="cargarDatos">
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" x-model="fechaFin" @change="cargarDatos">
                </div>
                <div class="col-md-3">
                    <button @click="exportarPDF" class="btn btn-outline-primary">
                        <i class="fas fa-download"></i> Exportar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Vista Gantt -->
    <div class="card">
        <div class="card-body">
            <div id="gantt-container" style="min-height: 500px; overflow-x: auto;">
                <!-- El gráfico Gantt se renderizará aquí con JavaScript -->
                <template x-if="loading">
                    <div class="text-center py-5">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2">Cargando planificación...</p>
                    </div>
                </template>

                <template x-if="!loading && ganttData.length === 0">
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-chart-gantt fa-3x mb-3"></i>
                        <p>No hay datos para mostrar en el período seleccionado</p>
                    </div>
                </template>

                <template x-if="!loading && ganttData.length > 0">
                    <div>
                        <!-- Renderizar con biblioteca externa como dhtmlxGantt o similar -->
                        <!-- Por ahora, una vista simplificada -->
                        <div class="gantt-simplified">
                            <template x-for="item in ganttData" :key="item.id">
                                <div class="gantt-row mb-2">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <strong x-text="item.centro_nombre"></strong>
                                            <br>
                                            <small class="text-muted" x-text="item.orden_numero"></small>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="gantt-bar-container position-relative">
                                                <div class="gantt-bar bg-primary"
                                                    :style="'left: ' + calcularPosicion(item.fecha_inicio) + '%; width: ' + calcularAncho(item.fecha_inicio, item.fecha_fin) + '%;'">
                                                    <small class="text-white px-2" x-text="item.operacion_nombre"></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Leyenda -->
    <div class="card mt-3">
        <div class="card-body">
            <h6>Leyenda</h6>
            <div class="row">
                <div class="col-auto">
                    <span class="badge bg-info">Programado</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-success">Confirmado</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-primary">En Proceso</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-success">Completado</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-danger">Cancelado</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function ganttPlanificacion() {
        return {
            centroId: '',
            fechaInicio: '<?= date('Y-m-d') ?>',
            fechaFin: '<?= date('Y-m-d', strtotime('+30 days')) ?>',
            centros: [],
            ganttData: [],
            loading: false,

            async init() {
                await this.cargarCentros();
                await this.cargarDatos();
            },

            async cargarCentros() {
                try {
                    const response = await fetch('/produccion/centros-trabajo/json');
                    this.centros = await response.json();
                } catch (error) {
                    console.error('Error:', error);
                }
            },

            async cargarDatos() {
                this.loading = true;
                try {
                    const params = new URLSearchParams({
                        centro_id: this.centroId,
                        fecha_inicio: this.fechaInicio,
                        fecha_fin: this.fechaFin
                    });

                    const response = await fetch(`/produccion/planificacion/gantt-data?${params}`);
                    this.ganttData = await response.json();
                } catch (error) {
                    console.error('Error:', error);
                } finally {
                    this.loading = false;
                }
            },

            calcularPosicion(fechaInicio) {
                const inicio = new Date(this.fechaInicio);
                const fecha = new Date(fechaInicio);
                const fin = new Date(this.fechaFin);
                const totalDias = (fin - inicio) / (1000 * 60 * 60 * 24);
                const diasDesdeInicio = (fecha - inicio) / (1000 * 60 * 60 * 24);
                return (diasDesdeInicio / totalDias) * 100;
            },

            calcularAncho(fechaInicio, fechaFin) {
                const inicio = new Date(this.fechaInicio);
                const fin = new Date(this.fechaFin);
                const totalDias = (fin - inicio) / (1000 * 60 * 60 * 24);
                const duracion = (new Date(fechaFin) - new Date(fechaInicio)) / (1000 * 60 * 60 * 24);
                return (duracion / totalDias) * 100;
            },

            exportarPDF() {
                window.print();
            }
        };
    }
</script>

<style>
    .gantt-bar-container {
        height: 40px;
        background: #f8f9fa;
        border-radius: 5px;
    }

    .gantt-bar {
        position: absolute;
        height: 100%;
        border-radius: 5px;
        display: flex;
        align-items: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .gantt-bar:hover {
        opacity: 0.8;
        transform: translateY(-2px);
    }

    .gantt-row {
        padding: 10px;
        border-bottom: 1px solid #dee2e6;
    }
</style>
