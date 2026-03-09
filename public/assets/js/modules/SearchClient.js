/**
 * SearchClient Module
 *
 * Módulo JavaScript reutilizable para búsquedas asíncronas.
 * Sigue principios SOLID: Single Responsibility, Open/Closed.
 *
 * @version 1.0.0
 * @author MRP System
 */

class SearchClient {
  /**
   * @param {Object} config Configuración del cliente
   * @param {string} config.endpoint URL del endpoint de búsqueda
   * @param {HTMLElement} config.inputElement Input de búsqueda
   * @param {HTMLElement} config.resultsContainer Contenedor de resultados
   * @param {HTMLElement} config.hiddenInput Input oculto para el valor seleccionado
   * @param {number} [config.minChars=2] Mínimo de caracteres para buscar
   * @param {number} [config.debounceDelay=300] Delay en ms para debounce
   * @param {number} [config.maxResults=10] Máximo de resultados a mostrar
   * @param {Function} [config.onSelect] Callback al seleccionar item
   * @param {Function} [config.renderItem] Función custom para renderizar items
   * @param {Object} [config.filters={}] Filtros adicionales
   * @param {string} [config.format='standard'] Formato de datos (standard/compact/detailed)
   */
  constructor(config) {
    // Validar configuración requerida
    this.validateConfig(config);

    // Configuración
    this.endpoint = config.endpoint;
    this.inputElement = config.inputElement;
    this.resultsContainer = config.resultsContainer;
    this.hiddenInput = config.hiddenInput;
    this.minChars = config.minChars ?? 2;
    this.debounceDelay = config.debounceDelay ?? 300;
    this.maxResults = config.maxResults ?? 10;
    this.onSelect = config.onSelect ?? null;
    this.renderItem = config.renderItem ?? this.defaultRenderItem.bind(this);
    this.filters = config.filters ?? {};
    this.format = config.format ?? 'standard';

    // Estado interno
    this.debounceTimer = null;
    this.currentResults = [];
    this.isVisible = false;
    this.selectedIndex = -1;

    // Inicializar
    this.init();
  }

  /**
   * Valida la configuración requerida
   */
  validateConfig(config) {
    const required = ['endpoint', 'inputElement', 'resultsContainer'];
    for (const key of required) {
      if (!config[key]) {
        throw new Error(`SearchClient: "${key}" es requerido en la configuración`);
      }
    }

    if (!(config.inputElement instanceof HTMLElement)) {
      throw new Error('SearchClient: inputElement debe ser un HTMLElement');
    }

    if (!(config.resultsContainer instanceof HTMLElement)) {
      throw new Error('SearchClient: resultsContainer debe ser un HTMLElement');
    }
  }

  /**
   * Inicializa event listeners
   */
  init() {
    // Input events
    this.inputElement.addEventListener('input', this.handleInput.bind(this));
    this.inputElement.addEventListener('keydown', this.handleKeydown.bind(this));
    this.inputElement.addEventListener('focus', this.handleFocus.bind(this));

    // Click outside to close
    document.addEventListener('click', this.handleClickOutside.bind(this));

    // Estilos por defecto del container
    this.applyDefaultStyles();
  }

  /**
   * Aplica estilos por defecto al contenedor de resultados
   */
  applyDefaultStyles() {
    Object.assign(this.resultsContainer.style, {
      position: 'absolute',
      zIndex: '1050',
      maxHeight: '300px',
      overflowY: 'auto',
      display: 'none',
      width: this.inputElement.offsetWidth + 'px'
    });

    this.syncResultsWidth();
  }

  /**
   * Sincroniza el ancho del dropdown con el input visible.
   * Evita anchos de 0px cuando el input se inicializa oculto.
   */
  syncResultsWidth() {
    const inputRect = this.inputElement.getBoundingClientRect();
    let width = Math.round(inputRect.width);

    if (width <= 0 && this.inputElement.parentElement) {
      width = Math.round(this.inputElement.parentElement.getBoundingClientRect().width);
    }

    if (width > 0) {
      this.resultsContainer.style.width = `${width}px`;
    }
  }

  /**
   * Maneja el evento de input
   */
  handleInput(event) {
    const query = event.target.value.trim();

    // Limpiar timer anterior
    clearTimeout(this.debounceTimer);

    // Si es muy corto, ocultar resultados
    if (query.length < this.minChars) {
      this.hide();
      return;
    }

    // Debounce
    this.debounceTimer = setTimeout(() => {
      this.search(query);
    }, this.debounceDelay);
  }

  /**
   * Maneja eventos de teclado (navegación con flechas, Enter, Escape)
   */
  handleKeydown(event) {
    if (!this.isVisible || this.currentResults.length === 0) return;

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        this.selectedIndex = Math.min(
          this.selectedIndex + 1,
          this.currentResults.length - 1
        );
        this.highlightItem(this.selectedIndex);
        break;

      case 'ArrowUp':
        event.preventDefault();
        this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
        this.highlightItem(this.selectedIndex);
        break;

      case 'Enter':
        event.preventDefault();
        if (this.selectedIndex >= 0) {
          this.selectItem(this.currentResults[this.selectedIndex]);
        }
        break;

      case 'Escape':
        event.preventDefault();
        this.hide();
        break;
    }
  }

  /**
   * Maneja el evento de focus
   */
  handleFocus() {
    if (this.currentResults.length > 0) {
      this.show();
    }
  }

  /**
   * Maneja clics fuera del componente
   */
  handleClickOutside(event) {
    if (
      !this.inputElement.contains(event.target) &&
      !this.resultsContainer.contains(event.target)
    ) {
      this.hide();
    }
  }

  /**
   * Realiza la búsqueda via API
   */
  async search(query) {
    try {
      const response = await fetch(this.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          query: query,
          filters: this.filters,
          limit: this.maxResults,
          format: this.format
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.success) {
        this.currentResults = data.results || [];
        this.render();
        this.show();
      } else {
        this.showError(data.message || 'Error en la búsqueda');
      }
    } catch (error) {
      console.error('SearchClient error:', error);
      this.showError('Error al realizar la búsqueda');
    }
  }

  /**
   * Renderiza los resultados
   */
  render() {
    this.syncResultsWidth();
    this.resultsContainer.innerHTML = '';
    this.selectedIndex = -1;

    if (this.currentResults.length === 0) {
      this.resultsContainer.innerHTML = `
                <div class="list-group-item text-muted">
                    <i class="fa-solid fa-search me-2"></i>
                    No se encontraron resultados
                </div>
            `;
      return;
    }

    this.currentResults.forEach((item, index) => {
      const element = this.renderItem(item, index);
      element.addEventListener('click', () => this.selectItem(item));
      this.resultsContainer.appendChild(element);
    });
  }

  /**
   * Renderizado por defecto de un item
   */
  defaultRenderItem(item, index) {
    const div = document.createElement('button');
    div.type = 'button';
    div.className = 'list-group-item list-group-item-action border-bottom py-2';
    div.dataset.index = index;

    // Datos procesados
    const varCodigo = this.escapeHtml(item.codigo_variante || 'N/A');
    const varDetalle = this.escapeHtml(item.detalle || item.variante_detalle || 'Sin detalle');
    const parteCodigo = this.escapeHtml(item.parte_codigo || '');
    const parteDetalle = this.escapeHtml(item.parte_detalle || '');
    const tipoCodigo = this.escapeHtml(item.tipo_codigo || 'OTRO');

    // Estilo moderno
    div.innerHTML = `
        <div class="d-flex justify-content-between align-items-start w-100">
            <div class="flex-grow-1 pe-3 text-start">
                <div class="d-flex align-items-center mb-1">
                    <span class="badge bg-success me-2" title="Variante">VAR</span>
                    <span class="fw-bold text-dark">${varCodigo}</span>
                    <span class="text-muted mx-2">&bull;</span>
                    <span class="text-secondary small text-truncate" style="max-width: 200px;">${varDetalle}</span>
                </div>
                <div class="d-flex align-items-center text-muted small ms-1">
                    <i class="fa-solid fa-cube me-2 text-secondary"></i>
                    <span class="fw-semibold me-2">${parteCodigo}</span>
                    <span class="text-truncate" style="max-width: 250px;">${parteDetalle}</span>
                </div>
            </div>
            <div class="text-end align-self-center">
                <span class="badge bg-secondary border">${tipoCodigo}</span>
            </div>
        </div>
    `;

    return div;
  }

  /**
   * Resalta un item (navegación con teclado)
   */
  highlightItem(index) {
    const items = this.resultsContainer.querySelectorAll('button[data-index]');
    items.forEach((item, i) => {
      if (i === index) {
        item.classList.add('active');
        item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      } else {
        item.classList.remove('active');
      }
    });
  }

  /**
   * Selecciona un item
   */
  selectItem(item) {
    // Actualizar input visible
    this.inputElement.value = item.display_text ||
      `[${item.tipo_codigo}] ${item.codigo_variante} - ${item.detalle}`;

    // Actualizar input oculto si existe
    if (this.hiddenInput) {
      this.hiddenInput.value = item.id;
    }

    // Callback personalizado
    if (this.onSelect && typeof this.onSelect === 'function') {
      this.onSelect(item);
    }

    // Ocultar resultados
    this.hide();
  }

  /**
   * Muestra el contenedor de resultados
   */
  show() {
    this.syncResultsWidth();
    this.resultsContainer.style.display = 'block';
    this.isVisible = true;
  }

  /**
   * Oculta el contenedor de resultados
   */
  hide() {
    this.resultsContainer.style.display = 'none';
    this.isVisible = false;
    this.selectedIndex = -1;
  }

  /**
   * Muestra un mensaje de error
   */
  showError(message) {
    this.resultsContainer.innerHTML = `
            <div class="list-group-item text-danger">
                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                ${this.escapeHtml(message)}
            </div>
        `;
    this.show();
  }

  /**
   * Actualiza los filtros y re-busca si hay query
   */
  updateFilters(newFilters) {
    this.filters = { ...this.filters, ...newFilters };

    const currentQuery = this.inputElement.value.trim();
    if (currentQuery.length >= this.minChars) {
      this.search(currentQuery);
    }
  }

  /**
   * Limpia el componente
   */
  clear() {
    this.inputElement.value = '';
    if (this.hiddenInput) {
      this.hiddenInput.value = '';
    }
    this.currentResults = [];
    this.hide();
  }

  /**
   * Destruye el componente (cleanup)
   */
  destroy() {
    this.inputElement.removeEventListener('input', this.handleInput);
    this.inputElement.removeEventListener('keydown', this.handleKeydown);
    this.inputElement.removeEventListener('focus', this.handleFocus);
    document.removeEventListener('click', this.handleClickOutside);
    this.hide();
  }

  /**
   * Escapa HTML para prevenir XSS
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }
}

// Exportar para uso con módulos ES6
if (typeof module !== 'undefined' && module.exports) {
  module.exports = SearchClient;
}

// Disponible globalmente
if (typeof window !== 'undefined') {
  window.SearchClient = SearchClient;
}
