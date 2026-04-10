<!--
    Ejemplo de formulario de movimientos de inventario con validación dinámica

    Este formulario demuestra cómo integrar la validación de movimientos entre depósitos.
    Requiere el archivo: /assets/js/depositos-validacion.js
-->

<?php
// En el controlador, pasar los tipos de depósito activos
use App\Models\TipoDeposito;

$tiposDeposito = (new TipoDeposito())->activos();
?>

<!-- Opción 1: Uso automático con data-attribute -->
<form method="post"
    action="/inventario/movimientos"
    data-depositos-validation>

    <div class="row g-3">
        <!-- Tipo de Depósito Origen -->
        <div class="col-md-6">
            <label class="form-label" for="tipo-deposito-origen">
                Tipo de Depósito Origen <span class="text-danger">*</span>
            </label>
            <select class="form-select"
                id="tipo-deposito-origen"
                name="tipo_deposito_origen_id"
                required>
                <option value="">Seleccione origen...</option>
                <?php foreach ($tiposDeposito as $tipo) : ?>
                    <option value="<?= $tipo['id'] ?>">
                        <?= htmlspecialchars($tipo['nombre']) ?> (<?= htmlspecialchars($tipo['codigo']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">
                Seleccione el tipo de depósito de origen
            </small>
        </div>

        <!-- Tipo de Depósito Destino (se carga dinámicamente) -->
        <div class="col-md-6">
            <label class="form-label" for="tipo-deposito-destino">
                Tipo de Depósito Destino <span class="text-danger">*</span>
            </label>
            <select class="form-select"
                id="tipo-deposito-destino"
                name="tipo_deposito_destino_id"
                disabled
                required>
                <option value="">Seleccione origen primero...</option>
            </select>
            <small class="form-text text-muted">
                Solo se mostrarán destinos permitidos
            </small>
        </div>

        <!-- Contenedor para mensajes de error -->
        <div class="col-12">
            <div class="alert-validation d-none"></div>
        </div>

        <!-- Otros campos del formulario -->
        <div class="col-md-6">
            <label class="form-label" for="cantidad">
                Cantidad <span class="text-danger">*</span>
            </label>
            <input type="number"
                class="form-control"
                id="cantidad"
                name="cantidad"
                min="0.01"
                step="0.01"
                required>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="fecha">
                Fecha de Movimiento <span class="text-danger">*</span>
            </label>
            <input type="date"
                class="form-control"
                id="fecha"
                name="fecha_movimiento"
                value="<?= date('Y-m-d') ?>"
                required>
        </div>

        <div class="col-12">
            <label class="form-label" for="observaciones">
                Observaciones
            </label>
            <textarea class="form-control"
                id="observaciones"
                name="observaciones"
                rows="3"></textarea>
        </div>

        <!-- Botones -->
        <div class="col-12">
            <div class="d-flex gap-2 justify-content-end">
                <a href="/inventario/movimientos" class="btn btn-secondary">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-arrow-right-arrow-left me-1"></i>
                    Registrar Movimiento
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Opción 2: Uso programático con configuración personalizada -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Si necesitas más control, puedes inicializar manualmente:
        /*
        const form = document.querySelector('#mi-formulario-movimientos');

        DepositosValidacion.init(form, {
            origenSelector: '[name="tipo_deposito_origen_id"]',
            destinoSelector: '[name="tipo_deposito_destino_id"]',
            emptyText: 'Seleccione un destino...',
            loadingText: 'Cargando opciones...',
            errorText: 'Error al cargar',
            noDestinosText: 'Sin destinos disponibles',
            validateOnSubmit: true,

            // Callbacks personalizados
            onBeforeLoad: function(selectOrigen, selectDestino) {
                console.log('Cargando destinos para:', selectOrigen.value);
            },

            onAfterLoad: function(selectOrigen, selectDestino, destinos) {
                console.log('Destinos cargados:', destinos.length);
            },

            onError: function(error, selectOrigen, selectDestino) {
                console.error('Error:', error);
                alert('No se pudieron cargar los destinos permitidos');
            },

            onValidSubmit: function(form) {
                console.log('Formulario válido, enviando...');
                // Aquí podrías enviar por AJAX en lugar de submit tradicional
                form.submit();
            }
        });
        */
    });
</script>

<!-- Opción 3: Validación manual sin auto-submit -->
<script>
    async function validarYEnviar() {
        const form = document.querySelector('#mi-formulario');
        const origenId = parseInt(document.querySelector('[name="tipo_deposito_origen_id"]').value);
        const destinoId = parseInt(document.querySelector('[name="tipo_deposito_destino_id"]').value);

        if (!origenId || !destinoId) {
            alert('Debe seleccionar origen y destino');
            return;
        }

        try {
            const resultado = await DepositosValidacion.validarMovimiento(origenId, destinoId);

            if (resultado.permitido) {
                // Movimiento permitido, enviar formulario
                form.submit();
            } else {
                // Movimiento no permitido
                alert(resultado.mensaje || 'Este movimiento no está permitido');
            }
        } catch (error) {
            console.error('Error al validar:', error);
            alert('Error al validar el movimiento');
        }
    }
</script>

<!-- Incluir el script de validación -->
<script src="/assets/js/depositos-validacion.js"></script>

<!--
    NOTAS DE IMPLEMENTACIÓN:

    1. El script depositos-validacion.js debe estar incluido en la página

    2. Para uso automático, simplemente agregue el atributo data-depositos-validation al form

    3. Los names de los selects deben ser:
       - tipo_deposito_origen_id
       - tipo_deposito_destino_id
       (O configúrelos con origenSelector y destinoSelector)

    4. Asegúrese de tener un contenedor con clase .alert-validation para mostrar errores

    5. El select de destino debe estar deshabilitado inicialmente (disabled)

    6. Cuando el usuario selecciona un origen, el script:
       - Llama a la API /api/v1/depositos-validaciones/{origenId}/destinos
       - Carga solo los destinos permitidos
       - Habilita el select de destino

    7. Al enviar el formulario:
       - Valida que el movimiento esté permitido
       - Si no lo está, muestra error y previene el envío
       - Si está permitido, envía el formulario normalmente
-->
