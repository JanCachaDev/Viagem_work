import { el, formatDate, formatNumber } from './dom.js';
import { LAND_TYPE_COLORS } from './map-style.js';

export class Legend {
    constructor(root, { landTypes, onChange }) {
        this.root = root;
        this.onChange = onChange;
        this.enabled = new Set(landTypes.map((type) => type.code));

        const list = root.querySelector('#legend-items');
        list.replaceChildren(...landTypes.map((type) => el('li', {},
            el('label', {},
                el('input', { type: 'checkbox', checked: true, onchange: (event) => this.toggle(type.code, event.target.checked) }),
                el('i', { class: 'swatch', style: { background: LAND_TYPE_COLORS[type.code] ?? '#bbb' } }),
                type.name,
            ),
        )));

        const toggle = root.querySelector('.legend-toggle');
        const collapsed = window.matchMedia('(max-width: 720px)').matches;
        this.setCollapsed(toggle, collapsed);
        toggle.addEventListener('click', () => this.setCollapsed(toggle, toggle.getAttribute('aria-expanded') === 'true'));
    }

    toggle(code, checked) {
        checked ? this.enabled.add(code) : this.enabled.delete(code);
        this.onChange([...this.enabled]);
    }

    setCollapsed(toggle, collapsed) {
        toggle.setAttribute('aria-expanded', String(!collapsed));
        this.root.classList.toggle('collapsed', collapsed);
    }

    showDataset(meta) {
        this.root.querySelector('#dataset-info').textContent =
            `${formatNumber(meta.parcels)} parcel · ${meta.cadastralAreas} k.ú. · ${meta.municipalities} obcí · data ČÚZK k ${formatDate(meta.datasetDate)}`;
    }
}
