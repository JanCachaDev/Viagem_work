import { api } from './api.js';
import { el, formatArea, formatCzk, formatDate } from './dom.js';
import { LAND_TYPE_COLORS } from './map-style.js';

export class DetailPanel {
    constructor(root, { onClose, onZoomTo }) {
        this.root = root;
        this.content = root.querySelector('#detail-content');
        this.onZoomTo = onZoomTo;
        this.request = null;
        this.handle = root.querySelector('.detail-handle');
        root.querySelector('.detail-close').addEventListener('click', () => onClose());
        this.handle.addEventListener('click', () => this.setExpanded(!root.classList.contains('expanded')));
    }

    isOpen() {
        return !this.root.hidden;
    }

    /** Na mobilu je detail spodní panel: sbalený ukazuje hlavní údaje, rozbalený celý obsah. */
    setExpanded(expanded) {
        this.root.classList.toggle('expanded', expanded);
        this.handle.setAttribute('aria-expanded', String(expanded));
        this.handle.setAttribute('aria-label', expanded ? 'Sbalit detail' : 'Rozbalit detail');
    }

    async show(id) {
        this.request?.abort();
        this.request = new AbortController();
        this.setExpanded(false);
        this.root.scrollTop = 0;
        this.open();
        this.content.replaceChildren(el('p', { class: 'muted' }, 'Načítám parcelu…'));

        try {
            const parcel = await api.parcel(id, this.request.signal);
            this.content.replaceChildren(...this.render(parcel).filter(Boolean));
            return parcel;
        } catch (error) {
            if (error.name === 'AbortError') return null;
            this.content.replaceChildren(el('p', { class: 'error' }, `Parcelu se nepodařilo načíst: ${error.message}`));
            return null;
        }
    }

    open() {
        this.root.hidden = false;
        document.body.classList.add('detail-open');
    }

    close() {
        this.request?.abort();
        this.root.hidden = true;
        document.body.classList.remove('detail-open');
    }

    render(parcel) {
        const color = LAND_TYPE_COLORS[parcel.landType.code] ?? '#bbb';
        const rows = [
            ['Katastrální území', `${parcel.cadastralArea.name} (${parcel.cadastralArea.code})`],
            ['Obec', parcel.municipality.name],
            ['Výměra', formatArea(parcel.area)],
            ['Druh pozemku', el('span', { class: 'swatch-label' }, el('i', { class: 'swatch', style: { background: color } }), parcel.landType.name)],
            ['Způsob využití', parcel.landUse?.name ?? '—'],
            ['Druh číslování', parcel.buildingPlot ? 'stavební parcela' : 'pozemková parcela'],
            ['Platí od', formatDate(parcel.validFrom)],
        ];

        const liveBox = el('div', { class: 'live' });
        const liveButton = el('button', { type: 'button', class: 'button secondary', onclick: () => this.checkLive(parcel, liveBox, liveButton) },
            'Ověřit v aktuálním katastru');

        return [
            el('p', { class: 'eyebrow' }, `k.ú. ${parcel.cadastralArea.name}`),
            el('h2', {}, `Parcela ${parcel.number}`),
            el('dl', { class: 'facts' }, rows.flatMap(([label, value]) => [el('dt', {}, label), el('dd', {}, value)])),
            this.renderProtections(parcel.protections),
            this.renderBpej(parcel),
            el('div', { class: 'actions' },
                el('a', { class: 'button', href: parcel.links.nahlizeniDoKn, target: '_blank', rel: 'noopener noreferrer' }, 'Nahlížení do KN ↗'),
                liveButton,
                el('button', { type: 'button', class: 'button secondary', onclick: () => this.onZoomTo(parcel.bbox) }, 'Přiblížit'),
            ),
            liveBox,
        ];
    }

    renderProtections(protections) {
        if (!protections.length) return null;
        return el('section', {},
            el('h3', {}, 'Způsob ochrany'),
            el('ul', { class: 'tags' }, protections.map((name) => el('li', {}, name))),
        );
    }

    renderBpej(parcel) {
        if (!parcel.bpej.length) return null;
        const rows = parcel.bpej.map((part) => el('tr', {},
            el('td', {}, part.code),
            el('td', { class: 'num' }, formatArea(part.area)),
            el('td', { class: 'num' }, part.pricePerM2 === null ? '—' : `${part.pricePerM2.toFixed(2)} Kč`),
        ));

        return el('section', {},
            el('h3', {}, 'Bonitace půdy (BPEJ)'),
            el('table', { class: 'bpej' },
                el('thead', {}, el('tr', {}, el('th', {}, 'Kód'), el('th', { class: 'num' }, 'Výměra'), el('th', { class: 'num' }, 'Kč/m²'))),
                el('tbody', {}, rows),
            ),
            parcel.estimatedOfficialPrice === null ? null : el('p', { class: 'price' },
                'Orientační úřední cena: ', el('strong', {}, formatCzk(parcel.estimatedOfficialPrice)),
                el('small', {}, 'Výměra × cena BPEJ dle číselníku ČÚZK. Nejde o tržní cenu.'),
            ),
        );
    }

    async checkLive(parcel, box, button) {
        button.disabled = true;
        box.replaceChildren(el('p', { class: 'muted' }, 'Dotazuji WFS ČÚZK…'));
        try {
            const result = await api.live(parcel.id);
            if (result.upToDate) {
                box.replaceChildren(el('p', { class: 'ok' },
                    `✓ Údaje odpovídají aktuálnímu katastru (stav platí od ${formatDate(result.live.validSince)}).`));
            } else {
                box.replaceChildren(
                    el('p', { class: 'warn' }, 'Od importu dat došlo ke změně:'),
                    el('ul', {}, result.differences.map((difference) => el('li', {}, difference))),
                );
            }
        } catch (error) {
            box.replaceChildren(el('p', { class: 'error' }, `Ověření selhalo: ${error.message}`));
        } finally {
            button.disabled = false;
        }
    }
}
