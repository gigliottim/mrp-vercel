<?php

/**
 * Vista: Editor Visual de Rutas de Producción
 * Interface drag & drop para diseñar secuencias de operaciones
 */
?>

<div class="container-fluid py-4" x-data="rutaEditor(<?= $bom_id ?>)">
    <!-- Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Editor de Ruta: <?= esc($producto_nombre ?? 'Producto') ?></h5>
                    <p class="text-muted small mb-0">BOM ID: <?= $bom_id ?></p>
                </div>
                <div>
                    <button @click="guardarRuta" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="<?= url('produccion/rutas') ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cerrar
                    </a>
                </div>
            </div>

            <!-- Resumen -->
            <div class="row text-center mt-3">
                <div class="col-md-3">
                    <div class="border rounded p-2">
                        <h5 class="mb-0" x-text="operaciones.length"></h5>
                        <small class="text-muted">Operaciones</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-2">
                        <h5 class="mb-0" x-text="tiempoTotal.toFixed(0)"></h5>
                        <small class="text-muted">Minutos totales</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-2">
                        <h5 class="mb-0" x-text="'$' + costoTotal.toFixed(2)"></h5>
                        <small class="text-muted">Costo total</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-2">
                        <h5 class="mb-0" x-text="centrosUnicos"></h5>
                        <small class="text-muted">Centros usados</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Panel de Operaciones -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="mb-0">Secuencia de Operaciones</h6>
                    <button @click="agregarOperacion" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body">
                    <!-- Lista de operaciones -->
                    <div class="operaciones-list" style="min-height: 300px;">
                        <template x-if="operaciones.length === 0">
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-route fa-3x mb-3"></i>
                                <p>No hay operaciones configuradas</p>
                                <button @click="agregarOperacion" class="btn btn-primary">
                                    Agregar primera operación
                                </button>
                            </div>
                        </template>

                        <template x-for="(op, index) in operaciones" :key="op.id">
                            <div class="card mb-3 operacion-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <!-- Secuencia -->
                                        <div class="col-auto">
                                            <div class="badge bg-primary" style="font-size: 1.2em; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                <span x-text="op.secuencia"></span>
                                            </div>
                                        </div>

                                        <!-- Datos -->
                                        <div class="col">
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control form-control-sm"
                                                        x-model="op.nombre" placeholder="Nombre operación">
                                                </div>
                                                <div class="col-md-6">
                                                    <select class="form-select form-select-sm" x-model="op.centro_trabajo_id">
                                                        <option value="">Seleccione centro...</option>
                                                        <template x-for="centro in centrosTrabajo" :key="centro.id">
                                                            <option :value="centro.id" x-text="centro.nombre"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="number" class="form-control form-control-sm"
                                                        x-model.number="op.tiempo_setup" placeholder="Setup (min)" step="0.1">
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="number" class="form-control form-control-sm"
                                                        x-model.number="op.tiempo_operacion" placeholder="Operación (min)" step="0.1">
                                                </div>
                                                <div class="col-md-6">
                                                    <textarea class="form-control form-control-sm"
                                                        x-model="op.descripcion" placeholder="Descripción" rows="1"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Acciones -->
                                        <div class="col-auto">
                                            <div class="btn-group-vertical">
                                                <button @click="moverOperacion(index, -1)"
                                                    :disabled="index === 0"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-chevron-up"></i>
                                                </button>
                                                <button @click="moverOperacion(index, 1)"
                                                    :disabled="index === operaciones.length - 1"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-chevron-down"></i>
                                                </button>
                                            </div>
                                            <button @click="eliminarOperacion(index)" class="btn btn-sm btn-danger ms-2">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Resumen de tiempos -->
                                    <div class="mt-2 pt-2 border-top">
                                        <small class="text-muted">
                                            Tiempo total: <strong x-text="(op.tiempo_setup + op.tiempo_operacion).toFixed(1)"></strong> min
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Lateral -->
        <div class="col-lg-4">
            <!-- Centros de Trabajo -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Centros de Trabajo</h6>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <template x-for="centro in centrosTrabajo" :key="centro.id">
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                            <div>
                                <strong x-text="centro.nombre"></strong>
                                <br>
                                <small class="text-muted">
                                    <span x-text="centro.tipo"></span> - $<span x-text="centro.costo_hora"></span>/h
                                </small>
                            </div>
                            <span class="badge bg-success" x-text="(centro.eficiencia * 100).toFixed(0) + '%'"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Validaciones -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Validaciones</h6>
                </div>
                <div class="card-body">
                    <template x-if="errores.length === 0">
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i> Ruta válida
                        </div>
                    </template>
                    <template x-if="errores.length > 0">
                        <div class="alert alert-danger mb-0">
                            <strong>Errores encontrados:</strong>
                            <ul class="mb-0 mt-2">
                                <template x-for="error in errores" :key="error">
                                    <li x-text="error"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function rutaEditor(bomId) {
        return {
            bomId: bomId,
            operaciones: [],
            centrosTrabajo: [],
            errores: [],

            async init() {
                await this.cargarCentrosTrabajo();
                await this.cargarRuta();
            },

            async cargarCentrosTrabajo() {
                try {
                    const response = await fetch('/produccion/centros-trabajo/json');
                    this.centrosTrabajo = await response.json();
                } catch (error) {
                    console.error('Error cargando centros:', error);
                }
            },

            async cargarRuta() {
                try {
                    const response = await fetch(`/produccion/rutas/${this.bomId}`);
                    const data = await response.json();
                    this.operaciones = data.operaciones || [];
                    this.recalcularSecuencias();
                } catch (error) {
                    console.error('Error cargando ruta:', error);
                }
            },

            agregarOperacion() {
                this.operaciones.push({
                    id: Date.now(),
                    nombre: '',
                    centro_trabajo_id: '',
                    tiempo_setup: 0,
                    tiempo_operacion: 0,
                    descripcion: '',
                    secuencia: this.operaciones.length + 1
                });
            },

            eliminarOperacion(index) {
                if (confirm('¿Eliminar esta operación?')) {
                    this.operaciones.splice(index, 1);
                    this.recalcularSecuencias();
                }
            },

            moverOperacion(index, direccion) {
                const newIndex = index + direccion;
                if (newIndex >= 0 && newIndex < this.operaciones.length) {
                    [this.operaciones[index], this.operaciones[newIndex]] = [this.operaciones[newIndex], this.operaciones[index]];
                    this.recalcularSecuencias();
                }
            },

            recalcularSecuencias() {
                this.operaciones.forEach((op, i) => {
                    op.secuencia = i + 1;
                });
            },

            async guardarRuta() {
                if (!this.validar()) {
                    alert('Por favor corrija los errores antes de guardar');
                    return;
                }

                try {
                    const response = await fetch(`/produccion/rutas/${this.bomId}/guardar`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            operaciones: this.operaciones
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Ruta guardada exitosamente');
                    } else {
                        alert('Error: ' + (result.errors || []).join(', '));
                    }
                } catch (error) {
                    console.error('Error guardando:', error);
                    alert('Error al guardar la ruta');
                }
            },

            validar() {
                this.errores = [];

                if (this.operaciones.length === 0) {
                    this.errores.push('Debe agregar al menos una operación');
                }

                this.operaciones.forEach((op, i) => {
                    if (!op.nombre) {
                        this.errores.push(`Operación ${i + 1}: falta nombre`);
                    }
                    if (!op.centro_trabajo_id) {
                        this.errores.push(`Operación ${i + 1}: falta centro de trabajo`);
                    }
                });

                return this.errores.length === 0;
            },

            get tiempoTotal() {
                return this.operaciones.reduce((sum, op) =>
                    sum + (op.tiempo_setup || 0) + (op.tiempo_operacion || 0), 0);
            },

            get costoTotal() {
                return this.operaciones.reduce((sum, op) => {
                    const centro = this.centrosTrabajo.find(c => c.id == op.centro_trabajo_id);
                    if (!centro) return sum;
                    const tiempoHoras = ((op.tiempo_setup || 0) + (op.tiempo_operacion || 0)) / 60;
                    return sum + (tiempoHoras * centro.costo_hora);
                }, 0);
            },

            get centrosUnicos() {
                const unicos = new Set(this.operaciones.map(op => op.centro_trabajo_id).filter(Boolean));
                return unicos.size;
            }
        };
    }
</script>

<style>
    .operacion-card {
        transition: all 0.2s;
    }

    .operacion-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
</style>
