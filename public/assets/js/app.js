import { api } from './api.js';
import { DetailPanel } from './detail.js';
import { Legend } from './legend.js';
import { BASEMAPS, buildStyle } from './map-style.js';
import { Search } from './search.js';

const PARCEL_LAYERS = ['parcels-fill', 'parcels-outline', 'parcel-numbers'];
const FULL_DETAIL_ZOOM = 14;
const isMobile = () => window.matchMedia('(max-width: 720px)').matches;

const meta = await api.meta();
const bbox = meta.bbox ?? [15.0, 50.2, 15.8, 50.6];

const map = new maplibregl.Map({
    container: 'map',
    style: buildStyle({ origin: window.location.origin, bbox, maxZoom: meta.tiles.maxZoom }),
    bounds: bbox,
    fitBoundsOptions: { padding: 40 },
    maxBounds: [[bbox[0] - 0.5, bbox[1] - 0.3], [bbox[2] + 0.5, bbox[3] + 0.3]],
    minZoom: 8,
    maxZoom: 20,
    hash: 'mapa',
    dragRotate: false,
    pitchWithRotate: false,
    attributionControl: { compact: true },
});
map.touchZoomRotate.disableRotation();
map.on('error', (e) => console.error('maplibre', e.error?.message ?? e));
map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'bottom-right');
map.addControl(new maplibregl.GeolocateControl({ fitBoundsOptions: { maxZoom: 17 } }), 'bottom-right');
map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-right');

const fitBox = (box) => map.fitBounds(box, {
    padding: isMobile()
        ? { top: 90, bottom: window.innerHeight * 0.55, left: 20, right: 20 }
        : { top: 90, bottom: 40, left: 40, right: 420 },
    maxZoom: 18,
    duration: 700,
});

const detail = new DetailPanel(document.getElementById('detail'), {
    onClose: () => selectParcel(null),
    onZoomTo: fitBox,
});

async function selectParcel(id, { zoom = false } = {}) {
    const selection = map.getSource('selection');
    if (id === null) {
        detail.close();
        selection?.setData({ type: 'FeatureCollection', features: [] });
        history.replaceState(null, '', window.location.pathname + window.location.hash);
        return;
    }
    const parcel = await detail.show(id);
    if (!parcel) return;
    selection.setData({ type: 'Feature', geometry: parcel.geometry, properties: {} });
    const url = new URL(window.location.href);
    url.searchParams.set('parcela', String(parcel.id));
    history.replaceState(null, '', url);
    if (zoom) {
        fitBox(parcel.bbox);
    } else if (isMobile()) {
        const [west, south, east, north] = parcel.bbox;
        map.easeTo({ center: [(west + east) / 2, (south + north) / 2], padding: { bottom: window.innerHeight * 0.58 }, duration: 400 });
    }
}

new Search(document.getElementById('search'), document.getElementById('search-results'), {
    onSelect: (hit) => (hit.type === 'parcel' ? selectParcel(hit.id, { zoom: true }) : fitBox(hit.bbox)),
});

const legend = new Legend(document.querySelector('.legend'), {
    landTypes: meta.landTypes,
    onChange: (codes) => {
        const filter = ['in', ['get', 't'], ['literal', codes]];
        PARCEL_LAYERS.forEach((layer) => map.setFilter(layer, filter));
    },
});
legend.showDataset(meta);

document.querySelectorAll('[data-basemap]').forEach((button) => button.addEventListener('click', () => {
    const name = button.dataset.basemap;
    document.querySelectorAll('[data-basemap]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
    const before = map.getStyle().layers[1].id;
    map.removeLayer('basemap');
    map.removeSource('basemap');
    map.addSource('basemap', { type: 'raster', tileSize: 256, ...BASEMAPS[name] });
    map.addLayer({ id: 'basemap', type: 'raster', source: 'basemap', paint: { 'raster-saturation': name === 'osm' ? -0.35 : 0 } }, before);
}));

document.getElementById('toggle-kn').addEventListener('change', (event) => {
    map.setLayoutProperty('kn', 'visibility', event.target.checked ? 'visible' : 'none');
});
document.getElementById('toggle-fill').addEventListener('change', (event) => {
    map.setPaintProperty('parcels-fill', 'fill-opacity', event.target.checked
        ? ['case', ['boolean', ['feature-state', 'hover'], false], 0.85, 0.6]
        : ['case', ['boolean', ['feature-state', 'hover'], false], 0.25, 0]);
});

let hovered = null;
const setHover = (id) => {
    if (hovered === id) return;
    if (hovered !== null) map.setFeatureState({ source: 'cadastre', sourceLayer: 'parcels', id: hovered }, { hover: false });
    hovered = id;
    if (id !== null) map.setFeatureState({ source: 'cadastre', sourceLayer: 'parcels', id }, { hover: true });
    map.getCanvas().style.cursor = id === null ? '' : 'pointer';
};

map.on('mousemove', 'parcels-fill', (event) => setHover(event.features[0]?.id ?? null));
map.on('mouseleave', 'parcels-fill', () => setHover(null));
map.on('click', (event) => {
    const feature = map.queryRenderedFeatures(event.point, { layers: ['parcels-fill'] })[0];
    if (feature?.id !== undefined) {
        selectParcel(feature.id);
    } else {
        selectParcel(null);
    }
});

const zoomHint = document.getElementById('zoom-hint');
const updateZoomHint = () => { zoomHint.hidden = map.getZoom() >= FULL_DETAIL_ZOOM - 0.5; };
map.on('zoomend', updateZoomHint);

map.on('load', () => {
    updateZoomHint();
    const initialId = new URL(window.location.href).searchParams.get('parcela');
    if (initialId && /^\d+$/.test(initialId)) {
        selectParcel(initialId, { zoom: !window.location.hash });
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !document.getElementById('detail').hidden) selectParcel(null);
});
