document.addEventListener('DOMContentLoaded', function () {
  // ── árbol de menú (panel izquierdo) ────────────────────────────────────────
  const treeNodes  = Array.from(document.querySelectorAll('[data-tree-node]'));
  const menuInput  = document.getElementById('menu_item_id');
  const treeSearch = document.getElementById('menu-tree-search');

  treeNodes.forEach((node) => {
    node.addEventListener('click', () => {
      const nodeId = Number(node.dataset.nodeId || 0);
      if (nodeId <= 0 || !menuInput) return;
      menuInput.value = String(nodeId);
      treeNodes.forEach(n => n.classList.toggle('is-selected', n === node));
    });
  });

  if (treeSearch) {
    treeSearch.addEventListener('input', () => {
      const query = treeSearch.value.trim().toLowerCase();
      treeNodes.forEach((node) => {
        const text = (node.dataset.searchText || '').toLowerCase();
        const li   = node.closest('[data-tree-li]') || node;
        li.style.display = (query === '' || text.includes(query)) ? '' : 'none';
      });
      document.querySelectorAll('[data-tree-section]').forEach((section) => {
        section.style.display = section.querySelector('[data-tree-li]:not([style*="display: none"])') ? '' : 'none';
      });
    });
  }

  // ── form: guardar por AJAX ─────────────────────────────────────────────────
  const form      = document.getElementById('acl-form');
  const saveBtn   = form ? form.querySelector('button[type="submit"]') : null;
  const alertZone = document.getElementById('acl-alert-zone');

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const menuId = menuInput ? Number(menuInput.value) : 0;
      if (menuId <= 0) {
        showAlert('Selecciona primero un módulo del árbol.', 'warning');
        return;
      }

      const formData = new FormData(form);
      const body     = new URLSearchParams(formData);

      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';
      }

      fetch(typeof aclBulkUrl !== 'undefined' ? aclBulkUrl : form.action, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body:    body.toString(),
      })
        .then(r => r.json())
        .then(data => {
          if (data.ok) {
            showAlert('Permisos guardados correctamente.', 'success');
          } else {
            showAlert(data.error || 'Error al guardar permisos.', 'danger');
          }
        })
        .catch(() => showAlert('Error de red al guardar permisos.', 'danger'))
        .finally(() => {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-save"></i> Guardar Permisos';
          }
        });
    });
  }

  function showAlert(msg, type) {
    if (!alertZone) return;
    alertZone.className = `alert alert-${type} py-2 px-3 mb-0`;
    alertZone.textContent = msg;
    alertZone.style.display = '';
    clearTimeout(alertZone._timer);
    alertZone._timer = setTimeout(() => { alertZone.style.display = 'none'; }, 4000);
  }
});

/**
 * Selecciona un nodo del árbol ACL y carga los permisos guardados.
 * aclData, nodeTreeData y userRoleMap deben estar definidos globalmente.
 */
function selectNode(e, nodeId, label, code, isItem) {
  document.querySelectorAll('.acl-tree-node').forEach(el => el.classList.remove('is-selected'));
  if (e) e.currentTarget.classList.add('is-selected');

  document.getElementById('selected-node-title').textContent = label.toUpperCase();

  const badge = document.getElementById('selected-node-type');
  if (badge) {
    badge.textContent = isItem ? 'Menú Ítem' : 'Rama / Grupo';
    badge.className   = isItem ? 'badge bg-secondary ms-1' : 'badge bg-warning text-dark ms-1';
  }

  const numId    = parseInt(nodeId, 10);
  const menuInpt = document.getElementById('menu_item_id');
  if (menuInpt) menuInpt.value = isNaN(numId) ? '' : String(numId);

  // 1. Restablecer todos los roles a "allow" y usuarios a "inherit"
  document.querySelectorAll('select[name^="perms[role]"]').forEach(sel => {
    sel.value = 'allow';
    updateRowState(sel);
  });
  document.querySelectorAll('select[name^="perms[user]"]').forEach(sel => {
    sel.value = 'inherit';
    updateRowState(sel);
  });

  // 2. Cargar valores desde aclData para el nodo seleccionado
  if (!isNaN(numId) && typeof aclData !== 'undefined') {
    const nodeAcls = aclData.filter(row => parseInt(row.menu_item_id, 10) === numId);
    nodeAcls.forEach(acl => {
      const sel = document.querySelector(`select[name="perms[${acl.subject_type}][${acl.subject_id}]"]`);
      if (sel) {
        sel.value = (acl.permission_level === 'deny' || acl.effect === 'deny') ? 'deny' : 'allow';
        updateRowState(sel);
      }
    });
  }

  // 3. Cascada visual: actualizar usuarios con "inherit" según su rol
  document.querySelectorAll('select[name^="perms[role]"]').forEach(roleSelect => {
    const roleId = extractSubjectId(roleSelect.name);
    if (roleId > 0) propagateRoleToUsers(roleId, roleSelect.value);
  });

  const tbody = document.querySelector('tbody');
  if (tbody) {
    tbody.style.opacity = '0.3';
    setTimeout(() => { tbody.style.opacity = '1'; }, 250);
  }
}

/**
 * Llamado desde onchange de los selects de rol.
 * Propaga el nuevo permiso a los usuarios herededos de ese rol.
 */
function onRolePermChange(selectEl, roleId) {
  updateRowState(selectEl);
  propagateRoleToUsers(roleId, selectEl.value);
}

/**
 * Para todos los usuarios del rol dado que aún tienen "inherit",
 * actualiza el indicador visual mostrando el permiso del rol (atenuado).
 */
function propagateRoleToUsers(roleId, roleValue) {
  document.querySelectorAll(`tr[data-user-row][data-role-id="${roleId}"]`).forEach(row => {
    const sel = row.querySelector('select[name^="perms[user]"]');
    if (sel && sel.value === 'inherit') {
      updateRowStateInherited(sel, roleValue);
    }
  });
}

/**
 * Extrae el subject_id numérico del atributo name="perms[type][id]".
 */
function extractSubjectId(name) {
  const m = name.match(/\[(\d+)\]$/);
  return m ? parseInt(m[1], 10) : 0;
}

/**
 * Actualiza el indicador visual de estado en la fila de permiso.
 */
function updateRowState(selectEl) {
  const val      = selectEl.value;
  const rootRow  = selectEl.closest('tr');
  const indicator = rootRow ? rootRow.querySelector('.status-indicator') : null;

  selectEl.classList.remove('text-primary', 'text-danger', 'text-muted', 'fw-bold');

  if (val === 'allow') {
    selectEl.classList.add('text-primary', 'fw-bold');
    if (indicator) indicator.innerHTML = '<span class="badge bg-success rounded-pill"><i class="fa-solid fa-check"></i></span>';
  } else if (val === 'deny') {
    selectEl.classList.add('text-danger', 'fw-bold');
    if (indicator) indicator.innerHTML = '<span class="badge bg-danger rounded-pill"><i class="fa-solid fa-xmark"></i></span>';
  } else {
    // 'inherit': mostrar el estado del rol padre (se actualiza por propagateRoleToUsers)
    selectEl.classList.add('text-muted');
    if (indicator) indicator.innerHTML = '<span class="text-muted"><i class="fa-solid fa-minus"></i></span>';

    // Si hay un rol asignado, propagar el visual actual
    const row    = selectEl.closest('tr[data-user-row]');
    const roleId = row ? parseInt(row.dataset.roleId || '0', 10) : 0;
    if (roleId > 0) {
      const roleSel = document.querySelector(`select[name="perms[role][${roleId}]"]`);
      if (roleSel) updateRowStateInherited(selectEl, roleSel.value);
    }
  }
}

/**
 * Actualiza indicador de usuario heredado (atenuado) según el valor del rol.
 */
function updateRowStateInherited(selectEl, roleValue) {
  const rootRow   = selectEl.closest('tr');
  const indicator = rootRow ? rootRow.querySelector('.status-indicator') : null;
  if (!indicator) return;

  if (roleValue === 'deny') {
    indicator.innerHTML = '<span class="badge bg-danger opacity-50 rounded-pill" title="Denegado por rol"><i class="fa-solid fa-xmark"></i></span>';
  } else {
    indicator.innerHTML = '<span class="badge bg-success opacity-50 rounded-pill" title="Acceso por rol"><i class="fa-solid fa-check"></i></span>';
  }
}
