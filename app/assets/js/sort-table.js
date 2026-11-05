// The arrows of a column: empty while the table is not sorted by it, filled for the current order (like ☆/★).
const ARROWS = {
  asc: { empty: '△', filled: '▲' },
  desc: { empty: '▽', filled: '▼' },
};

const initSortableTable = (selector) => {
  const table = document.querySelector(selector);
  if (!table) {
    return;
  }

  const tbody = table.querySelector('tbody');
  const headers = Array.from(table.querySelectorAll('thead th'));
  const sortState = new Map();
  const arrowButtons = [];

  const normalizeNumber = (value) =>
    parseFloat(value.replace(/\s/g, '').replace(',', '.')) || 0;

  const getCellValue = (row, index, type) => {
    const cell = row.children[index];
    if (!cell) {
      return '';
    }
    const text = cell.textContent.trim();
    return type === 'number' ? normalizeNumber(text) : text.toLowerCase();
  };

  const showSortState = () => {
    arrowButtons.forEach(({ button, index, direction }) => {
      const active = sortState.get(index) === direction;
      button.textContent = active ? ARROWS[direction].filled : ARROWS[direction].empty;
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };

  const sortBy = (index, type, direction) => {
    sortState.clear();
    sortState.set(index, direction);

    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
      const aVal = getCellValue(a, index, type);
      const bVal = getCellValue(b, index, type);
      if (aVal < bVal) return direction === 'asc' ? -1 : 1;
      if (aVal > bVal) return direction === 'asc' ? 1 : -1;
      return 0;
    });

    rows.forEach((row) => tbody.appendChild(row));
    showSortState();
  };

  const createArrow = (index, type, direction) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sort-arrow';
    const label = direction === 'asc' ? table.dataset.sortAscLabel : table.dataset.sortDescLabel;
    button.setAttribute('aria-label', label ?? '');
    button.addEventListener('click', (event) => {
      // An arrow sets the order; it must not also reach the header, whose click toggles it.
      event.stopPropagation();
      sortBy(index, type, direction);
    });
    arrowButtons.push({ button, index, direction });

    return button;
  };

  headers.forEach((header, index) => {
    const type = header.dataset.sortKey || 'text';
    header.style.cursor = 'pointer';
    header.addEventListener('click', () => {
      const current = sortState.get(index) || 'asc';
      sortBy(index, type, current === 'asc' ? 'desc' : 'asc');
    });

    if (header.dataset.sortKey) {
      const arrows = document.createElement('span');
      arrows.className = 'sort-arrows';
      arrows.appendChild(createArrow(index, type, 'asc'));
      arrows.appendChild(createArrow(index, type, 'desc'));
      header.appendChild(arrows);
    }
  });

  showSortState();
};

document.addEventListener('DOMContentLoaded', () => {
  initSortableTable('table[data-sortable="accounts"]');
});
