async function getJson(url, signal) {
    const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(body.error || `HTTP ${response.status}`);
    }
    return body;
}

export const api = {
    meta: () => getJson('/api/meta'),
    parcel: (id, signal) => getJson(`/api/parcels/${encodeURIComponent(id)}`, signal),
    live: (id) => getJson(`/api/parcels/${encodeURIComponent(id)}/live`),
    search: (query, signal) => getJson(`/api/search?q=${encodeURIComponent(query)}`, signal),
};
