document.addEventListener('DOMContentLoaded', function () {
  const treeNodes = Array.from(document.querySelectorAll('[data-tree-node]'));
  const menuInput = document.getElementById('menu_item_id');
  const selectedMenuNode = document.getElementById('selected-menu-node');
  const treeSearch = document.getElementById('menu-tree-search');
  const subjectType = document.getElementById('subject_type');
  const subjectRef = document.getElementById('subject_ref');
  const subjectId = document.getElementById('subject_id');

  const setSelectedNode = (id) => {
    let found = false;

    treeNodes.forEach((node) => {
      const nodeId = Number(node.dataset.nodeId || 0);
      const isMatch = nodeId === Number(id);
      node.classList.toggle('is-selected', isMatch);

      if (isMatch && selectedMenuNode) {
        const label = node.dataset.nodeLabel || '';
        const code = node.dataset.nodeCode || '';
        selectedMenuNode.textContent = code ? `${label} (${code})` : label;
        found = true;
      }
    });

    if (!found && selectedMenuNode) {
      selectedMenuNode.textContent = 'Ninguno';
    }
  };

  treeNodes.forEach((node) => {
    node.addEventListener('click', () => {
      const nodeId = Number(node.dataset.nodeId || 0);
      if (nodeId <= 0 || !menuInput) {
        return;
      }

      menuInput.value = String(nodeId);
      setSelectedNode(nodeId);
    });
  });

  if (menuInput && menuInput.value !== '') {
    setSelectedNode(Number(menuInput.value));
  }

  if (treeSearch) {
    treeSearch.addEventListener('input', () => {
      const query = treeSearch.value.trim().toLowerCase();

      treeNodes.forEach((node) => {
        const text = (node.dataset.searchText || '').toLowerCase();
        const visible = query === '' || text.includes(query);
        const nodeContainer = node.closest('[data-tree-li]') || node;
        nodeContainer.style.display = visible ? '' : 'none';
      });

      const sections = Array.from(document.querySelectorAll('[data-tree-section]'));
      sections.forEach((section) => {
        const hasVisibleNode = section.querySelector('[data-tree-li]:not([style*="display: none"])');
        section.style.display = hasVisibleNode ? '' : 'none';
      });
    });
  }

  const syncSubjectPicker = () => {
    if (!subjectType || !subjectRef) {
      return;
    }

    const typeValue = subjectType.value;
    Array.from(subjectRef.options).forEach((option) => {
      const optionType = option.dataset.subjectType || '';
      if (option.value === '') {
        option.hidden = false;
        return;
      }
      option.hidden = optionType !== typeValue;
    });

    const selected = subjectRef.selectedOptions[0];
    if (selected && !selected.hidden && selected.dataset.subjectId && subjectId) {
      subjectId.value = selected.dataset.subjectId;
    }
  };

  if (subjectRef && subjectId) {
    subjectRef.addEventListener('change', () => {
      const selected = subjectRef.selectedOptions[0];
      if (!selected) {
        return;
      }

      const nextType = selected.dataset.subjectType || '';
      const nextId = selected.dataset.subjectId || '';

      if (nextType !== '' && subjectType && subjectType.value !== nextType) {
        subjectType.value = nextType;
      }
      if (nextId !== '') {
        subjectId.value = nextId;
      }

      syncSubjectPicker();
    });
  }

  if (subjectType) {
    subjectType.addEventListener('change', syncSubjectPicker);
    syncSubjectPicker();
  }
});

/**
 * Selecciona un nodo del árbol ACL y carga los permisos guardados.
 * aclData debe estar definida globalmente antes de cargar este script.
 *
 * @param {Event|null} e
 * @param {number|string} nodeId
 * @param {string} label
 * @param {string} code
 * @param {boolean} isItem
 */
function selectNode(e, nodeId, label, code, isItem) {
  document.querySelectorAll('.acl-tree-node').forEach(el => el.classList.remove('is-selected'));
  if (e) {
    e.currentTarget.classList.add('is-selected');
  }

  document.getElementById('selected-node-title').textContent = label.toUpperCase();

  const badge = document.getElementById('selected-node-type');
  if (isItem) {
    badge.textContent = 'Menú Ítem';
    badge.className = 'badge bg-secondary ms-1';
  } else {
    badge.textContent = 'Rama / Grupo';
    badge.className = 'badge bg-warning text-dark ms-1';
  }

  const numId = parseInt(nodeId, 10);
  if (!isNaN(numId)) {
    document.getElementById('menu_item_id').value = numId;
  } else {
    document.getElementById('menu_item_id').value = '';
  }

  const defaultVal = isItem ? 'none' : 'allow';
  document.querySelectorAll('select[name^="perms["]').forEach(select => {
    select.value = defaultVal;
    updateRowState(select);
  });

  if (!isNaN(numId) && typeof aclData !== 'undefined') {
    const nodeAcls = aclData.filter(row => parseInt(row.menu_item_id, 10) === numId);
    nodeAcls.forEach(acl => {
      const sel = document.querySelector(`select[name="perms[${acl.subject_type}][${acl.subject_id}]"]`);
      if (sel) {
        sel.value = acl.permission_level;
        updateRowState(sel);
      }
    });
  }

  // Actualizar el texto de la opción "Heredada" con el permiso del padre
  document.querySelectorAll('select[name^="perms["]').forEach(select => {
    const match = select.name.match(/^perms\[(\w+)\]\[(\d+)\]$/);
    if (match && !isNaN(numId)) {
      const inherited = resolveInheritedPermission(numId, match[1], match[2]);
      select.options[0].textContent = `Heredada (${inherited === 'deny' ? 'Denegar' : 'Acceder'})`;
    } else {
      select.options[0].textContent = 'Heredada (Acceder)';
    }
  });

  const tbody = document.querySelector('tbody');
  tbody.style.opacity = '0.3';
  setTimeout(() => { tbody.style.opacity = '1'; }, 300);
}

/**
 * Resuelve el permiso heredado para un sujeto recorriendo los ancestros del nodo.
 * Requiere que nodeTreeData y aclData estén definidos globalmente.
 *
 * @param {number} nodeId  - ID del nodo actualmente seleccionado
 * @param {string} subjectType - 'role' | 'user'
 * @param {string|number} subjectId
 * @returns {'allow'|'deny'}
 */
function resolveInheritedPermission(nodeId, subjectType, subjectId) {
  if (typeof nodeTreeData === 'undefined' || typeof aclData === 'undefined') {
    return 'allow';
  }
  const sid = parseInt(subjectId, 10);
  let currentId = Number(nodeTreeData[nodeId]?.parent_id ?? 0);
  while (currentId > 0) {
    const acl = aclData.find(row =>
      parseInt(row.menu_item_id, 10) === currentId &&
      row.subject_type === subjectType &&
      parseInt(row.subject_id, 10) === sid &&
      row.permission_level !== 'none'
    );
    if (acl) {
      return acl.permission_level;
    }
    currentId = Number(nodeTreeData[currentId]?.parent_id ?? 0);
  }
  return 'allow';
}

/**
 * Actualiza el indicador visual de estado en la fila de permiso.
 *
 * @param {HTMLSelectElement} selectElement
 */
function updateRowState(selectElement) {
  const val = selectElement.value;
  const rootRow = selectElement.closest('tr');
  const indicator = rootRow.querySelector('.status-indicator');

  selectElement.classList.remove('text-primary', 'text-danger', 'text-muted', 'fw-bold');

  if (val === 'allow') {
    selectElement.classList.add('text-primary', 'fw-bold');
    indicator.innerHTML = '<span class="badge bg-success rounded-pill"><i class="fa-solid fa-check"></i></span>';
  } else if (val === 'deny') {
    selectElement.classList.add('text-danger', 'fw-bold');
    indicator.innerHTML = '<span class="badge bg-danger rounded-pill"><i class="fa-solid fa-xmark"></i></span>';
  } else {
    selectElement.classList.add('text-muted');
    indicator.innerHTML = '<span class="text-muted"><i class="fa-solid fa-minus"></i></span>';
  }
}
