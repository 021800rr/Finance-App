import { Controller } from '@hotwired/stimulus';

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

export default class extends Controller {
    static targets = ['input', 'results', 'hidden', 'message'];

    connect() {
        if (!this.hasInputTarget) {
            console.warn('Brak input target w contractor-autocomplete controller');
            return;
        }
        this.onInput = this.onInput.bind(this);
        this.onKeyDown = this.onKeyDown.bind(this);

        this.inputTarget.addEventListener('input', this.onInput);
        this.inputTarget.addEventListener('keydown', this.onKeyDown);

        if (this.hasResultsTarget) {
            this.resultsTarget.style.display = 'none';
            this.resultsTarget.innerHTML = '';
        }
        this.clearMessage();
    }

    disconnect() {
        if (this.hasInputTarget) {
            this.inputTarget.removeEventListener('input', this.onInput);
            this.inputTarget.removeEventListener('keydown', this.onKeyDown);
        }
    }

    onKeyDown(e) {
        if (e.key === 'Escape') {
            this.clearResults();
            this.inputTarget.blur();
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
        }
    }

    onInput() {
        const q = this.inputTarget.value.trim();
        if (this.hasHiddenTarget) {
            this.hiddenTarget.value = '';
        }
        this.clearMessage();
        if (q.length < 2) {
            this.clearResults();
            return;
        }
        this.search(q);
    }

    async search(q) {
        const base = this.element.dataset.searchUrl;
        if (!base) return;

        const url = base.includes('?') ? `${base}&q=${encodeURIComponent(q)}` : `${base}?q=${encodeURIComponent(q)}`;

        try {
            const res = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) {
                this.clearResults();
                return;
            }

            const data = await res.json().catch(() => []);
            this.renderResults(Array.isArray(data) ? data : [], q);
        } catch (err) {
            console.error(err);
            this.clearResults();
        }
    }

    renderResults(items = [], query = '') {
        if (!this.hasResultsTarget) return;

        this.resultsTarget.innerHTML = '';
        const ul = document.createElement('ul');
        ul.style.listStyle = 'none';
        ul.style.padding = '0';
        ul.style.margin = '6px 0 0 0';

        if (!items.length) {
            const addLi = document.createElement('li');
            addLi.className = 'add';
            addLi.style.padding = '8px';
            addLi.style.cursor = 'pointer';
            addLi.innerHTML = `➕ Dodaj „${this.escapeHtml(query)}”`;
            addLi.addEventListener('click', () => this.confirmAndCreate(query));
            ul.appendChild(addLi);
        } else {
            items.forEach(i => {
                const li = document.createElement('li');
                li.style.padding = '8px';
                li.style.cursor = 'pointer';
                li.dataset.id = i.id ?? '';
                li.textContent = i.name ?? '(brak nazwy)';
                li.addEventListener('click', () => this.onPick(li));
                ul.appendChild(li);
            });

            const exact = items.some(it => (it.name || '').toLowerCase() === query.toLowerCase());
            if (!exact) {
                const addLi = document.createElement('li');
                addLi.style.padding = '8px';
                addLi.style.cursor = 'pointer';
                addLi.innerHTML = `➕ Dodaj „${this.escapeHtml(query)}”`;
                addLi.addEventListener('click', () => this.confirmAndCreate(query));
                ul.appendChild(addLi);
            }
        }

        this.resultsTarget.appendChild(ul);
        this.resultsTarget.style.display = 'block';
    }

    onPick(li) {
        const id = li.dataset.id;
        const name = li.textContent || this.inputTarget.value;
        if (id) {
            if (this.hasHiddenTarget) this.hiddenTarget.value = id;
            this.inputTarget.value = name;
            this.clearResults();
            this.showMessage('success', `Wybrano kontrahenta: ${name}`);
            this.inputTarget.classList.remove('is-invalid');
            return;
        }
        this.confirmAndCreate(name);
    }

    clearResults() {
        if (!this.hasResultsTarget) return;
        this.resultsTarget.innerHTML = '';
        this.resultsTarget.style.display = 'none';
    }

    escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
        });
    }

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token-contractor-create"]');
        if (meta && meta.content) return meta.content;
        return getCookie('XSRF-TOKEN') || getCookie('XSRF_TOKEN') || '';
    }

    confirmAndCreate(name) {
        if (!name) return;
        if (!window.confirm(`Dodać nowego kontrahenta „${name}”?`)) {
            return;
        }
        this.createContractor(name);
    }

    showMessage(type, text) {
        if (!this.hasMessageTarget) return;
        this.messageTarget.textContent = text;
        this.messageTarget.classList.remove('text-danger', 'text-success', 'text-muted');
        this.messageTarget.classList.add(type === 'success' ? 'text-success' : 'text-danger');
    }

    clearMessage() {
        if (!this.hasMessageTarget) return;
        this.messageTarget.textContent = '';
        this.messageTarget.classList.remove('text-danger', 'text-success', 'text-muted');
    }

    async createContractor(name) {
        if (!name) {
            this.showMessage('error', 'Nazwa jest pusta.');
            return;
        }
        const createUrl = this.element.dataset.createUrl;
        if (!createUrl) {
            this.showMessage('error', 'Brak adresu tworzenia kontrahenta.');
            return;
        }

        const token = this.getCsrfToken();

        try {
            const res = await fetch(createUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': token || ''
                },
                body: JSON.stringify({ name })
            });

            if (!res.ok) {
                const text = await res.text().catch(() => `${res.status}`);
                console.warn('createContractor failed', res.status, text);
                this.showMessage('error', 'Błąd przy tworzeniu kontrahenta.');
                return;
            }

            const data = await res.json().catch(() => null);
            if (data && data.id) {
                if (this.hasHiddenTarget) this.hiddenTarget.value = data.id;
                this.inputTarget.value = data.name || name;
                this.inputTarget.classList.remove('is-invalid');
                this.showMessage('success', `Dodano kontrahenta: ${data.name || name}`);
                this.clearResults();
                return;
            }

            this.showMessage('success', 'Dodano kontrahenta, ale serwer nie zwrócił ID.');
        } catch (err) {
            console.error(err);
            this.showMessage('error', 'Błąd sieci przy tworzeniu kontrahenta.');
        }
    }
}
