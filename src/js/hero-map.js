document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById('heroMap');
    if (!container) return;

    const reset = document.getElementById('heroMapReset');
    const status = document.getElementById('heroMapStatus');
    const legend = document.getElementById('heroInterpolationLegend');

    const metric = (value, unit = '') => Number.isFinite(Number(value))
        ? Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 }) + (unit ? ' ' + unit : '')
        : 'Not recorded';

    function popupFor(borehole) {
        const layer = Array.isArray(borehole.layers) ? borehole.layers[0] : null;
        const popup = document.createElement('article');
        popup.className = 'hero-borehole-popup';
        const title = document.createElement('strong');
        title.textContent = borehole.borehole_code || 'Borehole';
        const place = document.createElement('span');
        place.textContent = [borehole.barangay_name, borehole.municipality_name].filter(Boolean).join(', ') || 'Southern Leyte';
        const value = document.createElement('b');
        value.textContent = metric(layer?.bearing_capacity_kpa, 'kPa');
        const range = document.createElement('small');
        range.textContent = Number.isFinite(Number(layer?.bearing_capacity_kpa)) ? 'Observed borehole value' : 'No bearing capacity value';
        popup.append(title, place, value, range);
        return popup;
    }

    try {
        if (!window.L) throw new Error('Map unavailable. Open the full map to try again.');
        const map = L.map(container, { preferCanvas: true, scrollWheelZoom: false, minZoom: 7, maxZoom: 19 });
        const shadowPane = map.createPane('heroInterpolationShadowPane');
        const surfacePane = map.createPane('heroInterpolationPane');
        const boundaryPane = map.createPane('heroBoundaryPane');
        const pointPane = map.createPane('heroPointPane');
        Object.assign(shadowPane.style, { zIndex: '340', pointerEvents: 'none', opacity: '.36', transform: 'translate3d(1px, 2px, 0)' });
        Object.assign(surfacePane.style, { zIndex: '350', pointerEvents: 'none', filter: 'drop-shadow(0 1px 2px rgba(16, 58, 39, .22))' });
        Object.assign(boundaryPane.style, { zIndex: '410', pointerEvents: 'auto' });
        Object.assign(pointPane.style, { zIndex: '450', pointerEvents: 'auto' });

        let tilesUnavailable = false;
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            tileSize: 256,
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).on('tileerror', () => {
            tilesUnavailable = true;
            status.textContent = 'Background unavailable. Interpolation and boundaries remain visible.';
        }).addTo(map);

        const [provinceData, municipalityData, barangayData, interpolationResponse] = await Promise.all([
            ...['boundary', 'municipalities', 'barangays'].map(async name => {
                const response = await fetch('src/qgis/southern_leyte_' + name + '.geojson');
                if (!response.ok) throw new Error('Map unavailable. Open the full map to try again.');
                return response.json();
            }),
            fetch('app/Controllers/interpolation.php?action=result', { cache: 'no-store' })
                .then(response => response.ok ? response.json() : { status: 'unavailable', result: null })
                .catch(() => ({ status: 'unavailable', result: null }))
        ]);

        const result = interpolationResponse.status === 'current' ? interpolationResponse.result : null;
        const d3 = window.d3;
        const surfaceScale = d3?.scaleLinear && typeof d3.interpolateRgb === 'function' &&
            Number.isFinite(Number(result?.legend?.min)) && Number.isFinite(Number(result?.legend?.max)) &&
            Number(result.legend.max) > Number(result.legend.min)
            ? d3.scaleLinear()
                .domain([
                    Number(result.legend.min),
                    Number(result.legend.min) + (Number(result.legend.max) - Number(result.legend.min)) * .25,
                    Number(result.legend.min) + (Number(result.legend.max) - Number(result.legend.min)) * .5,
                    Number(result.legend.min) + (Number(result.legend.max) - Number(result.legend.min)) * .75,
                    Number(result.legend.max)
                ])
                .range(['#a94442', '#c77745', '#d5ad56', '#7fa36d', '#28745d'])
                .interpolate(d3.interpolateRgb)
            : null;
        const surfaceColor = value => surfaceScale
            ? surfaceScale(Number(value))
            : (Number.isFinite(Number(value)) ? '#28745d' : '#d9e2dd');
        if (result?.surface?.type === 'FeatureCollection' && window.SbcisInterpolation) {
            L.geoJSON(result.surface, {
                pane: 'heroInterpolationShadowPane', interactive: false,
                style: { color: '#173f2d', weight: 1.4, opacity: .45, fillColor: '#173f2d', fillOpacity: .14 }
            }).addTo(map);
            L.geoJSON(result.surface, {
                pane: 'heroInterpolationPane',
                interactive: false,
                style: feature => {
                    const color = surfaceColor(feature.properties.value);
                    return { color, fillColor: color, weight: .2, opacity: .18, fillOpacity: .64 };
                }
            }).addTo(map);
            legend.hidden = false;
        }

        const selected = L.geoJSON(null, {
            pane: 'heroBoundaryPane', interactive: false,
            style: { color: '#0b3d2e', weight: 2.5, fillColor: '#fff', fillOpacity: .08 }
        }).addTo(map);
        const barangays = L.geoJSON(null, {
            pane: 'heroBoundaryPane',
            style: { color: 'rgba(20, 71, 53, .46)', weight: .75, fill: false },
            onEachFeature: (feature, layer) => {
                layer.bindTooltip(feature.properties.NAME_3);
                layer.on('click', () => {
                    selected.clearLayers().addData(feature).bringToFront();
                    map.fitBounds(layer.getBounds(), { padding: [16, 16], maxZoom: 17 });
                    status.textContent = feature.properties.NAME_3 + ', ' + feature.properties.NAME_2;
                });
            }
        }).addTo(map);
        L.geoJSON(municipalityData, {
            pane: 'heroBoundaryPane',
            style: { color: '#174735', weight: 1.15, fill: false },
            onEachFeature: (feature, layer) => {
                layer.bindTooltip(feature.properties.NAME_2);
                layer.on('click', () => {
                    selected.clearLayers();
                    barangays.clearLayers().addData({
                        type: 'FeatureCollection',
                        features: barangayData.features.filter(candidate => candidate.properties.GID_2 === feature.properties.GID_2)
                    }).bringToFront();
                    map.fitBounds(layer.getBounds(), { padding: [16, 16], maxZoom: 14 });
                    status.textContent = feature.properties.NAME_2 + ': select a barangay or borehole.';
                });
            }
        }).addTo(map);
        const province = L.geoJSON(provinceData, {
            pane: 'heroBoundaryPane', interactive: false,
            style: { color: '#082f24', weight: 2.4, fill: false }
        }).addTo(map);

        const boreholes = Array.isArray(window.SBCIS_HERO_BOREHOLES) ? window.SBCIS_HERO_BOREHOLES : [];
        boreholes.forEach(borehole => {
            const latitude = Number(borehole.latitude);
            const longitude = Number(borehole.longitude);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
            L.circleMarker([latitude, longitude], {
                renderer: L.canvas({ padding: 0.35 }), radius: 5, weight: 2,
                color: '#ffffff', fillColor: '#0b6b4f', fillOpacity: 0.95,
                keyboard: true,
                title: `${borehole.borehole_code || 'Borehole'}: view soil record`
            }).addTo(map).bindPopup(popupFor(borehole));
        });

        const fit = () => {
            barangays.clearLayers();
            selected.clearLayers();
            map.fitBounds(province.getBounds(), { padding: [14, 14] });
            status.textContent = result
                ? 'IDW surface • ' + boreholes.length + ' boreholes • click a point for details.'
                : 'Interpolation unavailable. Boundaries and borehole records remain visible.';
            if (tilesUnavailable) status.textContent += ' Background map unavailable.';
        };
        reset.disabled = false;
        reset.addEventListener('click', fit);
        new ResizeObserver(() => map.invalidateSize()).observe(container);
        fit();
    } catch (error) {
        status.textContent = error.message || 'Map unavailable. Please try again.';
    }
});
