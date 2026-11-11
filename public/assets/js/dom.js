/** Vytvoří element; text se vkládá výhradně přes textContent (žádné innerHTML → žádné XSS). */
export function el(tag, attributes = {}, ...children) {
    const element = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes)) {
        if (value === null || value === undefined || value === false) continue;
        if (name === 'class') element.className = value;
        else if (name === 'style') Object.assign(element.style, value);
        else if (name.startsWith('on')) element.addEventListener(name.slice(2), value);
        else element.setAttribute(name, value === true ? '' : value);
    }
    for (const child of children.flat()) {
        if (child === null || child === undefined || child === false) continue;
        element.append(child instanceof Node ? child : document.createTextNode(String(child)));
    }
    return element;
}

const numberFormat = new Intl.NumberFormat('cs-CZ');
const czkFormat = new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK', maximumFractionDigits: 0 });
const dateFormat = new Intl.DateTimeFormat('cs-CZ', { dateStyle: 'medium' });

export function formatArea(m2) {
    const base = `${numberFormat.format(m2)} m²`;
    return m2 >= 10000 ? `${base} (${numberFormat.format(Math.round(m2 / 100) / 100)} ha)` : base;
}

export const formatNumber = (value) => numberFormat.format(value);
export const formatCzk = (value) => czkFormat.format(value);

export function formatDate(isoDate) {
    if (!isoDate) return '—';
    const normalized = /^\d{8}$/.test(isoDate) ? `${isoDate.slice(0, 4)}-${isoDate.slice(4, 6)}-${isoDate.slice(6)}` : isoDate;
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? isoDate : dateFormat.format(date);
}
