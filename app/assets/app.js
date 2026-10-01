import './bootstrap';

import 'bootstrap/dist/js/bootstrap.bundle';

import './styles/app.scss';

import './js/sort-table';
import './js/contractor-validation';
import './js/operation-confirm-toggle';
import './js/rates-chart';
import './js/gold-prices-chart';

const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
const setTheme = (isDark) => {
    document.documentElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
};

setTheme(prefersDark.matches);
prefersDark.addEventListener('change', (event) => setTheme(event.matches));
