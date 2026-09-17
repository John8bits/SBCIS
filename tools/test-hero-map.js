const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
    constructor() { this.events = {}; this.style = {}; this.children = []; this.hidden = true; this.textContent = ''; }
    addEventListener(name, handler) { this.events[name] = handler; }
    removeEventListener(name) { delete this.events[name]; }
    append(...children) { this.children.push(...children); }
    fire(name) { return this.events[name]?.({ target: this }); }
}

const document = new Element();
const elements = new Map(['heroMap', 'heroMapReset', 'heroMapStatus', 'heroInterpolationLegend'].map(id => [id, new Element()]));
document.getElementById = id => elements.get(id) || null;
document.createElement = () => new Element();

const window = new Element();
window.SBCIS_HERO_BOREHOLES = [{
    borehole_code: 'BH-001', latitude: 10.15, longitude: 124.85,
    municipality_name: 'Maasin', barangay_name: 'Test',
    layers: [{ bearing_capacity_kpa: 145 }]
}];

const feature = { type: 'Feature', properties: { GID_2: 'm1', NAME_2: 'Maasin', NAME_3: 'Test' },
    geometry: { type: 'Polygon', coordinates: [[[124.8,10.1],[124.9,10.1],[124.9,10.2],[124.8,10.1]]] } };
const collection = { type: 'FeatureCollection', features: [feature] };
const surface = { type: 'FeatureCollection', features: [{ ...feature, properties: { ...feature.properties, value: 145 } }] };

const geoLayers = [];
const points = [];
const makeLayer = () => ({
    events: {}, addTo() { return this; }, on(name, handler) { this.events[name] = handler; return this; },
    bindTooltip() { return this; }, bindPopup(content) { this.popup = content; return this; },
    clearLayers() { return this; }, addData() { return this; }, bringToFront() { return this; },
    getBounds() { return {}; }
});
const map = { createPane: () => ({ style: {} }), fitBounds() {}, invalidateSize() {} };
const L = {
    map: () => map,
    tileLayer: () => makeLayer(),
    geoJSON: (data, options = {}) => {
        const layer = makeLayer(); layer.data = data; layer.options = options; geoLayers.push(layer);
        for (const item of data?.features || []) options.onEachFeature?.(item, makeLayer());
        return layer;
    },
    divIcon: options => options,
    marker: (position, options) => { const point = makeLayer(); point.position = position; point.options = options; points.push(point); return point; }
};
window.L = L;

const fetch = async url => ({ ok: true, json: async () => url.includes('interpolation.php')
    ? { status: 'current', result: { legend: { min: 57, max: 321, unit: 'kPa' }, surface } }
    : collection });
const context = vm.createContext({ window, document, L, fetch, ResizeObserver: class { observe() {} }, console });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/js/interpolation.js'), 'utf8'), context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/js/hero-map.js'), 'utf8'), context);

(async () => {
    await document.events.DOMContentLoaded();
    assert.equal(elements.get('heroInterpolationLegend').hidden, false, 'Five-band interpolation legend is visible');
    assert.equal(points.length, 1, 'Hero map renders borehole locations');
    assert.ok(points[0].popup, 'Hero borehole location has a details popup');
    const surfaceLayer = geoLayers.find(layer => layer.data === surface);
    assert.ok(surfaceLayer, 'Published interpolation surface is rendered');
    assert.equal(surfaceLayer.options.style(surface.features[0]).fillColor, '#f59e0b', '145 kPa uses the 100–150 kPa band');
    assert.equal(elements.get('heroMapReset').disabled, false, 'Hero reset is enabled');
    elements.get('heroMapReset').fire('click');
    assert.match(elements.get('heroMapStatus').textContent, /IDW surface.*1 boreholes/);
    console.log('Hero map checks passed: published IDW surface, fixed ranges, borehole popup, and reset control.');
})().catch(error => { console.error(error); process.exitCode = 1; });
