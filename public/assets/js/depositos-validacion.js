/**
 * Módulo de Validación de Movimientos entre Depósitos
 *
 * Este script proporciona funcionalidad para validar y filtrar depósitos
 * según las reglas de movimientos configuradas en el sistema.
 *
 * @version 1.0.0
 * @requires fetch API
 */

const DepositosValidacion = (function () {
  'use strict';

  // Configuración
  const config = {
    apiBaseUrl: '/api/v1/depositos-validaciones',
    loadingClass: 'is-loading',
    disabledClass: 'disabled',
    errorClass: 'is-invalid',
  };

  /**
   * Obtiene los tipos de depósito destino permitidos para un origen
   * @param {number} origenId - ID del tipo de depósito origen
   * @returns {Promise<Array>} Lista de destinos permitidos
   */
  async function getDestinosPermitidos(origenId) {
    if (!origenId || origenId <= 0) {
      throw new Error('ID de origen inválido');
    }

    try {
      const response = await fetch(`${config.apiBaseUrl}/${origenId}/destinos`);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.message || 'Error al obtener destinos');
      }

      return data.destinos || [];
    } catch (error) {
      console.error('Error al obtener destinos permitidos:', error);
      throw error;
    }
  }

  /**
   * Valida si un movimiento está permitido entre dos tipos de depósito
   * @param {number} origenId - ID del tipo de depósito origen
   * @param {number} destinoId - ID del tipo de depósito destino
   * @returns {Promise<Object>} Resultado de la validación
   */
  async function validarMovimiento(origenId, destinoId) {
    if (!origenId || origenId <= 0 || !destinoId || destinoId <= 0) {
      return {
        success: false,
        permitido: false,
        mensaje: 'IDs inválidos'
      };
    }

    try {
      const response = await fetch(`${config.apiBaseUrl}/validar`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          origen_id: origenId,
          destino_id: destinoId
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      return data;
    } catch (error) {
      console.error('Error al validar movimiento:', error);
      throw error;
    }
  }

  /**
   * Actualiza dinámicamente un select de destinos según el origen seleccionado
   * @param {HTMLSelectElement} selectOrigen - Select del depósito origen
   * @param {HTMLSelectElement} selectDestino - Select del depósito destino
   * @param {Object} options - Opciones de configuración
   */
  function setupDynamicDestinos(selectOrigen, selectDestino, options = {}) {
    const settings = {
      emptyText: 'Seleccione destino...',
      loadingText: 'Cargando destinos...',
      errorText: 'Error al cargar destinos',
      noDestinosText: 'No hay destinos permitidos',
      onBeforeLoad: null,
      onAfterLoad: null,
      onError: null,
      ...options
    };

    if (!selectOrigen || !selectDestino) {
      console.error('Los elementos select son requeridos');
      return;
    }

    // Event listener para cambio en el origen
    selectOrigen.addEventListener('change', async function () {
      const origenId = parseInt(this.value, 10);

      // Resetear destino
      selectDestino.innerHTML = `<option value="">${settings.emptyText}</option>`;
      selectDestino.disabled = true;
      selectDestino.classList.remove(config.errorClass);

      if (!origenId || origenId <= 0) {
        return;
      }

      // Callback antes de cargar
      if (typeof settings.onBeforeLoad === 'function') {
        settings.onBeforeLoad(selectOrigen, selectDestino);
      }

      // Mostrar estado de carga
      selectDestino.classList.add(config.loadingClass);
      selectDestino.innerHTML = `<option value="">${settings.loadingText}</option>`;

      try {
        const destinos = await getDestinosPermitidos(origenId);

        // Limpiar select
        selectDestino.innerHTML = '';

        if (destinos.length === 0) {
          selectDestino.innerHTML = `<option value="">${settings.noDestinosText}</option>`;
          selectDestino.disabled = true;
        } else {
          // Agregar opción vacía
          const emptyOption = document.createElement('option');
          emptyOption.value = '';
          emptyOption.textContent = settings.emptyText;
          selectDestino.appendChild(emptyOption);

          // Agregar destinos permitidos
          destinos.forEach(destino => {
            const option = document.createElement('option');
            option.value = destino.id;
            option.textContent = `${destino.nombre} (${destino.codigo})`;

            if (destino.descripcion) {
              option.title = destino.descripcion;
            }

            selectDestino.appendChild(option);
          });

          selectDestino.disabled = false;
        }

        // Callback después de cargar
        if (typeof settings.onAfterLoad === 'function') {
          settings.onAfterLoad(selectOrigen, selectDestino, destinos);
        }

      } catch (error) {
        selectDestino.innerHTML = `<option value="">${settings.errorText}</option>`;
        selectDestino.classList.add(config.errorClass);
        selectDestino.disabled = true;

        // Callback de error
        if (typeof settings.onError === 'function') {
          settings.onError(error, selectOrigen, selectDestino);
        } else {
          console.error('Error al cargar destinos:', error);
        }
      } finally {
        selectDestino.classList.remove(config.loadingClass);
      }
    });
  }

  /**
   * Valida un formulario de movimiento antes de enviarlo
   * @param {HTMLFormElement} form - Formulario a validar
   * @param {Object} selectors - Selectores de los campos
   * @returns {Promise<boolean>} true si la validación es exitosa
   */
  async function validateForm(form, selectors = {}) {
    const settings = {
      origenSelector: '[name="tipo_deposito_origen_id"]',
      destinoSelector: '[name="tipo_deposito_destino_id"]',
      errorContainerSelector: '.alert-validation',
      ...selectors
    };

    const selectOrigen = form.querySelector(settings.origenSelector);
    const selectDestino = form.querySelector(settings.destinoSelector);
    const errorContainer = form.querySelector(settings.errorContainerSelector);

    if (!selectOrigen || !selectDestino) {
      console.error('No se encontraron los campos de origen y destino');
      return false;
    }

    const origenId = parseInt(selectOrigen.value, 10);
    const destinoId = parseInt(selectDestino.value, 10);

    if (!origenId || !destinoId) {
      showError(errorContainer, 'Debe seleccionar origen y destino');
      return false;
    }

    try {
      const result = await validarMovimiento(origenId, destinoId);

      if (!result.permitido) {
        showError(
          errorContainer,
          result.mensaje || 'Este movimiento no está permitido según la configuración del sistema'
        );
        return false;
      }

      hideError(errorContainer);
      return true;

    } catch (error) {
      showError(errorContainer, 'Error al validar el movimiento. Intente nuevamente.');
      return false;
    }
  }

  /**
   * Muestra un mensaje de error
   * @param {HTMLElement} container - Contenedor del mensaje
   * @param {string} message - Mensaje a mostrar
   */
  function showError(container, message) {
    if (!container) return;

    container.innerHTML = `
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            ${message}
        `;
    container.classList.remove('d-none');
    container.classList.add('alert', 'alert-danger');
  }

  /**
   * Oculta el mensaje de error
   * @param {HTMLElement} container - Contenedor del mensaje
   */
  function hideError(container) {
    if (!container) return;
    container.classList.add('d-none');
    container.innerHTML = '';
  }

  /**
   * Inicializa la validación en un formulario completo
   * @param {string|HTMLFormElement} formSelector - Selector o elemento del formulario
   * @param {Object} options - Opciones de configuración
   */
  function init(formSelector, options = {}) {
    const form = typeof formSelector === 'string'
      ? document.querySelector(formSelector)
      : formSelector;

    if (!form) {
      console.error('Formulario no encontrado:', formSelector);
      return;
    }

    const settings = {
      origenSelector: '[name="tipo_deposito_origen_id"]',
      destinoSelector: '[name="tipo_deposito_destino_id"]',
      validateOnSubmit: true,
      ...options
    };

    const selectOrigen = form.querySelector(settings.origenSelector);
    const selectDestino = form.querySelector(settings.destinoSelector);

    if (!selectOrigen || !selectDestino) {
      console.error('No se encontraron los campos de origen y destino en el formulario');
      return;
    }

    // Configurar carga dinámica de destinos
    setupDynamicDestinos(selectOrigen, selectDestino, settings);

    // Validar en submit si está habilitado
    if (settings.validateOnSubmit) {
      form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const isValid = await validateForm(form, settings);

        if (isValid) {
          // Si la validación es exitosa, enviar el formulario
          if (typeof settings.onValidSubmit === 'function') {
            settings.onValidSubmit(form);
          } else {
            form.submit();
          }
        }
      });
    }
  }

  // API pública
  return {
    getDestinosPermitidos,
    validarMovimiento,
    setupDynamicDestinos,
    validateForm,
    init
  };
})();

// Uso automático si se encuentra el atributo data-depositos-validation
document.addEventListener('DOMContentLoaded', function () {
  const forms = document.querySelectorAll('[data-depositos-validation]');

  forms.forEach(form => {
    DepositosValidacion.init(form);
  });
});
