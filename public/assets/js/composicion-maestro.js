/**
 * ComposicionMaestro - Alpine.js Component
 *
 * Módulo para la gestión de composición en vista maestro (árbol).
 * Cumple con normativa: código JavaScript externalizado.
 *
 * @version 1.0.0
 */

/**
 * Crea el componente Alpine.js para maestro de composición
 *
 * @param {Object} config Configuración del componente
 * @param {Array} config.treeData Datos del árbol BOM
 * @param {Object} config.variantes Diccionario de variantes disponibles
 * @param {string} config.apiSearchUrl URL del endpoint de búsqueda
 * @param {string} config.baseActionUrl URL base para acciones CRUD
 * @returns {Object} Objeto de datos Alpine.js
 */
window.createComposicionMaestroApp = function (config) {
  return {
    // Estado
    flatTree: config.treeData || [],
    selectedNode: null,
    variantes: config.variantes || {},
    editingItem: {},
    editActionUrl: '',
    activeFilter: '',
    forbiddenIds: [],
    filteredVariants: [],
    selectedMaterial: '',
    searchQuery: '',
    showSearchResults: false,
    searchResults: [],
    searchClient: null,
    addItemParentId: null,
    addActionUrl: '',
    replacementItem: {},
    availableReplacements: [],
    replaceActionUrl: '',
    viewMode: 'list', // 'list' o 'tree'
    currentRootVarianteId: Number.parseInt(config.selectedVarianteId, 10) || null,

    /**
     * Inicialización del componente
     */
    init() {
      this.logInitialization();
      this.normalizeVariantes();
      this.selectInitialNode();
      this.enhanceTreeWithIcons();
      this.setupWatchers();
      this.initializeTooltips();
    },

    /**
     * Selecciona el nodo inicial priorizando foco por query string.
     */
    selectInitialNode() {
      if (!this.autoSelectFocusedNode()) {
        this.autoSelectRoot();
      }
    },

    /**
     * Log de inicialización
     */
    logInitialization() {
      console.group('🚀 Maestro App Init');
      console.log('Variantes loaded:', this.variantes);
      console.log('Type:', typeof this.variantes, 'IsArray:', Array.isArray(this.variantes));
      console.log('Keys count:', Object.keys(this.variantes).length);
      console.log('Values count:', Object.values(this.variantes).length);
      console.groupEnd();
    },

    /**
     * Normaliza variantes si es array vacío
     */
    normalizeVariantes() {
      if (Array.isArray(this.variantes) && this.variantes.length === 0) {
        console.warn('⚠️ Variantes is empty array, converting to empty object');
        this.variantes = {};
      }
    },

    /**
     * Auto-selecciona el nodo raíz
     */
    autoSelectRoot() {
      if (this.flatTree.length > 0) {
        this.selectNode(this.flatTree[0]);
      }
    },

    /**
     * Selecciona nodo en base al parametro focus_variante si existe.
     */
    autoSelectFocusedNode() {
      const searchParams = new URLSearchParams(window.location.search);
      const focusPath = String(searchParams.get('focus_path') || '').trim();
      if (focusPath !== '') {
        const focusedByPath = this.flatTree.find(node => this.getNodeInstanceKey(node) === focusPath);
        if (focusedByPath) {
          this.selectNode(focusedByPath);
          this.$nextTick(() => {
            const selectedEl = document.querySelector('.tree-node.selected');
            if (selectedEl) {
              selectedEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          });
          return true;
        }
      }

      const focusVariantId = Number.parseInt(searchParams.get('focus_variante') || '', 10);
      if (!Number.isInteger(focusVariantId) || focusVariantId <= 0) {
        return false;
      }

      const focusedNode = this.flatTree.find(node => Number.parseInt(node.variante_id, 10) === focusVariantId);
      if (!focusedNode) {
        return false;
      }

      this.selectNode(focusedNode);
      this.$nextTick(() => {
        const selectedEl = document.querySelector('.tree-node.selected');
        if (selectedEl) {
          selectedEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });

      return true;
    },

    /**
     * URL de retorno para mantener variante raiz y foco en el arbol.
     */
    getReturnUrl(focusVariantId = null) {
      const currentUrl = new URL(window.location.href);
      const rootVariantId = Number.parseInt(currentUrl.searchParams.get('id_variante') || '', 10)
        || this.currentRootVarianteId;

      if (Number.isInteger(rootVariantId) && rootVariantId > 0) {
        currentUrl.searchParams.set('id_variante', String(rootVariantId));
      }

      const fallbackFocusId = this.selectedNode ? Number.parseInt(this.selectedNode.variante_id, 10) : null;
      const resolvedFocusId = Number.parseInt(String(focusVariantId ?? fallbackFocusId ?? ''), 10);

      if (Number.isInteger(resolvedFocusId) && resolvedFocusId > 0) {
        currentUrl.searchParams.set('focus_variante', String(resolvedFocusId));
      } else {
        currentUrl.searchParams.delete('focus_variante');
      }

      const focusPath = this.selectedNode ? this.getNodeInstanceKey(this.selectedNode) : '';
      if (focusPath) {
        currentUrl.searchParams.set('focus_path', focusPath);
      } else {
        currentUrl.searchParams.delete('focus_path');
      }

      return currentUrl.toString();
    },

    /**
     * Normaliza el path PostgreSQL del arbol a array de IDs.
     */
    parseNodePathIds(node) {
      if (!node) return [];

      const rawPath = node.path;
      if (Array.isArray(rawPath)) {
        return rawPath
          .map(value => Number.parseInt(value, 10))
          .filter(value => Number.isInteger(value) && value > 0);
      }

      const pathText = String(rawPath || '').trim();
      if (pathText === '') {
        const fallbackVariantId = Number.parseInt(node.variante_id, 10);
        return Number.isInteger(fallbackVariantId) && fallbackVariantId > 0 ? [fallbackVariantId] : [];
      }

      const normalized = pathText.replace(/^\{/, '').replace(/\}$/, '');
      if (normalized === '') {
        return [];
      }

      return normalized
        .split(',')
        .map(value => Number.parseInt(value, 10))
        .filter(value => Number.isInteger(value) && value > 0);
    },

    /**
     * Clave unica de instancia del nodo (path completo en el arbol).
     */
    getNodeInstanceKey(node) {
      const ids = this.parseNodePathIds(node);
      if (ids.length > 0) {
        return ids.join('>');
      }

      const variantId = Number.parseInt(node?.variante_id, 10);
      return Number.isInteger(variantId) && variantId > 0 ? String(variantId) : '';
    },

    /**
     * Clave de instancia del padre del nodo actual.
     */
    getParentInstanceKey(node) {
      const ids = this.parseNodePathIds(node);
      if (ids.length <= 1) {
        return '';
      }

      return ids.slice(0, -1).join('>');
    },

    /**
     * Determina seleccion por instancia (evita colision cuando la variante se repite en ramas distintas).
     */
    isSelectedNode(node) {
      if (!this.selectedNode || !node) return false;

      return this.getNodeInstanceKey(this.selectedNode) === this.getNodeInstanceKey(node);
    },

    /**
     * Agrega iconos a los nodos del árbol
     */
    enhanceTreeWithIcons() {
      this.flatTree = this.flatTree.map(node => ({
        ...node,
        full_variant_label: this.buildFullVariantLabel(node),
        icon_class: this.hasChildrenInTree(node)
          ? 'fa-folder text-warning me-2'
          : 'fa-cube text-info me-2'
      }));
    },

    /**
     * Construye etiqueta completa de la variante para mostrar contexto
     */
    buildFullVariantLabel(node) {
      const variantSource = this.variantes?.[node.variante_id] || {};
      const parteCodigo = variantSource.parte_codigo || 'N/A';
      const parteDetalle = variantSource.parte_detalle || '';
      const varianteCodigo = node.codigo_variante || variantSource.codigo_variante || 'N/A';
      const varianteDetalle = node.variante_detalle || variantSource.detalle || '';

      return `Parte: ${parteCodigo} - ${parteDetalle} | Variante: ${varianteCodigo} - ${varianteDetalle}`;
    },

    /**
     * Configura watchers reactivos
     */
    setupWatchers() {
      this.updateFilteredVariants();
      this.$watch('activeFilter', () => this.updateFilteredVariants());
    },

    /**
     * Inicializa tooltips de Bootstrap
     */
    initializeTooltips() {
      this.$nextTick(() => {
        const tooltipTriggerList = [].slice.call(
          document.querySelectorAll('[data-bs-toggle="tooltip"]')
        );
        tooltipTriggerList.map(tooltipTriggerEl =>
          new bootstrap.Tooltip(tooltipTriggerEl)
        );
      });
    },

    /**
     * Establece filtro de tipo
     */
    setFilter(type) {
      this.activeFilter = type;
    },

    /**
     * Selecciona un nodo del árbol
     */
    selectNode(node) {
      this.selectedNode = node;
    },

    /**
     * Resuelve datos de variante desde el mapa para completar campos faltantes.
     */
    resolveVariantSource(item) {
      if (!item) return {};
      return this.variantes?.[item.variante_id] || {};
    },

    /**
     * Obtiene código compuesto parte-variante para cualquier item.
     */
    getItemCode(item) {
      if (!item) return 'N/A';

      const source = this.resolveVariantSource(item);
      const parteCodigo = String(item.parte_codigo || source.parte_codigo || '').trim();
      const varianteCodigo = String(item.codigo_variante || source.codigo_variante || '').trim();
      const codigoCompuesto = [parteCodigo, varianteCodigo].filter(Boolean).join('-');

      return codigoCompuesto || 'N/A';
    },

    /**
     * Obtiene detalle compuesto parte-variante para cualquier item.
     */
    getItemDetail(item) {
      if (!item) return 'Sin detalle';

      const source = this.resolveVariantSource(item);
      const parteDetalle = String(item.parte_detalle || source.parte_detalle || '').trim();
      const varianteDetalle = String(
        item.variante_detalle || item.detalle || source.variante_detalle || source.detalle || ''
      ).trim();
      const detalleCompuesto = [parteDetalle, varianteDetalle].filter(Boolean).join(' - ');

      return detalleCompuesto || 'Sin detalle';
    },

    /**
     * Cambia el modo de visualización
     */
    setViewMode(mode) {
      this.viewMode = mode;
    },

    /**
     * Obtiene el árbol recursivo del nodo seleccionado
     */
    getNodeTree(parentNode, level = 0) {
      if (!parentNode) return [];

      const children = this.getChildren(parentNode);
      const result = [];

      children.forEach(child => {
        result.push({
          ...child,
          level: level
        });

        // Recursivamente obtener hijos
        const subChildren = this.getNodeTree(child, level + 1);
        result.push(...subChildren);
      });

      return result;
    },

    /**
     * Obtiene hijos de un nodo
     */
    getChildren(parentNode) {
      if (!parentNode) return [];

      const parentId = Number.parseInt(parentNode.variante_id, 10);
      const parentInstanceKey = this.getNodeInstanceKey(parentNode);

      return this.flatTree.filter(node => {
        const nodeParentId = Number.parseInt(node.parent_id, 10);
        if (!Number.isInteger(nodeParentId) || nodeParentId !== parentId) {
          return false;
        }

        return this.getParentInstanceKey(node) === parentInstanceKey;
      });
    },

    /**
     * Verifica si un nodo tiene hijos
     */
    hasChildrenInTree(node) {
      return this.getChildren(node).length > 0;
    },

    /**
     * Formatea cantidades con configuración de empresa, eliminando ceros a la derecha.
     */
    formatQuantity(value) {
      const numericValue = Number.parseFloat(value);
      if (!Number.isFinite(numericValue)) {
        return value;
      }

      const settings = {
        decimal_places: 4,
        rounding_mode: 'half_up',
        thousand_separator: '.',
        decimal_separator: ',',
        ...(window.appFormattingSettings || {})
      };

      const decimals = Math.min(Math.max(Number.parseInt(settings.decimal_places, 10) || 4, 1), 6);
      const rounded = this.roundQuantityValue(numericValue, decimals, String(settings.rounding_mode || 'half_up'));

      let fixed = rounded.toFixed(decimals);
      fixed = fixed.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');

      const [rawInteger, rawDecimal = ''] = fixed.split('.');
      const sign = rawInteger.startsWith('-') ? '-' : '';
      const digits = sign ? rawInteger.slice(1) : rawInteger;
      const thousandSeparator = String(settings.thousand_separator ?? '.');
      const decimalSeparator = String(settings.decimal_separator ?? ',');
      const groupedInteger = digits.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

      if (rawDecimal === '') {
        return sign + groupedInteger;
      }

      return sign + groupedInteger + decimalSeparator + rawDecimal;
    },

    roundQuantityValue(value, decimals, mode) {
      const factor = 10 ** decimals;

      if (mode === 'truncate') {
        return value >= 0 ? Math.floor(value * factor) / factor : Math.ceil(value * factor) / factor;
      }

      if (mode === 'half_down') {
        const sign = value >= 0 ? 1 : -1;
        const abs = Math.abs(value * factor);
        const integer = Math.floor(abs);
        const fraction = abs - integer;
        const adjusted = fraction > 0.5 ? integer + 1 : integer;
        return sign * adjusted / factor;
      }

      if (mode === 'half_even') {
        const rounded = Math.round(value * factor);
        const diff = Math.abs(value * factor - rounded);
        if (Math.abs(diff - 0.5) < Number.EPSILON && rounded % 2 !== 0) {
          return (rounded - Math.sign(value)) / factor;
        }
        return rounded / factor;
      }

      return Math.round(value * factor) / factor;
    },

    /**
     * Actualiza variantes filtradas
     */
    updateFilteredVariants() {
      const rawVariants = Object.values(this.variantes);

      console.group('updateFilteredVariants');
      console.log('Raw variants count:', rawVariants.length);
      console.log('Forbidden IDs:', this.forbiddenIds);
      console.log('Active Filter:', this.activeFilter);

      const forbiddenSet = new Set(this.forbiddenIds.map(id => String(id)));

      this.filteredVariants = rawVariants.filter(v => {
        if (!v || !v.id) {
          console.warn('Invalid variant object:', v);
          return false;
        }

        // Prevención de ciclos
        if (forbiddenSet.has(String(v.id))) {
          return false;
        }

        // Filtro por tipo
        if (this.activeFilter !== '') {
          const tipo = v.tipo_codigo;

          if (this.activeFilter === 'PART') {
            return ['SC', 'PT', 'PART'].includes(tipo);
          }

          if (!tipo) return false;
          return tipo === this.activeFilter;
        }

        return true;
      });

      // Ordenar alfabéticamente
      this.filteredVariants.sort((a, b) => {
        const na = (a.parte_codigo + a.codigo_variante).toLowerCase();
        const nb = (b.parte_codigo + b.codigo_variante).toLowerCase();
        return na.localeCompare(nb);
      });

      console.log('Filtered count:', this.filteredVariants.length);
      console.groupEnd();
    },

    /**
     * Actualiza resultados de búsqueda
     */
    updateSearchResults() {
      const query = this.searchQuery.trim();

      if (query.length < 2) {
        this.searchResults = [];
        this.showSearchResults = false;
        return;
      }

      this.performSearch(query);
    },

    /**
     * Realiza búsqueda via API
     */
    async performSearch(query) {
      try {
        const response = await fetch(config.apiSearchUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({
            query: query,
            filters: {
              tipo_codigo: this.activeFilter || undefined,
              exclude_ids: this.forbiddenIds
            },
            limit: 10,
            format: 'standard'
          })
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          this.searchResults = data.results || [];
          this.showSearchResults = true;
        } else {
          console.error('Search error:', data.message);
          this.searchResults = [];
          this.showSearchResults = false;
        }
      } catch (error) {
        console.error('Search API error:', error);
        this.searchResults = [];
        this.showSearchResults = false;
      }
    },

    /**
     * Selecciona resultado de búsqueda
     */
    selectSearchResult(item) {
      this.selectedMaterial = item.id;
      this.searchQuery = `[${item.tipo_codigo || 'OTRO'}] ${item.codigo_variante} - ${item.detalle || item.variante_detalle || 'Sin detalle'}`;
      this.showSearchResults = false;
    },

    /**
     * Obtiene ID de parte de un nodo
     */
    getPartId(node) {
      const v = this.variantes[node.variante_id];
      return v ? v.id_parte : 0;
    },

    /**
     * Abre modal de edición
     */
    editItem(item) {
      this.editingItem = { ...item };
      this.editActionUrl = `${config.baseActionUrl}/${item.bom_detalle_id}`;

      const modalEl = document.getElementById('modalEditar');
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    },

    /**
     * Determina si el componente usa unidad de superficie.
     */
    isSurfaceUsageItem(item = this.editingItem) {
      if (!item) return false;

      const source = this.resolveVariantSource(item);
      const umUsoTipo = String(source.um_uso_tipo || '').toLowerCase().trim();
      const umUsoCodigo = String(source.um_uso || source.um_uso_codigo || '')
        .toLowerCase()
        .replace(/\s+/g, '');

      return umUsoTipo === 'superficie' || ['m2', 'm²'].includes(umUsoCodigo);
    },

    /**
     * Obtiene el ID de la variante padre para el item que se esta editando.
     */
    getEditParentVariantId(item = this.editingItem) {
      if (item && Number.isFinite(Number.parseInt(item.parent_id, 10)) && Number.parseInt(item.parent_id, 10) > 0) {
        return Number.parseInt(item.parent_id, 10);
      }

      if (this.selectedNode && Number.isFinite(Number.parseInt(this.selectedNode.variante_id, 10)) && Number.parseInt(this.selectedNode.variante_id, 10) > 0) {
        return Number.parseInt(this.selectedNode.variante_id, 10);
      }

      return null;
    },

    /**
     * Superficie del padre para sugerir cantidad automatica.
     */
    getParentSurfaceForEdit(item = this.editingItem) {
      const parentVariantId = this.getEditParentVariantId(item);
      if (!parentVariantId) return null;

      const parentVariant = this.variantes?.[parentVariantId] || null;
      const parentSurface = Number.parseFloat(parentVariant?.superficie);

      return Number.isFinite(parentSurface) && parentSurface > 0 ? parentSurface : null;
    },

    /**
     * Define si se puede autocalcular cantidad en el modal de edicion.
     */
    canAutoCalculateEditQuantity(item = this.editingItem) {
      return this.isSurfaceUsageItem(item) && this.getParentSurfaceForEdit(item) !== null;
    },

    /**
     * Aplica la cantidad calculada automaticamente (superficie del padre).
     */
    applyAutoCalculatedEditQuantity() {
      const surfaceValue = this.getParentSurfaceForEdit(this.editingItem);
      if (surfaceValue === null) return;

      this.editingItem.cantidad = String(surfaceValue);
    },

    /**
     * Elimina un item (con confirmación)
     */
    deleteItem(item) {
      if (!confirm('¿Estás seguro de quitar este componente?')) {
        return;
      }

      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `${config.baseActionUrl}/${item.bom_detalle_id}`;

      const methodField = document.createElement('input');
      methodField.type = 'hidden';
      methodField.name = '_method';
      methodField.value = 'DELETE';
      form.appendChild(methodField);

      const redirField = document.createElement('input');
      redirField.type = 'hidden';
      redirField.name = 'redirect_to';
      redirField.value = window.location.href;
      form.appendChild(redirField);

      document.body.appendChild(form);
      form.submit();
    },

    /**
     * Abre modal de reemplazo
     */
    replaceItem(item) {
      this.replacementItem = { ...item };
      const partId = this.getPartId(item);

      this.availableReplacements = Object.values(this.variantes).filter(v =>
        v.id_parte == partId && v.id != item.variante_id
      );

      this.replaceActionUrl = `${config.baseActionUrl}/${item.bom_detalle_id}`;

      const modalEl = document.getElementById('modalReemplazar');
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    },

    /**
     * Muestra destino de partes
     */
    destinoPartes(item) {
      console.group('📦 Destino de Partes');
      console.log('Item:', item);
      console.log('Código Variante:', item.codigo_variante);
      console.log('Variante ID:', item.variante_id);
      console.groupEnd();

      // TODO: Implementar funcionalidad de destino de partes
      alert(`Destino de Partes\n\nCódigo: ${item.codigo_variante}\nVariante: ${item.variante_detalle}\n\n(Funcionalidad pendiente de implementación)`);
    },

    /**
     * Abre modal de agregar componente
     */
    openAddModal() {
      if (!this.selectedNode) {
        alert('Por favor selecciona un nodo primero');
        return;
      }

      console.group('🔵 openAddModal');
      console.log('Selected Node:', this.selectedNode);

      // Reset
      this.selectedMaterial = '';
      this.searchQuery = '';
      this.showSearchResults = false;
      this.searchResults = [];
      this.activeFilter = '';

      // Configurar acción
      this.addItemParentId = this.selectedNode.variante_id;
      this.addActionUrl = config.baseActionUrl;

      // Calcular IDs prohibidos (prevenir ciclos)
      this.forbiddenIds = [this.selectedNode.variante_id];

      if (this.selectedNode.path) {
        const pathString = String(this.selectedNode.path);
        const cleanPath = pathString.replace(/^\{|\}$/g, '');
        if (cleanPath) {
          const ancestorIds = cleanPath.split(',').map(id => parseInt(id));
          ancestorIds.forEach(id => {
            if (!this.forbiddenIds.includes(id)) {
              this.forbiddenIds.push(id);
            }
          });
        }
      }

      console.log('Forbidden IDs calculated:', this.forbiddenIds);
      this.updateFilteredVariants();
      console.log('Filtered Variants result:', this.filteredVariants.length);
      console.groupEnd();

      const modalEl = document.getElementById('modalAgregar');
      if (modalEl) {
        const parentVariant = this.variantes?.[this.selectedNode.variante_id] || null;
        const parentSurface = Number.parseFloat(parentVariant?.superficie);

        modalEl.dataset.parentVarianteId = String(this.selectedNode.variante_id || '');
        modalEl.dataset.parentSuperficie = Number.isFinite(parentSurface) ? String(parentSurface) : '';
      }

      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  };
};
