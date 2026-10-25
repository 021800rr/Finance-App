import './bootstrap';

import 'bootstrap/dist/js/bootstrap.bundle';

import 'bootstrap/dist/css/bootstrap.css';
import './styles/app.css';

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
