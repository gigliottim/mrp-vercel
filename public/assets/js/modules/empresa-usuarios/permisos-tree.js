document.addEventListener('DOMContentLoaded', function () {
  // ── árbol de menú (panel izquierdo) ────────────────────────────────────────
  const treeNodes = Array.from(document.querySelectorAll('[data-tree-node]'));
  const menuInput = document.getElementById('menu_item_id');
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
        const li = node.closest('[data-tree-li]') || node;
        li.style.display = (query === '' || text.includes(query)) ? '' : 'none';
      });
      document.querySelectorAll('[data-tree-section]').forEach((section) => {
        section.style.display = section.querySelector('[data-tree-li]:not([style*="display: none"])') ? '' : 'none';
      });
    });
  }

  // ── form: guardar por AJAX ─────────────────────────────────────────────────
  const form = document.getElementById('acl-form');
  const saveBtn = form ? form.querySelector('button[type="submit"]') : null;
  const alertZone = document.getElementById('acl-alert-zone');

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      // Determinar los IDs a guardar: propagación a hijos o nodo individual
      const propagateIds = window._aclPropagateIds || [];
      const menuId = menuInput ? Number(menuInput.value) : 0;

      if (propagateIds.length === 0 && menuId <= 0) {
        showAlert('Selecciona primero un módulo del árbol.', 'warning');
        return;
      }

      const formData = new FormData(form);
      const body = new URLSearchParams(formData);

      // Si hay IDs de propagación, reemplazar menu_item_id por menu_item_ids[]
      if (propagateIds.length > 0) {
        body.delete('menu_item_id');
        propagateIds.forEach(id => body.append('menu_item_ids[]', String(id)));
      }

      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';
      }

      const savedIds = propagateIds.length > 0 ? propagateIds : (menuId > 0 ? [menuId] : []);

      fetch(typeof aclBulkUrl !== 'undefined' ? aclBulkUrl : form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: body.toString(),
      })
        .then(r => r.json())
        .then(data => {
          if (data.ok) {
            showAlert(
              savedIds.length > 1
                ? `Permisos guardados en ${savedIds.length} módulos.`
                : 'Permisos guardados correctamente.',
              'success'
            );
            // Sincronizar aclData en memoria para todos los IDs guardados
            if (typeof aclData !== 'undefined') {
              savedIds.forEach(savedMenuId => {
                // 1. Eliminar entradas antiguas de este nodo
                for (let i = aclData.length - 1; i >= 0; i--) {
                  if (parseInt(aclData[i].menu_item_id, 10) === savedMenuId) {
                    aclData.splice(i, 1);
                  }
                }
                // 2. Insertar las entradas actuales (allow/deny); 'inherit' no genera registro
                form.querySelectorAll('select[name^="perms["]').forEach(sel => {
                  const val = sel.value;
                  if (val === 'inherit') return;
                  const match = sel.name.match(/^perms\[(\w+)\]\[(\d+)\]$/);
                  if (!match) return;
                  aclData.push({
                    menu_item_id: String(savedMenuId),
                    subject_type: match[1],
                    subject_id: match[2],
                    scope: 'item',
                    permission_level: val === 'deny' ? 'deny' : 'read',
                    effect: val === 'deny' ? 'deny' : 'allow',
                  });
                });
              });
            }
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
 * Devuelve todos los IDs del subárbol con raíz en rootId (inclusive).
 */
function getSubtreeIds(rootId) {
  const tree = typeof nodeTreeData !== 'undefined' ? nodeTreeData : {};
  const ids = [rootId];
  Object.values(tree).forEach(n => {
    if (n.parent_id === rootId) ids.push(...getSubtreeIds(n.id));
  });
  return ids;
}

/**
 * Devuelve todos los IDs de los nodos pertenecientes a una sección.
 */
function getSectionIds(sectionKey) {
  const tree = typeof nodeTreeData !== 'undefined' ? nodeTreeData : {};
  const ids = [];
  Object.values(tree).forEach(n => {
    if ((n.section_key === sectionKey) && (!tree[n.parent_id])) {
      ids.push(...getSubtreeIds(n.id));
    }
  });
  return ids;
}

/**
 * Selecciona un nodo del árbol ACL y carga los permisos guardados.
 * aclData, nodeTreeData y userRoleMap deben estar definidos globalmente.
 */
function selectNode(e, nodeId, label, code, isItem) {
  document.querySelectorAll('.acl-tree-node').forEach(el => el.classList.remove('is-selected'));
  if (e) e.currentTarget.classList.add('is-selected');

  document.getElementById('selected-node-title').textContent = label.toUpperCase();

  const badge = document.getElementById('selected-node-type');

  // Detectar si es un nodo de sección (string 'section:key') o rama con ID numérico
  const isSectionNode = typeof nodeId === 'string' && nodeId.startsWith('section:');
  const numId = isSectionNode ? NaN : parseInt(nodeId, 10);

  // Calcular IDs de propagación para padres/secciones
  if (isSectionNode) {
    const sectionKey = String(nodeId).replace(/^section:/, '');
    window._aclPropagateIds = getSectionIds(sectionKey);
  } else if (!isItem && !isNaN(numId) && numId > 0) {
    window._aclPropagateIds = getSubtreeIds(numId);
  } else {
    window._aclPropagateIds = [];
  }

  const isPropagating = window._aclPropagateIds.length > 0;

  if (badge) {
    if (isSectionNode) {
      badge.textContent = `Sección (${window._aclPropagateIds.length} módulos)`;
      badge.className = 'badge bg-warning text-dark ms-1';
    } else if (isPropagating) {
      badge.textContent = `Rama (${window._aclPropagateIds.length} módulos)`;
      badge.className = 'badge bg-warning text-dark ms-1';
    } else {
      badge.textContent = isItem ? 'Menú Ítem' : 'Rama / Grupo';
      badge.className = isItem ? 'badge bg-secondary ms-1' : 'badge bg-warning text-dark ms-1';
    }
  }

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
  } else if (isSectionNode && typeof aclData !== 'undefined' && window._aclPropagateIds.length > 0) {
    const propagateIds = window._aclPropagateIds;

    // ── Roles: consenso directo desde aclData (deny gana) ──────────────────
    const roleConsensus = new Map(); // roleId → {hasAllow, hasDeny}
    aclData.forEach(row => {
      if (row.subject_type !== 'role') return;
      if (!propagateIds.includes(parseInt(row.menu_item_id, 10))) return;
      const rId = parseInt(row.subject_id, 10);
      const isDeny = (row.permission_level === 'deny' || row.effect === 'deny');
      const cur = roleConsensus.get(rId) || { hasAllow: false, hasDeny: false };
      if (isDeny) cur.hasDeny = true; else cur.hasAllow = true;
      roleConsensus.set(rId, cur);
    });
    roleConsensus.forEach(({ hasDeny }, roleId) => {
      const sel = document.querySelector(`select[name="perms[role][${roleId}]"]`);
      if (sel) { sel.value = hasDeny ? 'deny' : 'allow'; updateRowState(sel); }
    });

    // ── Usuarios: permiso efectivo considerando herencia de rol ─────────────
    // Para cada nodo del subárbol: si hay excepción explícita del usuario → úsala;
    // si no, heredar del permiso del rol en ese nodo (o 'allow' por defecto).
    document.querySelectorAll('select[name^="perms[user]"]').forEach(userSel => {
      const userId = extractSubjectId(userSel.name);
      if (userId <= 0) return;
      const roleId = (typeof userRoleMap !== 'undefined') ? (userRoleMap[userId] || 0) : 0;
      let hasAllow = false, hasDeny = false;

      propagateIds.forEach(nodeId => {
        // 1. ¿Excepción explícita del usuario en este nodo?
        const userRow = aclData.find(r =>
          parseInt(r.menu_item_id, 10) === nodeId &&
          r.subject_type === 'user' &&
          parseInt(r.subject_id, 10) === userId
        );
        let val;
        if (userRow) {
          val = (userRow.permission_level === 'deny' || userRow.effect === 'deny') ? 'deny' : 'allow';
        } else if (roleId > 0) {
          // 2. Permiso del rol en este nodo
          const roleRow = aclData.find(r =>
            parseInt(r.menu_item_id, 10) === nodeId &&
            r.subject_type === 'role' &&
            parseInt(r.subject_id, 10) === roleId
          );
          val = roleRow
            ? ((roleRow.permission_level === 'deny' || roleRow.effect === 'deny') ? 'deny' : 'allow')
            : 'allow'; // sin registro de rol = acceso por defecto
        } else {
          val = 'allow';
        }
        if (val === 'deny') hasDeny = true; else hasAllow = true;
      });

      if (hasAllow && hasDeny) {
        userSel.value = 'inherit';
        updateRowStateMixed(userSel);
      } else if (hasDeny) {
        userSel.value = 'deny';
        updateRowState(userSel);
      } else {
        // Solo allow: mostrar excepción explícita si la hay, si no usa rol
        const hasExplicit = aclData.some(r =>
          propagateIds.includes(parseInt(r.menu_item_id, 10)) &&
          r.subject_type === 'user' &&
          parseInt(r.subject_id, 10) === userId
        );
        userSel.value = hasExplicit ? 'allow' : 'inherit';
        updateRowState(userSel);
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
    if (sel && sel.value === 'inherit' && sel.dataset.mixed !== '1') {
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
  const val = selectEl.value;
  const rootRow = selectEl.closest('tr');
  const indicator = rootRow ? rootRow.querySelector('.status-indicator') : null;

  selectEl.classList.remove('text-primary', 'text-danger', 'text-muted', 'fw-bold');
  delete selectEl.dataset.mixed;

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
    const row = selectEl.closest('tr[data-user-row]');
    const roleId = row ? parseInt(row.dataset.roleId || '0', 10) : 0;
    if (roleId > 0) {
      const roleSel = document.querySelector(`select[name="perms[role][${roleId}]"]`);
      if (roleSel) updateRowStateInherited(selectEl, roleSel.value);
    }
  }
}

/**
 * Muestra badge naranja "Mixto" cuando un usuario tiene permisos distintos en distintos hijos.
 */
function updateRowStateMixed(selectEl) {
  const rootRow = selectEl.closest('tr');
  const indicator = rootRow ? rootRow.querySelector('.status-indicator') : null;
  selectEl.classList.remove('text-primary', 'text-danger', 'text-muted', 'fw-bold');
  selectEl.classList.add('text-muted');
  selectEl.dataset.mixed = '1';
  if (indicator) {
    indicator.innerHTML = '<span class="badge rounded-pill text-dark" style="background:#fd7e14" title="Permisos mixtos en esta sección"><i class="fa-solid fa-shuffle me-1"></i>Mixto</span>';
  }
}

/**
 * Actualiza indicador de usuario heredado (atenuado) según el valor del rol.
 */
function updateRowStateInherited(selectEl, roleValue) {
  const rootRow = selectEl.closest('tr');
  const indicator = rootRow ? rootRow.querySelector('.status-indicator') : null;
  if (!indicator) return;

  if (roleValue === 'deny') {
    indicator.innerHTML = '<span class="badge bg-danger opacity-50 rounded-pill" title="Denegado por rol"><i class="fa-solid fa-xmark"></i></span>';
  } else {
    indicator.innerHTML = '<span class="badge bg-success opacity-50 rounded-pill" title="Acceso por rol"><i class="fa-solid fa-check"></i></span>';
  }
}
