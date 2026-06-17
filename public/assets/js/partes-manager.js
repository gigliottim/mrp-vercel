/**
 * Gestor moderno de Partes y Variantes
 * Usa Alpine.js para el manejo del estado
 */

function parteManager(initialData) {
  const BASE = (window.MRP_BASE_PATH || '').replace(/\/$/, '');
  const isEditingVariantContext = Boolean(
    (initialData.editingVariant && initialData.editingVariant.id) || initialData.editingVariantId
  );
  const startsLockedWithoutPart = initialData.mode === 'create' && !initialData.parte;

  return {
    decimalPlaces: 4,
    numberInputStep: '0.0001',

    // Estado
    mode: initialData.mode || 'create',
    isEditing: initialData.mode !== 'create',
    editingVariantId: initialData.editingVariantId || null,
    editingVariant: initialData.editingVariant || null,
    isPartFormReadOnly: initialData.mode === 'view' || isEditingVariantContext || startsLockedWithoutPart,
    isVariantFormEnabled: initialData.mode === 'edit' && isEditingVariantContext,
    loading: false,
    parte: initialData.parte || null,
    variantes: initialData.variantes || [],

    // Formularios
    form: {
      id: null,
      codigo: '',
      id_tipo: '',
      id_grupo: '',
      id_um_compra: '',
      id_um_uso: '',
      detalle: '',
      largo_alto: null,
      id_um_largo_alto: '',
      ancho: null,
      id_um_ancho: '',
      espesor_profundidad: null,
      id_um_espesor: '',
      superficie: null,
      id_um_superficie: '',
      volumen: null,
      id_um_volumen: '',
      activo: true
    },

    variantForm: {
      id: null,
      codigo_variante: '',
      detalle: '',
      estado: 'activa',
      lote_minimo: 1,
      punto_pedido: 0,
      stock_actual: 0,
      peso: null,
      id_um_peso: '',
      ubicacion_cuerpo: '',
      ubicacion_pasillo: '',
      ubicacion_estante: ''
    },

    // Datos auxiliares (se cargan desde PHP)
    unidadesMasa: window.unidadesMasaData || [],
    unidadesTodas: window.unidadesTodasData || [],

    // Inicialización
    init() {
      this.decimalPlaces = this._getConfiguredDecimalPlaces();
      this.numberInputStep = this._buildInputStep(this.decimalPlaces);

      if (this.parte) {
        this.loadParteData(this.parte);
      }

      // Auto-cargar variante cuando venimos desde /variantes/{id}/editar
      if (this.editingVariant && this.editingVariant.id) {
        this.loadVarianteIntoForm(this.editingVariant);
      } else if (this.editingVariantId && this.variantes && this.variantes.length > 0) {
        const variante = this.variantes.find(v => v.id == this.editingVariantId);
        if (variante) {
          this.loadVarianteIntoForm(variante);
        }
      }

      // Watchers para auto-calcular factor_conversion
      this.$watch('form.id_um_compra', () => this.autoCalculateFactorConversion());
      this.$watch('form.id_um_uso', () => this.autoCalculateFactorConversion());
      this.$watch('form.ancho', () => this.autoCalculateFactorConversion());
      this.$watch('form.id_um_ancho', () => this.autoCalculateFactorConversion());

      this.$nextTick(() => this.adjustVariantDetalleHeight());
    },

    hasValidParteSelection() {
      const id = Number.parseInt(this.form?.id, 10);
      return Number.isInteger(id) && id > 0;
    },

    // Cargar datos de una parte
    loadParteData(parte) {
      this.form = {
        id: parte.id,
        codigo: parte.codigo || '',
        id_tipo: parseInt(parte.id_tipo) || '',
        id_grupo: parseInt(parte.id_grupo) || '',
        id_um_compra: parte.id_um_compra || '',
        id_um_uso: parte.id_um_uso || '',
        factor_conversion: this._toNullableNumber(parte.factor_conversion),
        detalle: parte.detalle || '',
        largo_alto: this._toNullableNumber(parte.largo_alto),
        id_um_largo_alto: parte.id_um_largo_alto || '',
        ancho: this._toNullableNumber(parte.ancho),
        id_um_ancho: parte.id_um_ancho || '',
        espesor_profundidad: this._toNullableNumber(parte.espesor_profundidad),
        id_um_espesor: parte.id_um_espesor || '',
        superficie: this._toNullableNumber(parte.superficie),
        id_um_superficie: parte.id_um_superficie || '',
        volumen: this._toNullableNumber(parte.volumen),
        id_um_volumen: parte.id_um_volumen || '',
        activo: parte.activo === true || parte.activo === 1 || parte.activo === '1'
      };

      this.isEditing = true;
    },

    // Cargar parte desde selector
    loadParte(id) {
      if (!id) return;
      window.location.href = `${BASE}/productos/partes/manager/${id}`;
    },

    // Cargar variante exacta desde selector de búsqueda
    loadVariante(idParte, idVariante) {
      if (!idParte || !idVariante) return;
      window.location.href = `${BASE}/productos/partes/manager/${idParte}/variantes/${idVariante}`;
    },

    // Resetear formulario
    resetForm() {
      this.form = {
        id: null,
        codigo: '',
        id_tipo: '',
        id_grupo: '',
        id_um_compra: '',
        id_um_uso: '',
        factor_conversion: null,
        detalle: '',
        largo_alto: null,
        id_um_largo_alto: '',
        ancho: null,
        id_um_ancho: '',
        espesor_profundidad: null,
        id_um_espesor: '',
        superficie: null,
        id_um_superficie: '',
        volumen: null,
        id_um_volumen: '',
        activo: true
      };

      this.parte = null;
      this.variantes = [];
      this.mode = 'create';
      this.isEditing = false;
      this.isPartFormReadOnly = false;
      this.isVariantFormEnabled = false;
      this.editingVariantId = null;
      this.editingVariant = null;
      this.resetVariantForm();

      // Limpiar el campo de búsqueda
      const searchInput = document.getElementById('parte-search-input');
      if (searchInput) {
        searchInput.value = '';
      }

      // Cerrar resultados de búsqueda si están abiertos
      const searchResults = document.getElementById('parte-search-results');
      if (searchResults) {
        searchResults.style.display = 'none';
      }

      // No redirigir, mantener la página actual
      // Para evitar recargas innecesarias, simplemente actualizar el estado
      const managerUrl = new URL(`${BASE}/productos/partes/manager`, window.location.origin);
      if (window.location.pathname !== managerUrl.pathname) {
        window.history.pushState({}, '', managerUrl.href);
      }
    },

    // ─── Helpers de unidades ──────────────────────────────────────────────

    /**
     * Devuelve la equivalencia_base (en metros) de una UM dada su ID.
     * Si no se encuentra o no tiene equivalencia, devuelve null.
     */
    _getEquivBase(unitId, dataset) {
      if (!unitId || !dataset || !dataset.length) return null;
      const u = dataset.find(u => String(u.id) === String(unitId));
      return u && u.equivalencia_base ? parseFloat(u.equivalencia_base) : null;
    },

    /**
     * Redondeo de precisión: elimina ruido de punto flotante sin truncar
     * valores pequeños legítimos (hasta 8 decimales significativos).
     */
    _round(value, decimals = 8) {
      const factor = Math.pow(10, decimals);
      return Math.round(value * factor) / factor;
    },

    _getConfiguredDecimalPlaces() {
      const configured = Number.parseInt(window.appFormattingSettings?.decimal_places, 10);
      if (!Number.isFinite(configured)) {
        return 4;
      }

      return Math.max(1, Math.min(10, configured));
    },

    _buildInputStep(decimals) {
      if (!Number.isInteger(decimals) || decimals < 1) {
        return '0.0001';
      }

      return `0.${'0'.repeat(decimals - 1)}1`;
    },

    _toNullableNumber(value) {
      if (value === null || value === '') {
        return null;
      }

      const numericValue = Number.parseFloat(value);
      if (!Number.isFinite(numericValue)) {
        return null;
      }

      return this._round(numericValue, this.decimalPlaces);
    },

    _toNumberWithDefault(value, fallback = 0) {
      const normalized = this._toNullableNumber(value);
      return normalized === null ? fallback : normalized;
    },

    formatNumberForInput(value) {
      const normalized = this._toNullableNumber(value);
      return normalized === null ? '' : String(normalized);
    },

    formatNumberDisplay(value, fallback = '') {
      const normalized = this._toNullableNumber(value);
      if (normalized === null) {
        return fallback;
      }

      if (typeof window.appFormatNumber === 'function') {
        return window.appFormatNumber(normalized, this.decimalPlaces);
      }

      return String(normalized);
    },

    normalizeNumberInputValue(event, modelPath) {
      const rawValue = event?.target?.value ?? '';
      const normalized = this._toNullableNumber(rawValue);
      const displayValue = normalized === null ? '' : String(normalized);

      if (event?.target) {
        event.target.value = displayValue;
      }

      if (modelPath && modelPath.startsWith('form.')) {
        const key = modelPath.slice(5);
        this.form[key] = normalized;
      }

      if (modelPath && modelPath.startsWith('variantForm.')) {
        const key = modelPath.slice(12);
        this.variantForm[key] = normalized;
      }
    },

    // ─── Auto-calcular factor_conversion ─────────────────────────────────

    /**
     * Si UM Compra es de tipo longitud (ej. mL) y UM Uso es de tipo superficie,
     * el factor de conversión equivale al ancho de la pieza expresado en metros.
     *
     * factor = ancho [en metros]
     *        = ancho_valor × equivalencia_base_um_ancho
     *
     * Solo sobreescribe si el campo está vacío o en 0; el usuario puede editarlo
     * manualmente después.
     */
    autoCalculateFactorConversion() {
      const umCompraId = this.form.id_um_compra;
      const umUsoId = this.form.id_um_uso;

      if (!umCompraId || !umUsoId || umCompraId === umUsoId) return;

      // Buscar en TODAS las unidades disponibles (longitud para compra)
      const todasLasUnits = [
        ...(window.unidadesLongitudData || []),
        ...(window.unidadesSuperficieData || []),
        ...(window.unidadesVolumenData || []),
        ...(window.unidadesMasaData || [])
      ];

      const umCompra = todasLasUnits.find(u => String(u.id) === String(umCompraId));
      const umUso = todasLasUnits.find(u => String(u.id) === String(umUsoId));

      if (!umCompra || !umUso) return;

      // Solo aplica cuando compra es longitud (mL o cualquier longitud)
      // y uso es superficie
      const compraEsLongitud = umCompra.tipo === 'longitud';
      const usoEsSuperficie = umUso.tipo === 'superficie';

      if (!compraEsLongitud || !usoEsSuperficie) return;

      const ancho = parseFloat(this.form.ancho) || 0;
      const idUmAncho = this.form.id_um_ancho;

      if (ancho <= 0) return;  // Sin ancho no hay factor que calcular

      // Obtener equivalencia del ancho en metros
      const equivAncho = this._getEquivBase(idUmAncho, window.unidadesLongitudData);
      if (!equivAncho) return;  // Sin equivalencia no podemos calcular

      const anchoEnMetros = ancho * equivAncho;
      const factorCalculado = this._round(anchoEnMetros, this.decimalPlaces);

      // Solo sobreescribir si está vacío o en 0 (no pisar edición manual)
      const factorActual = parseFloat(this.form.factor_conversion) || 0;
      if (factorActual === 0) {
        this.form.factor_conversion = factorCalculado;
      }
    },

    // ─── Calcular superficie y volumen ───────────────────────────────────

    /**
     * Calcula superficie (m²) y volumen (cm³) usando la equivalencia_base
     * de las UMs seleccionadas para cada dimensión, en lugar de asumir mm.
     *
     * equivalencia_base para unidades de longitud está expresada en metros:
     *   mm → 0.001,  cm → 0.01,  m → 1,  in → 0.0254, etc.
     *
     * Si no hay UM seleccionada para una dimensión, se asume metros (equiv = 1)
     * para que el valor ingresado se tome "tal cual".
     */
    calculateDimensions() {
      const largoPx = parseFloat(this.form.largo_alto) || 0;
      const anchoPx = parseFloat(this.form.ancho) || 0;
      const espesorPx = parseFloat(this.form.espesor_profundidad) || 0;

      const longData = window.unidadesLongitudData || [];

      // Equivalencias en metros (1 si no hay UM seleccionada → el valor se toma directo)
      const equivLargo = this._getEquivBase(this.form.id_um_largo_alto, longData) ?? 1;
      const equivAncho = this._getEquivBase(this.form.id_um_ancho, longData) ?? 1;
      const equivEspesor = this._getEquivBase(this.form.id_um_espesor, longData) ?? 1;

      // Dimensiones en metros
      const largoM = largoPx * equivLargo;
      const anchoM = anchoPx * equivAncho;
      const espesorM = espesorPx * equivEspesor;

      // ── Superficie en m² ──────────────────────────────────────────────
      if (largoPx > 0 && anchoPx > 0) {
        this.form.superficie = this._round(largoM * anchoM, this.decimalPlaces);

        // Asignar m² automáticamente si no hay unidad elegida
        if (!this.form.id_um_superficie && window.unidadesSuperficieData) {
          const m2 = window.unidadesSuperficieData.find(
            u => u.simbolo === 'm²' || u.simbolo === 'mts²'
          );
          if (m2) this.form.id_um_superficie = m2.id;
        }
      }

      // ── Volumen en cm³ ────────────────────────────────────────────────
      if (largoPx > 0 && anchoPx > 0 && espesorPx > 0) {
        // m³ → cm³: × 1_000_000
        const volumenCm3 = largoM * anchoM * espesorM * 1_000_000;
        this.form.volumen = this._round(volumenCm3, this.decimalPlaces);

        // Asignar cm³ automáticamente si no hay unidad elegida
        if (!this.form.id_um_volumen && window.unidadesVolumenData) {
          const cm3 = window.unidadesVolumenData.find(
            u => u.simbolo === 'cm³' || u.simbolo.toLowerCase().includes('cm')
          );
          if (cm3) this.form.id_um_volumen = cm3.id;
        }
      }
    },

    // Validar unidades de medida
    validateUnidadesMedida() {
      const dimensionFields = [
        { value: 'largo_alto', unit: 'id_um_largo_alto', label: 'Largo / Alto' },
        { value: 'ancho', unit: 'id_um_ancho', label: 'Ancho' },
        { value: 'espesor_profundidad', unit: 'id_um_espesor', label: 'Espesor / Profundidad' },
        { value: 'superficie', unit: 'id_um_superficie', label: 'Superficie' },
        { value: 'volumen', unit: 'id_um_volumen', label: 'Volumen' }
      ];

      const errors = [];

      dimensionFields.forEach(field => {
        const value = parseFloat(this.form[field.value]) || 0;
        const unit = this.form[field.unit];

        // Si el valor es distinto de 0 y no hay unidad seleccionada
        if (value !== 0 && (!unit || unit === '' || unit === 'UM')) {
          errors.push(field.label);
        }
      });

      if (errors.length > 0) {
        alert(`Los siguientes campos tienen valores pero no tienen unidad de medida seleccionada:\n\n${errors.join('\n')}\n\nPor favor, seleccione una unidad de medida o deje el valor en 0.`);
        return false;
      }

      return true;
    },

    // Guardar parte
    async saveParte() {
      // Validar unidades de medida antes de guardar
      if (!this.validateUnidadesMedida()) {
        return;
      }

      this.loading = true;

      try {
        const url = this.form.id
          ? `${BASE}/productos/partes/manager/${this.form.id}`
          : BASE + '/productos/partes/manager';

        const method = this.form.id ? 'PUT' : 'POST';
        const formData = new URLSearchParams();

        Object.keys(this.form).forEach(key => {
          if (key !== 'id') {
            const value = this.form[key];
            if (key === 'activo') {
              formData.append(key, value ? '1' : '0');
            } else if (value !== null && value !== '') {
              formData.append(key, value);
            }
          }
        });

        if (method === 'PUT') {
          formData.append('_method', 'PUT');
        }

        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: formData
        });

        if (response.redirected) {
          window.location.href = response.url;
        } else if (response.ok) {
          const contentType = response.headers.get('content-type') || '';
          if (contentType.includes('application/json')) {
            const payload = await response.json();
            if (payload.redirect_url) {
              window.location.href = payload.redirect_url;
              return;
            }

            if (payload.status === 'error') {
              alert(payload.message || 'Error al guardar la parte');
              return;
            }
          }

          // Fallback para respuestas sin JSON
          window.location.reload();
        } else {
          let errorMessage = 'Error al guardar la parte';
          try {
            const payload = await response.json();
            if (payload && payload.message) {
              errorMessage = payload.message;
            }
          } catch (e) {
            // Keep default message when response is not JSON.
          }
          alert(errorMessage);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar la parte: ' + error.message);
      } finally {
        this.loading = false;
      }
    },

    // ===== FUNCIONES VIEW/EDIT =====

    enableEdit() {
      if (this.form.id) {
        window.location.href = `${BASE}/productos/partes/manager/${this.form.id}/editar`;
      }
    },

    disableEdit() {
      if (this.form.id) {
        window.location.href = `${BASE}/productos/partes/manager/${this.form.id}`;
      }
    },

    // ===== GESTIÓN DE VARIANTES =====

    resetVariantForm() {
      this.variantForm = {
        id: null,
        codigo_variante: '',
        detalle: '',
        estado: 'activa',
        lote_minimo: 1,
        punto_pedido: 0,
        stock_actual: 0,
        peso: null,
        id_um_peso: '',
        ubicacion_cuerpo: '',
        ubicacion_pasillo: '',
        ubicacion_estante: ''
      };

      this.$nextTick(() => this.adjustVariantDetalleHeight());
    },

    adjustVariantDetalleHeight() {
      const detalleEl = this.$refs.variantDetalle;
      if (!detalleEl) return;

      const codigoEl = this.$refs.variantCodigo;
      const fallbackMinHeight = 31;
      const minHeight = codigoEl
        ? Math.ceil(codigoEl.getBoundingClientRect().height)
        : fallbackMinHeight;

      detalleEl.style.height = 'auto';
      const nextHeight = Math.max(detalleEl.scrollHeight, minHeight);
      detalleEl.style.height = `${nextHeight}px`;
    },

    enableNewVariante() {
      this.resetVariantForm();
      this.isVariantFormEnabled = true;
    },

    async saveVariante() {
      if (!this.form.id) {
        alert('Primero debes guardar la parte');
        return;
      }

      const isUpdatingVariant = Boolean(this.variantForm.id);

      // Validar unidad de medida del peso.
      // Si la UM queda vacia (opcion "UM"), permitimos peso vacio.
      const rawPeso = this.variantForm.peso;
      const hasPeso = rawPeso !== null && rawPeso !== '' && !Number.isNaN(Number(rawPeso));
      const peso = hasPeso ? Number(rawPeso) : null;
      const unitPeso = this.variantForm.id_um_peso;
      const hasUnitPeso = unitPeso !== null && unitPeso !== '' && Number(unitPeso) > 0;

      if (hasPeso && !hasUnitPeso) {
        alert('El campo Peso tiene un valor pero no tiene unidad de medida seleccionada.\n\nPor favor, seleccione una unidad de medida o deje el valor en 0.');
        return;
      }

      if (!hasUnitPeso) {
        this.variantForm.peso = null;
        this.variantForm.id_um_peso = '';
      } else if (peso !== null) {
        this.variantForm.peso = peso;
        this.variantForm.id_um_peso = String(unitPeso);
      }

      this.loading = true;

      try {
        const url = this.variantForm.id
          ? `${BASE}/productos/partes/${this.form.id}/variantes/${this.variantForm.id}?context=manager`
          : `${BASE}/productos/partes/${this.form.id}/variantes?context=manager`;

        const method = this.variantForm.id ? 'PUT' : 'POST';
        const formData = new URLSearchParams();

        formData.append('id_parte', this.form.id);

        Object.keys(this.variantForm).forEach(key => {
          if (key !== 'id') {
            const value = this.variantForm[key];
            if (value !== null && value !== '') {
              formData.append(key, value);
            }
          }
        });

        if (method === 'PUT') {
          formData.append('_method', 'PUT');
        }

        // Add context for redirection
        formData.append('context', 'manager');

        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: formData
        });

        const managerViewUrl = `${BASE}/productos/partes/manager/${this.form.id}`;
        const managerVariantViewUrl = `${BASE}/productos/partes/manager/${this.form.id}/variantes/${this.variantForm.id}`;


        if (response.redirected) {
          window.location.href = isUpdatingVariant ? managerVariantViewUrl : response.url;
        } else if (response.ok) {
          if (isUpdatingVariant) {
            window.location.href = managerVariantViewUrl;
          } else {
            window.location.reload();
          }
        } else {
          alert('Error al guardar la variante');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar la variante: ' + error.message);
      } finally {
        this.loading = false;
      }
    },

    editVariante(variante) {
      if (this.form.id) {
        window.location.href = `${BASE}/productos/partes/manager/${this.form.id}/variantes/${variante.id}/editar`;
      }
    },

    loadVarianteIntoForm(variante) {
      this.variantForm = {
        id: variante.id,
        codigo_variante: variante.codigo_variante || '',
        detalle: variante.detalle || '',
        estado: variante.estado || 'activa',
        lote_minimo: this._toNumberWithDefault(variante.lote_minimo, 1),
        punto_pedido: this._toNumberWithDefault(variante.punto_pedido, 0),
        stock_actual: this._toNumberWithDefault(variante.stock_actual, 0),
        peso: this._toNullableNumber(variante.peso),
        id_um_peso: variante.id_um_peso || '',
        ubicacion_cuerpo: variante.ubicacion_cuerpo || '',
        ubicacion_pasillo: variante.ubicacion_pasillo || '',
        ubicacion_estante: variante.ubicacion_estante || ''
      };

      this.$nextTick(() => this.adjustVariantDetalleHeight());

      // Scroll al formulario
      const form = document.querySelector('form[\\@submit\\.prevent="saveVariante()"]');
      if (form) {
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    },

    cancelEditVariante() {
      // Si estamos editando una variante existente, volver al estado de vista de la parte.
      if (this.variantForm.id && this.form.id) {
        window.location.href = `${BASE}/productos/partes/manager/${this.form.id}/variantes/${this.variantForm.id}`;
        return;
      }

      this.resetVariantForm();

      // Al cancelar una nueva variante, volver al estado bloqueado del formulario.
      this.isVariantFormEnabled = false;
    },

    async deleteVariante(varianteId, index) {
      if (this.variantes.length <= 1) {
        alert('No se puede eliminar la ultima variante de una parte.');
        return;
      }

      try {
        const checkUrl = `${BASE}/api/v1/variantes/${this.form.id}/${varianteId}/can-delete`;
        const checkResponse = await fetch(checkUrl);
        const checkData = await checkResponse.json();

        if (!checkData.can_delete) {
          const errorList = checkData.errors.map(e => '• ' + e).join('\n');
          alert('No se puede eliminar la variante:\n\n' + errorList);
          return;
        }

        if (checkData.is_last_variant) {
          const confirmed = confirm(
            'Esta es la única variante de la parte "' + checkData.parte_codigo + '".\n' +
            'Al eliminarla se eliminará también la parte, ya que no puede existir una parte sin variantes.\n\n' +
            '¿Desea continuar?'
          );
          if (!confirmed) return;

          this.loading = true;
          const url = `${BASE}/productos/partes/${this.form.id}/variantes/${varianteId}?context=manager`;
          const formData = new URLSearchParams();
          formData.append('_method', 'DELETE');
          formData.append('context', 'manager');
          formData.append('force_delete_parte', '1');

          const response = await fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
          });

          if (response.redirected) {
            window.location.href = response.url;
          } else if (response.ok) {
            window.location.href = `${BASE}/productos/partes/manager`;
          } else {
            let errorMessage = 'Error al eliminar la variante';
            try {
              const payload = await response.json();
              if (payload && payload.message) {
                errorMessage = payload.message;
              }
            } catch (e) {
              // Ignore JSON parse failures and keep default message.
            }
            alert(errorMessage);
          }
          return;
        }
      } catch (error) {
        console.error('Error checking variant:', error);
        alert('Error al verificar la variante. Intente nuevamente.');
        return;
      }

      if (!confirm('¿Eliminar esta variante?')) return;

      this.loading = true;

      try {
        const url = `${BASE}/productos/partes/${this.form.id}/variantes/${varianteId}?context=manager`;
        const formData = new URLSearchParams();
        formData.append('_method', 'DELETE');
        formData.append('context', 'manager');

        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: formData
        });

        if (response.redirected) {
          window.location.href = response.url;
        } else if (response.ok) {
          // Eliminar del array local
          this.variantes.splice(index, 1);
        } else {
          let errorMessage = 'Error al eliminar la variante';
          try {
            const payload = await response.json();
            if (payload && payload.message) {
              errorMessage = payload.message;
            }
          } catch (e) {
            // Ignore JSON parse failures and keep default message.
          }
          alert(errorMessage);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar: ' + error.message);
      } finally {
        this.loading = false;
      }
    },

    // ===== HELPERS =====

    getEstadoLabel(estado) {
      const estados = {
        'activa': 'Activa',
        'desarrollo': 'En desarrollo',
        'obsoleta': 'Obsoleta',
        'descontinuada': 'Descontinuada'
      };
      return estados[estado] || estado;
    },

    getPesoUM(idUM) {
      if (!idUM || !this.unidadesMasa.length) return '';
      const unidad = this.unidadesMasa.find(u => u.id == idUM);
      return unidad ? unidad.simbolo : '';
    },

    getUmUsoSimbolo() {
      const umUsoId = this.form.id_um_uso;
      if (!umUsoId || !this.unidadesTodas.length) return '';
      const unidad = this.unidadesTodas.find(u => u.id == umUsoId);
      return unidad ? unidad.simbolo : '';
    }
  };
}
