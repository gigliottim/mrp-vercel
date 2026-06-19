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
      maxHeight: '320px',
      overflowY: 'auto',
      display: 'none',
      backgroundColor: '#fff',
      border: '1px solid #e2e8f0',
      borderRadius: '6px',
      boxShadow: '0 8px 24px rgba(0,0,0,.12)',
    });

    this.syncResultsWidth();
  }

  /**
   * Sincroniza el ancho del dropdown con el input visible.
   * Evita anchos de 0px cuando el input se inicializa oculto.
   */
  syncResultsWidth() {
    const parent = this.resultsContainer.parentElement;
    if (parent) {
      this.resultsContainer.style.width = Math.round(parent.getBoundingClientRect().width) + 'px';
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
                <div style="text-align:center;padding:1.2rem .5rem;color:#94a3b8;font-size:.82rem;">
                    <i class="fa-solid fa-search" style="font-size:1.4rem;opacity:.4;display:block;margin-bottom:.3rem;"></i>
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
    div.dataset.index = index;
    div.style.cssText = 'width:100%;border:none;background:transparent;text-align:left;padding:.55rem .7rem;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .12s;font-size:.82rem;color:#1e293b;';

    const varCodigo = this.escapeHtml(item.codigo_variante || 'N/A');
    const varDetalle = this.escapeHtml(item.detalle || item.variante_detalle || 'Sin detalle');
    const parteCodigo = this.escapeHtml(item.parte_codigo || '');
    const parteDetalle = this.escapeHtml(item.parte_detalle || '');
    const tipoCodigo = this.escapeHtml(item.tipo_codigo || 'OTRO');
    const tipoNombre = this.escapeHtml(item.tipo_nombre || tipoCodigo);

    const tipoColors = {
      'MP': {bg:'#ecfdf5',fg:'#059669'},
      'PZ': {bg:'#eef2ff',fg:'#4f46e5'},
      'PROD': {bg:'#fffbeb',fg:'#d97706'},
      'CONJ': {bg:'#fef2f2',fg:'#dc2626'},
      'S-CONJ': {bg:'#f0fdf4',fg:'#16a34a'},
      'TER': {bg:'#ecfeff',fg:'#0891b2'},
      'MO': {bg:'#f8fafc',fg:'#64748b'},
    };
    const tc = tipoColors[tipoCodigo] || {bg:'#f1f5f9',fg:'#64748b'};

    div.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:.35rem;margin-bottom:.15rem;">
                    <span style="background:${tc.bg};color:${tc.fg};padding:.1em .4em;border-radius:9999px;font-size:.6rem;font-weight:700;white-space:nowrap;">${tipoCodigo}</span>
                    <span style="font-weight:700;color:#4f46e5;font-size:.75rem;">VAR ${varCodigo}</span>
                    <span style="color:#64748b;font-size:.68rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${varDetalle}</span>
                </div>
                <div style="display:flex;align-items:center;gap:.3rem;font-size:.7rem;color:#94a3b8;margin-left:.1rem;">
                    <i class="fa-solid fa-cube" style="font-size:.55rem;color:#94a3b8;"></i>
                    <span style="font-weight:600;color:#475569;">${parteCodigo}</span>
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${parteDetalle}</span>
                </div>
            </div>
        </div>
    `;

    div.addEventListener('mouseenter', () => { div.style.background = '#eef2ff'; });
    div.addEventListener('mouseleave', () => { div.style.background = 'transparent'; });

    return div;
  }

  /**
   * Resalta un item (navegación con teclado)
   */
  highlightItem(index) {
    const items = this.resultsContainer.querySelectorAll('button[data-index]');
    items.forEach((item, i) => {
      if (i === index) {
        item.style.background = '#eef2ff';
        item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      } else {
        item.style.background = 'transparent';
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
            <div style="text-align:center;padding:1rem .5rem;color:#dc2626;font-size:.82rem;">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right:.3rem;"></i>
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
