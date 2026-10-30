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
window.SBCIS_HERO_BOREHOLES = Array.from({ length: 80 }, (_, index) => ({
    borehole_code: `BH-${index + 1}`, latitude: 10.15 + index * .000001, longitude: 124.85,
    municipality_name: 'Maasin', barangay_name: 'Test',
    layers: [{ bearing_capacity_kpa: 145 }]
}));

const feature = { type: 'Feature', properties: { GID_2: 'm1', NAME_2: 'Maasin', NAME_3: 'Test' },
    geometry: { type: 'Polygon', coordinates: [[[124.8,10.1],[124.9,10.1],[124.9,10.2],[124.8,10.1]]] } };
const collection = { type: 'FeatureCollection', features: [feature] };
const surface = { type: 'FeatureCollection', features: [{ ...feature, properties: { ...feature.properties, value: 145 } }] };

const geoLayers = [];
const points = [];
const makeLayer = () => ({
    events: {}, addTo() { return this; }, on(name, handler) { this.events[name] = handler; return this; },
    bindTooltip() { return this; }, bindPopup(content) { this.popup = content; return this; },
    clearLayers() { return this; }, addLayer() { return this; }, addData() { return this; }, bringToFront() { return this; },
    getBounds() { return {}; }
});
let zoom = 8;
const mapEvents = {};
const map = { createPane: () => ({ style: {} }), fitBounds() {}, invalidateSize() {},
    getZoom: () => zoom, getBounds: () => ({ contains: () => true }),
    latLngToContainerPoint: location => ({ x: 100 + (location.lng - 124.85) * 1000, y: 100 }),
    on: (names, callback) => { names.split(' ').forEach(name => { mapEvents[name] = callback; }); } };
const L = {
    map: () => map,
    tileLayer: () => makeLayer(),
    geoJSON: (data, options = {}) => {
        const layer = makeLayer(); layer.data = data; layer.options = options; geoLayers.push(layer);
        for (const item of data?.features || []) options.onEachFeature?.(item, makeLayer());
        return layer;
    },
    divIcon: options => options,
    canvas: () => ({}),
    layerGroup: () => makeLayer(),
    latLng: (lat, lng) => ({ lat, lng }),
    latLngBounds: points => points,
    marker: (position, options) => { const point = makeLayer(); point.position = position; point.options = options; points.push(point); return point; },
    circleMarker: (position, options) => { const point = makeLayer(); point.position = position; point.options = options; points.push(point); return point; }
};
window.L = L;

const fetch = async url => ({ ok: true, json: async () => url.includes('interpolation.php')
    ? { status: 'current', result: { legend: { min: 57, max: 321, unit: 'kPa', classes: [
        {key:'low',label:'Low',range:'100–150',min:100,max:150,color:'#f46d43'}
    ] }, surface } }
    : collection });
const context = vm.createContext({ window, document, L, fetch, ResizeObserver: class { observe() {} }, console });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/js/interpolation.js'), 'utf8'), context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/js/hero-map.js'), 'utf8'), context);

(async () => {
    await document.events.DOMContentLoaded();
    assert.equal(elements.get('heroInterpolationLegend').hidden, false, 'Numeric interpolation legend is visible');
    assert.equal(points.length, 0, 'Province overview keeps borehole circles off the surface');
    zoom = 12; mapEvents.zoomend();
    assert.equal(points.length, 1, 'Nearby boreholes are grouped when zoomed in');
    assert.match(points[0].options.title, /80 boreholes/);
    zoom = 16; mapEvents.zoomend();
    assert.equal(points.length, 2, 'Dense locations remain grouped at close zoom');
    assert.match(points[1].popup.children[0].textContent, /80 boreholes/);
    const surfaceLayer = geoLayers.find(layer => layer.data === surface && layer.options.pane === 'heroInterpolationPane');
    assert.ok(surfaceLayer, 'Published interpolation surface is rendered');
    assert.equal(surfaceLayer.options.style(surface.features[0]).fillColor, '#f46d43', 'Server classification color is deterministic');
    assert.equal(elements.get('heroMapReset').disabled, false, 'Hero reset is enabled');
    elements.get('heroMapReset').fire('click');
    assert.match(elements.get('heroMapStatus').textContent, /IDW surface.*80 boreholes/);
    console.log('Hero map checks passed: clear overview, grouped 80 boreholes, close-zoom popups, IDW surface, and reset.');
})().catch(error => { console.error(error); process.exitCode = 1; });
