document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById('heroMap');
    if (!container) return;
    const reset = document.getElementById('heroMapReset');
    const status = document.getElementById('heroMapStatus');
    try {
        if (!window.L) throw new Error('Map unavailable. Open the full map to try again.');
        const map = L.map(container, { preferCanvas: true, scrollWheelZoom: false, minZoom: 7, maxZoom: 19 });
        map.setView([10.2, 125.1], 9);
        let tilesUnavailable = false;
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            tileSize: 256,
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).on('tileerror', () => {
            tilesUnavailable = true;
            status.textContent = 'Background unavailable. Boundaries are still shown.';
        }).addTo(map);
        const datasets = await Promise.all(['boundary', 'municipalities', 'barangays'].map(async name => {
            const response = await fetch('src/qgis/southern_leyte_' + name + '.geojson');
            if (!response.ok) throw new Error('Map unavailable. Open the full map to try again.');
            return response.json();
        }));
        L.geoJSON(datasets[1], {
            style: { color: '#176b4d', weight: 1.2, fillColor: '#75b590', fillOpacity: 0.12 },
            onEachFeature: (feature, layer) => {
                const label = document.createElement('span');
                label.textContent = feature.properties.NAME_2;
                layer.bindTooltip(label);
                layer.on('click', () => {
                    selected.clearLayers();
                    barangays.clearLayers().addData({
                        type: 'FeatureCollection',
                        features: datasets[2].features.filter(f => f.properties.GID_2 === feature.properties.GID_2)
                    }).bringToFront();
                    map.fitBounds(layer.getBounds(), { padding: [16, 16], maxZoom: 14 });
                    status.textContent = feature.properties.NAME_2 + ': click a barangay to zoom in.';
                });
            }
        }).addTo(map);
        const province = L.geoJSON(datasets[0], {
            interactive: false,
            style: { color: '#0b3d2e', weight: 2, fill: false }
        }).addTo(map);
        const barangays = L.geoJSON(null, {
            style: { color: '#4788ac', weight: 1.3, fillColor: '#8cc7dd', fillOpacity: 0.15 },
            onEachFeature: (feature, layer) => {
                const label = document.createElement('span');
                label.textContent = feature.properties.NAME_3;
                layer.bindTooltip(label);
                layer.on('click', () => {
                    selected.clearLayers().addData(feature).bringToFront();
                    map.fitBounds(layer.getBounds(), { padding: [16, 16], maxZoom: 17 });
                    status.textContent = feature.properties.NAME_3 + ', ' + feature.properties.NAME_2;
                });
            }
        }).addTo(map);
        const selected = L.geoJSON(null, {
            interactive: false,
            style: { color: '#d88516', weight: 2, fillColor: '#f3ba5a', fillOpacity: 0.2 }
        }).addTo(map);
        const fit = () => {
            barangays.clearLayers();
            selected.clearLayers();
            map.fitBounds(province.getBounds(), { padding: [12, 12] });
            status.textContent = tilesUnavailable
                ? 'Background unavailable. Click a municipality to explore.'
                : 'Click a municipality, then a barangay to zoom in.';
        };
        reset.disabled = false;
        reset.addEventListener('click', fit);
        new ResizeObserver(() => map.invalidateSize()).observe(container);
        fit();
    } catch (error) {
        status.textContent = error.message || 'Map unavailable. Please try again.';
    }
});
