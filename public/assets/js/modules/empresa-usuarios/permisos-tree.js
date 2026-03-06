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
