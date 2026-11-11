export const LAND_TYPE_COLORS = {
    2: '#e9cf7c',  // orná půda
    3: '#a4c35f',  // chmelnice
    4: '#b679ad',  // vinice
    5: '#93d38c',  // zahrada
    6: '#cbd970',  // ovocný sad
    7: '#b9e2a6',  // trvalý travní porost
    10: '#4f9a5d', // lesní pozemek
    11: '#74b9e4', // vodní plocha
    13: '#d39a86', // zastavěná plocha a nádvoří
    14: '#cfcac2', // ostatní plocha
};

const FALLBACK_COLOR = '#bbbbbb';

export const BASEMAPS = {
    osm: {
        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxzoom: 19,
    },
    ortofoto: {
        tiles: ['https://ags.cuzk.cz/arcgis1/rest/services/ORTOFOTO_WM/MapServer/tile/{z}/{y}/{x}'],
        attribution: 'Ortofoto © <a href="https://www.cuzk.cz">ČÚZK</a>',
        maxzoom: 20,
    },
};

const KN_WMS = 'https://services.cuzk.cz/wms/wms.asp?service=WMS&version=1.3.0&request=GetMap'
    + '&layers=KN&styles=&crs=EPSG:3857&bbox={bbox-epsg-3857}&width=512&height=512'
    + '&format=image/png&transparent=true';

export function landTypeColorExpression() {
    const expression = ['match', ['get', 't']];
    for (const [code, color] of Object.entries(LAND_TYPE_COLORS)) {
        expression.push(Number(code), color);
    }
    expression.push(FALLBACK_COLOR);
    return expression;
}

export function buildStyle({ origin, bbox, maxZoom, tileVersion }) {
    return {
        version: 8,
        glyphs: 'https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf',
        sources: {
            basemap: { type: 'raster', tileSize: 256, ...BASEMAPS.osm },
            kn: { type: 'raster', tiles: [KN_WMS], tileSize: 512, minzoom: 13, attribution: 'Katastrální mapa © ČÚZK' },
            cadastre: {
                type: 'vector',
                tiles: [`${origin}/tiles/{z}/{x}/{y}.pbf?v=${encodeURIComponent(tileVersion)}`],
                minzoom: 8,
                maxzoom: maxZoom,
                bounds: bbox ?? undefined,
                attribution: 'Data © <a href="https://www.cuzk.cz">ČÚZK</a> (RÚIAN)',
            },
            selection: { type: 'geojson', data: { type: 'FeatureCollection', features: [] } },
        },
        layers: [
            { id: 'basemap', type: 'raster', source: 'basemap', paint: { 'raster-saturation': -0.35 } },
            {
                id: 'parcels-fill',
                type: 'fill',
                source: 'cadastre',
                'source-layer': 'parcels',
                paint: {
                    'fill-color': landTypeColorExpression(),
                    'fill-opacity': ['case', ['boolean', ['feature-state', 'hover'], false], 0.75, 0.6],
                },
            },
            {
                id: 'parcels-outline',
                type: 'line',
                source: 'cadastre',
                'source-layer': 'parcels',
                minzoom: 13,
                paint: {
                    'line-color': '#5b4b3a',
                    'line-opacity': ['interpolate', ['linear'], ['zoom'], 13, 0.25, 16, 0.7],
                    'line-width': ['interpolate', ['linear'], ['zoom'], 13, 0.3, 18, 1.2],
                },
            },
            { id: 'kn', type: 'raster', source: 'kn', layout: { visibility: 'none' } },
            {
                id: 'cadastral-areas',
                type: 'line',
                source: 'cadastre',
                'source-layer': 'cadastral_areas',
                paint: {
                    'line-color': '#7a3e8f',
                    'line-width': ['interpolate', ['linear'], ['zoom'], 9, 0.6, 14, 1.6],
                    'line-dasharray': [3, 2],
                },
            },
            {
                id: 'district',
                type: 'line',
                source: 'cadastre',
                'source-layer': 'district',
                paint: { 'line-color': '#3c1f4a', 'line-width': ['interpolate', ['linear'], ['zoom'], 8, 2, 14, 4], 'line-opacity': 0.8 },
            },
            {
                id: 'selection-fill',
                type: 'fill',
                source: 'selection',
                paint: { 'fill-color': '#ff5a1f', 'fill-opacity': 0.35 },
            },
            {
                id: 'selection-outline',
                type: 'line',
                source: 'selection',
                paint: { 'line-color': '#ff5a1f', 'line-width': 3 },
            },
            {
                id: 'parcel-numbers',
                type: 'symbol',
                source: 'cadastre',
                'source-layer': 'parcels',
                minzoom: 17,
                layout: {
                    'text-field': ['get', 'n'],
                    'text-font': ['Noto Sans Regular'],
                    'text-size': 11,
                    'text-padding': 1,
                },
                paint: { 'text-color': '#2b2118', 'text-halo-color': '#ffffff', 'text-halo-width': 1.2 },
            },
            {
                id: 'cadastral-labels',
                type: 'symbol',
                source: 'cadastre',
                'source-layer': 'cadastral_labels',
                minzoom: 10,
                maxzoom: 16,
                layout: {
                    'text-field': ['get', 'name'],
                    'text-font': ['Noto Sans Regular'],
                    'text-size': ['interpolate', ['linear'], ['zoom'], 10, 11, 14, 14],
                },
                paint: { 'text-color': '#4b2160', 'text-halo-color': '#ffffff', 'text-halo-width': 1.5 },
            },
        ],
    };
}
