/**
 * empresa-delete.js
 * Modal de confirmacion con codigo alfanumerico para eliminar empresa (solo SuperAdmin).
 */
(function () {
  'use strict';

  const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

  function generateCode() {
    let code = '';
    const array = new Uint8Array(6);
    crypto.getRandomValues(array);
    for (let i = 0; i < 6; i++) {
      code += CHARSET[array[i] % CHARSET.length];
    }
    return code;
  }

  function init() {
    const modal = document.getElementById('modalEliminarEmpresa');
    if (!modal) return;

    const bsModal = new bootstrap.Modal(modal);
    const lblNombre = modal.querySelector('[data-delete-nombre]');
    const lblCodigo = modal.querySelector('[data-delete-codigo]');
    const inputCodigo = modal.querySelector('[data-delete-input]');
    const btnConfirm = modal.querySelector('[data-delete-confirm]');
    const form = modal.querySelector('[data-delete-form]');

    let currentCode = '';

    document.querySelectorAll('[data-delete-empresa]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const empresaId = btn.dataset.deleteEmpresa;
        const empresaNombre = btn.dataset.deleteNombre;

        currentCode = generateCode();

        if (lblNombre) lblNombre.textContent = empresaNombre;
        if (lblCodigo) lblCodigo.textContent = currentCode;
        if (inputCodigo) inputCodigo.value = '';
        if (btnConfirm) btnConfirm.disabled = true;

        if (form) {
          form.action = form.dataset.baseAction.replace('__ID__', empresaId);
        }

        bsModal.show();
      });
    });

    if (inputCodigo) {
      inputCodigo.addEventListener('input', function () {
        const match = inputCodigo.value.trim().toUpperCase() === currentCode;
        if (btnConfirm) btnConfirm.disabled = !match;
      });
    }

    modal.addEventListener('hidden.bs.modal', function () {
      if (inputCodigo) inputCodigo.value = '';
      if (btnConfirm) btnConfirm.disabled = true;
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
