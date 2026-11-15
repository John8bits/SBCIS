const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
    constructor() { this.events = {}; this.hidden = true; this.disabled = true; this.textContent = ''; }
    addEventListener(name, handler) { this.events[name] = handler; }
    fire(name) { return this.events[name]?.(); }
    click() { this.fire('click'); }
}
const document = new Element();
const elements = new Map(['heroMap', 'heroMapReset', 'heroMapStatus', 'heroInterpolationLegend'].map(id => [id, new Element()]));
document.getElementById = id => elements.get(id) || null;
const previewReset = new Element();
const previewStatus = new Element();
previewStatus.textContent = 'Loading map...';
const frame = elements.get('heroMap');
frame.contentDocument = { readyState: 'loading', getElementById: id => ({ quickResetMap: previewReset, gisStatus: previewStatus })[id] || null };
let observer;
class MutationObserver {
    constructor(callback) { this.callback = callback; this.targets = []; observer = this; }
    observe(target, options) { this.targets.push({ target, options }); }
    disconnect() { this.disconnected = true; }
}
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/js/hero-map.js'), 'utf8'), vm.createContext({ document, MutationObserver }));
document.fire('DOMContentLoaded');
frame.fire('load');
assert.equal(elements.get('heroMapReset').disabled, true, 'Reset waits for shared runtime initialization');
assert.equal(elements.get('heroMapStatus').textContent, 'Loading map...');
assert.equal(observer.targets.length, 2, 'Observe readiness and public map status');
previewReset.disabled = false;
previewStatus.textContent = '80 source borehole records available through area details';
observer.callback();
assert.equal(elements.get('heroMapReset').disabled, false);
assert.equal(elements.get('heroInterpolationLegend').hidden, false, 'Legend also describes markers when no surface is published');
assert.equal(elements.get('heroMapStatus').textContent, previewStatus.textContent);
let resets = 0;
previewReset.addEventListener('click', () => { resets++; });
elements.get('heroMapReset').fire('click');
assert.equal(resets, 1, 'Homepage reset calls the public GIS reset');
previewReset.disabled = true;
elements.get('heroMapReset').fire('click');
assert.equal(resets, 1, 'Disabled map cannot be reset before initialization');
const previous = observer;
frame.fire('load');
assert.equal(previous.disconnected, true, 'Frame reload removes previous observers');
console.log('Homepage bridge checks passed: shared map readiness, legend, live status, reset forwarding, and frame reload.');
