/**
 * Búsqueda en tiempo real para Partes y Variantes
 */
(function () {
  'use strict';

  // Inicializar búsqueda para Partes
  function initPartesSearch() {
    const form = document.getElementById('search-form-partes');
    const input = document.getElementById('search-input-partes');
    const clearBtn = document.getElementById('clear-search-partes');

    if (!form || !input) return;

    // Restaurar foco al cargar si hay búsqueda activa
    if (input.dataset.hasSearch === 'true' || input.value.trim().length > 0) {
      input.focus();
      // Mover cursor al final
      const val = input.value;
      input.value = '';
      input.value = val;
    }

    let timeout = null;
    let isAutoSubmitting = false;

    // Búsqueda automática al escribir
    input.addEventListener('input', function () {
      clearTimeout(timeout);
      const value = this.value.trim();

      if (value.length >= 2) {
        timeout = setTimeout(function () {
          isAutoSubmitting = true;
          form.submit();
        }, 1000);
      } else if (value.length === 0 && input.dataset.hasSearch === 'true') {
        window.location.href = form.dataset.clearUrl;
      }
    });

    // Permitir submit con Enter solo si hay 2+ caracteres
    form.addEventListener('submit', function (e) {
      if (!isAutoSubmitting && input.value.trim().length < 2) {
        e.preventDefault();
        return false;
      }
      isAutoSubmitting = false;
    });

    // Botón limpiar
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        window.location.href = form.dataset.clearUrl;
      });
    }
  }

  // Inicializar búsqueda para Variantes
  function initVariantesSearch() {
    const form = document.getElementById('search-form-variantes');
    const input = document.getElementById('search-input-variantes');
    const clearBtn = document.getElementById('clear-search-variantes');

    if (!form || !input) return;

    // Restaurar foco al cargar si hay búsqueda activa
    if (input.dataset.hasSearch === 'true' || input.value.trim().length > 0) {
      input.focus();
      // Mover cursor al final
      const val = input.value;
      input.value = '';
      input.value = val;
    }

    let timeout = null;
    let isAutoSubmitting = false;

    // Búsqueda automática al escribir
    input.addEventListener('input', function () {
      clearTimeout(timeout);
      const value = this.value.trim();

      if (value.length >= 2) {
        timeout = setTimeout(function () {
          isAutoSubmitting = true;
          form.submit();
        }, 1000);
      } else if (value.length === 0 && input.dataset.hasSearch === 'true') {
        window.location.href = form.dataset.clearUrl;
      }
    });

    // Permitir submit con Enter solo si hay 2+ caracteres
    form.addEventListener('submit', function (e) {
      if (!isAutoSubmitting && input.value.trim().length < 2) {
        e.preventDefault();
        return false;
      }
      isAutoSubmitting = false;
    });

    // Botón limpiar
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        window.location.href = form.dataset.clearUrl;
      });
    }
  }

  // Inicializar SearchClient para el formulario de Nueva variante
  function initVariantParteSearch() {
    const searchInput = document.getElementById('variant-parte-search-input');
    const searchResults = document.getElementById('variant-parte-search-results');
    const hiddenInput = document.getElementById('variant-id-parte-hidden');

    if (!searchInput || !searchResults || !hiddenInput) return;

    // Verificar que SearchClient esté disponible
    if (typeof SearchClient === 'undefined') {
      console.error('SearchClient no está disponible');
      return;
    }

    let selectedParteId = null;

    const basePath = (window.MRP_BASE_PATH || '').replace(/\/$/, '');
    const baseUrl = basePath.startsWith('http') ? basePath : window.location.origin + basePath;

    const parteSearchClient = new SearchClient({
      endpoint: baseUrl + '/api/v1/search/partes',
      inputElement: searchInput,
      resultsContainer: searchResults,
      minChars: 2,
      debounceDelay: 300,
      maxResults: 10,
      format: 'standard',
      filters: { activo: true },
      onSelect: (item) => {
        console.log('Parte seleccionada:', item);

        // Actualizar el campo oculto con el ID de la parte
        selectedParteId = item.id;
        hiddenInput.value = item.id;

        // Actualizar el campo de búsqueda con el texto de la parte seleccionada
        searchInput.value = item.display_text || (item.codigo + ' - ' + item.detalle);

        // Cargar las variantes de la parte seleccionada
        loadVariantesByParte(item.id);
      },
      renderItem: (item) => {
        return `
          <div class="d-flex justify-content-between align-items-start w-100 p-2">
            <div class="flex-grow-1">
              <div class="fw-bold text-primary">${item.codigo || 'N/A'}</div>
              <small class="text-secondary">${item.detalle || 'Sin descripción'}</small>
            </div>
            <span class="badge bg-secondary ms-2 align-self-start">${item.tipo_codigo || 'N/A'}</span>
          </div>
        `;
      }
    });

    // Validar que se haya seleccionado una parte antes de enviar el formulario
    const form = searchInput.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        if (!hiddenInput.value) {
          e.preventDefault();
          alert('Debe seleccionar una parte de la lista de resultados');
          searchInput.focus();
          return false;
        }
      });
    }
  }

  // Función para cargar las variantes de una parte específica
  function loadVariantesByParte(parteId) {
    console.log('Cargando variantes de la parte:', parteId);

    // Redirigir a la misma página con el filtro de parte
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('tab', 'variantes');
    currentUrl.searchParams.set('id_parte', parteId);

    window.location.href = currentUrl.toString();
  }

  // Inicializar cuando el DOM esté listo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initPartesSearch();
      initVariantesSearch();
      initVariantParteSearch();
    });
  } else {
    initPartesSearch();
    initVariantesSearch();
    initVariantParteSearch();
  }
})();
