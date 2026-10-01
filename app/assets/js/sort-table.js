const initSortableTable = (selector) => {
  const table = document.querySelector(selector);
  if (!table) {
    return;
  }

  const tbody = table.querySelector('tbody');
  const headers = Array.from(table.querySelectorAll('thead th'));
  const sortState = new Map();

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

  headers.forEach((header, index) => {
    const type = header.dataset.sortKey || 'text';
    header.style.cursor = 'pointer';
    header.addEventListener('click', () => {
      const current = sortState.get(index) || 'asc';
      const next = current === 'asc' ? 'desc' : 'asc';
      sortState.clear();
      sortState.set(index, next);

      const rows = Array.from(tbody.querySelectorAll('tr'));
      rows.sort((a, b) => {
        const aVal = getCellValue(a, index, type);
        const bVal = getCellValue(b, index, type);
        if (aVal < bVal) return next === 'asc' ? -1 : 1;
        if (aVal > bVal) return next === 'asc' ? 1 : -1;
        return 0;
      });

      rows.forEach((row) => tbody.appendChild(row));
    });
  });
};

document.addEventListener('DOMContentLoaded', () => {
  initSortableTable('table[data-sortable="accounts"]');
});
