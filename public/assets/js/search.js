import { api } from './api.js';
import { el } from './dom.js';

const DEBOUNCE_MS = 250;

export class Search {
    constructor(input, list, { onSelect }) {
        this.input = input;
        this.list = list;
        this.onSelect = onSelect;
        this.results = [];
        this.active = -1;
        this.timer = null;
        this.request = null;

        input.addEventListener('input', () => this.schedule());
        input.addEventListener('keydown', (event) => this.onKey(event));
        input.addEventListener('focus', () => this.results.length && this.setOpen(true));
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.search')) this.setOpen(false);
        });
    }

    schedule() {
        clearTimeout(this.timer);
        const query = this.input.value.trim();
        if (query.length < 2) {
            this.render([]);
            return;
        }
        this.timer = setTimeout(() => this.run(query), DEBOUNCE_MS);
    }

    async run(query) {
        this.request?.abort();
        this.request = new AbortController();
        try {
            const { results } = await api.search(query, this.request.signal);
            this.render(results, query);
        } catch (error) {
            if (error.name !== 'AbortError') this.renderMessage(`Hledání selhalo: ${error.message}`);
        }
    }

    render(results, query = '') {
        this.results = results;
        this.active = results.length ? 0 : -1;
        if (!results.length) {
            query ? this.renderMessage('Nic nenalezeno. Zkus např. „Jičín 1854/42“ nebo „st. 120“.') : this.setOpen(false);
            return;
        }
        this.list.replaceChildren(...results.map((hit, index) => el('li', {
            role: 'option',
            id: `search-option-${index}`,
            'aria-selected': index === this.active ? 'true' : 'false',
            onmousedown: (event) => { event.preventDefault(); this.select(index); },
        },
        el('span', { class: `kind kind-${hit.type}` }, hit.type === 'parcel' ? 'parcela' : 'k.ú.'),
        el('strong', {}, hit.label),
        el('small', {}, hit.detail),
        )));
        this.setOpen(true);
    }

    renderMessage(message) {
        this.results = [];
        this.list.replaceChildren(el('li', { class: 'message' }, message));
        this.setOpen(true);
    }

    onKey(event) {
        if (event.key === 'Escape') {
            this.setOpen(false);
            return;
        }
        if (!this.results.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            this.active = (this.active + step + this.results.length) % this.results.length;
            [...this.list.children].forEach((item, index) => item.setAttribute('aria-selected', String(index === this.active)));
            this.input.setAttribute('aria-activedescendant', `search-option-${this.active}`);
        } else if (event.key === 'Enter' && this.active >= 0) {
            event.preventDefault();
            this.select(this.active);
        }
    }

    select(index) {
        const hit = this.results[index];
        if (!hit) return;
        this.setOpen(false);
        this.input.blur();
        this.onSelect(hit);
    }

    setOpen(open) {
        this.list.hidden = !open;
        this.input.setAttribute('aria-expanded', String(open));
    }
}
