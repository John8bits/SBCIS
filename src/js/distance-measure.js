// Validate the complete visible segment, not a bounding box or sampled points.
// Project the GeoJSON with the same CRS as Leaflet's polygon/polyline renderer.
class SbcisMeasurementBoundary {
    constructor(geojson, project = point => [point.lng, point.lat]) {
        this.project = project;
        this.epsilon = 1e-9;
        const features = geojson?.type === 'FeatureCollection' ? geojson.features : [geojson];
        this.polygons = [];
        for (const feature of features || []) {
            const geometry = feature?.type === 'Feature' ? feature.geometry : feature;
            const polygons = geometry?.type === 'Polygon' ? [geometry.coordinates]
                : geometry?.type === 'MultiPolygon' ? geometry.coordinates : [];
            for (const polygon of polygons || []) {
                if (!polygon.length || polygon.some(ring => ring.length < 4 || ring.some(position =>
                    !Number.isFinite(position[0]) || !Number.isFinite(position[1])))) continue;
                this.polygons.push(polygon.map(ring => ring.map(([lng, lat]) => project({ lat, lng }))));
            }
        }
    }

    ringRelation([x, y], ring) {
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const [ax, ay] = ring[j], [bx, by] = ring[i];
            const dx = bx - ax, dy = by - ay, length = Math.hypot(dx, dy);
            if (length && Math.abs((x - ax) * dy - (y - ay) * dx) <= this.epsilon * length &&
                x >= Math.min(ax, bx) - this.epsilon && x <= Math.max(ax, bx) + this.epsilon &&
                y >= Math.min(ay, by) - this.epsilon && y <= Math.max(ay, by) + this.epsilon) return 0;
            if ((ay > y) !== (by > y) && x < ax + (y - ay) * dx / dy) inside = !inside;
        }
        return inside ? 1 : -1;
    }

    containsXY(point) {
        return this.polygons.some(polygon => this.ringRelation(point, polygon[0]) >= 0 &&
            !polygon.slice(1).some(hole => this.ringRelation(point, hole) === 1));
    }

    contains(point) {
        return !!point && Number.isFinite(point.lat) && Number.isFinite(point.lng) &&
            Math.abs(point.lat) <= 90 && Math.abs(point.lng) <= 180 && this.containsXY(this.project(point));
    }

    containsLine(start, end) {
        if (!this.contains(start) || !this.contains(end)) return false;
        const p = this.project(start), q = this.project(end);
        const r = [q[0] - p[0], q[1] - p[1]], length = Math.hypot(...r);
        if (length <= this.epsilon) return true;
        const cross = (a, b) => a[0] * b[1] - a[1] * b[0];
        const cuts = [0, 1];
        const addCut = t => { if (t >= -1e-12 && t <= 1 + 1e-12) cuts.push(Math.max(0, Math.min(1, t))); };
        for (const polygon of this.polygons) for (const ring of polygon) {
            for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                const a = ring[j], b = ring[i];
                const s = [b[0] - a[0], b[1] - a[1]], offset = [a[0] - p[0], a[1] - p[1]];
                const denominator = cross(r, s);
                if (Math.abs(denominator) > Number.EPSILON * length * Math.hypot(...s) * 32) {
                    const t = cross(offset, s) / denominator, u = cross(offset, r) / denominator;
                    if (u >= -1e-12 && u <= 1 + 1e-12) addCut(t);
                } else if (Math.abs(cross(offset, r)) <= this.epsilon * length) {
                    // Also split at collinear edge ends (coastlines/shared edges).
                    for (const point of [a, b]) addCut(((point[0] - p[0]) * r[0] + (point[1] - p[1]) * r[1]) / (length * length));
                }
            }
        }
        cuts.sort((a, b) => a - b);
        for (let i = 1; i < cuts.length; i++) {
            if (cuts[i] - cuts[i - 1] <= 1e-12) continue;
            const t = (cuts[i] + cuts[i - 1]) / 2;
            if (!this.containsXY([p[0] + r[0] * t, p[1] + r[1] * t])) return false;
        }
        return true;
    }
}

// Temporary, client-side measurements. Uses Leaflet's geographic distance in meters.
class SbcisDistanceMeasure {
    constructor(map, { root, toggle, records, boundary, onOpen }) {
        this.map = map;
        this.root = root;
        this.toggle = toggle;
        this.records = records;
        this.boundary = new SbcisMeasurementBoundary(boundary, point => {
            const pixel = map.project(L.latLng(point.lat, point.lng), 0);
            return [pixel.x, pixel.y];
        });
        this.active = false;
        this.mode = 'points';
        this.points = [];
        this.selected = null;
        this.lineLayer = L.layerGroup().addTo(map);
        this.pointLayer = L.layerGroup().addTo(map);
        Object.assign(map.createPane('measurementPane').style, { zIndex: '440', pointerEvents: 'none' });
        Object.assign(map.createPane('measurementSitePane').style, { zIndex: '610', pointerEvents: 'auto' });
        toggle.disabled = false;
        toggle.addEventListener('click', () => {
            if (this.active) this.close();
            else {
                onOpen();
                this.active = true;
                root.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
                map.getContainer().classList.add('gis-measuring');
                this.element('mode').value = this.mode;
                this.clear();
            }
        });
        this.element('close').addEventListener('click', () => { this.close(); toggle.focus(); });
        this.element('clear').addEventListener('click', () => this.clear());
        this.element('mode').addEventListener('change', event => {
            this.mode = event.target.value === 'site' ? 'site' : 'points';
            this.clear();
        });
        this.element('borehole').addEventListener('change', event => {
            const record = this.nearby?.[Number(event.target.value)];
            if (record) this.selectBorehole(record);
        });
        this.keyHandler = event => { if (event.key === 'Escape' && this.active) this.close(); };
        document.addEventListener('keydown', this.keyHandler);
        map.once('unload', () => document.removeEventListener('keydown', this.keyHandler));
    }

    element(name) { return this.root.querySelector('[data-measure="' + name + '"]'); }

    rejectBoundary(crossing = false) {
        this.element('warning').hidden = false;
        this.element('warning').textContent = crossing
            ? 'This straight-line connection crosses outside Southern Leyte. Choose another location or borehole.'
            : 'Distance measurement is only available within Southern Leyte.';
        return false;
    }

    validConnection(start, end) {
        if (!this.boundary.contains(start) || !this.boundary.contains(end)) return this.rejectBoundary();
        if (!this.boundary.containsLine(start, end)) return this.rejectBoundary(true);
        return true;
    }

    recordPoint(record) {
        if (!record || [record.latitude, record.longitude].some(value => value === null || value === undefined || String(value).trim() === '' || !Number.isFinite(Number(value)))) return null;
        return L.latLng(Number(record.latitude), Number(record.longitude));
    }

    formatDistance(meters) {
        return meters >= 1000
            ? (meters / 1000).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' km'
            : meters.toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' m';
    }

    clear() {
        this.points = [];
        this.selected = null;
        this.element('warning').hidden = true;
        this.element('warning').textContent = '';
        this.siteMarker = null;
        this.lineLayer.clearLayers();
        this.pointLayer.clearLayers();
        this.element('site-fields').hidden = this.mode !== 'site';
        this.element('coordinates').textContent = '';
        this.element('details').textContent = '';
        this.element('catalogue').textContent = '';
        this.element('distance').textContent = 'Straight-line distance: —';
        this.element('borehole').replaceChildren(new Option('Place a site on the map first', ''));
        this.element('borehole').disabled = true;
        this.element('instruction').textContent = this.mode === 'site'
            ? 'Click the map to place a proposed construction site.' : 'Click the first location on the map.';
    }

    close() {
        this.clear();
        this.active = false;
        this.root.hidden = true;
        this.toggle.setAttribute('aria-expanded', 'false');
        this.map.getContainer().classList.remove('gis-measuring');
    }

    handleMapClick(event) {
        if (!this.active) return false;
        const lat = Number(event.latlng?.lat), lng = Number(event.latlng?.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return true;
        const point = L.latLng(lat, lng);
        if (!this.boundary.contains(point)) { this.rejectBoundary(); return true; }
        if (this.mode === 'site') this.placeSite(point);
        else {
            if (this.points.length === 1 && !this.validConnection(this.points[0], point)) return true;
            if (this.points.length === 2) this.clear();
            this.element('warning').hidden = true;
            this.points.push(point);
            L.circleMarker(point, { pane: 'measurementPane', radius: 5, weight: 2,
                color: '#ffffff', fillColor: '#155e75', fillOpacity: 1, interactive: false }).addTo(this.pointLayer);
            if (this.points.length === 2) {
                this.drawLine(this.points[0], this.points[1]);
                this.element('instruction').textContent = 'Measurement complete. Click to start another measurement.';
            } else this.element('instruction').textContent = 'Click the second location on the map.';
        }
        return true;
    }

    handleBoreholeClick(record) {
        if (!this.active) return false;
        if (this.mode === 'site' && this.points.length) {
            if (this.selectBorehole(record)) this.refreshRecords();
        }
        else this.handleMapClick({ latlng: { lat: record.latitude, lng: record.longitude } });
        return true;
    }

    placeSite(point) {
        if (!this.boundary.contains(point)) return this.rejectBoundary();
        if (this.selected && !this.validConnection(point, this.recordPoint(this.selected))) return false;
        this.element('warning').hidden = true;
        this.points = [point];
        this.pointLayer.clearLayers();
        const icon = document.createElement('span');
        icon.className = 'gis-site-symbol';
        icon.textContent = '⌂';
        this.siteMarker = L.marker(point, {
            pane: 'measurementSitePane', draggable: true, keyboard: true,
            title: 'Proposed construction site. Drag to reposition.',
            icon: L.divIcon({ className: 'gis-site-marker', html: icon, iconSize: [30, 32], iconAnchor: [15, 30] })
        }).addTo(this.pointLayer);
        this.siteMarker.on('dragend', () => {
            const next = this.siteMarker.getLatLng();
            if (!this.boundary.contains(next) || (this.selected && !this.validConnection(next, this.recordPoint(this.selected)))) {
                this.siteMarker.setLatLng(this.points[0]);
                if (!this.boundary.contains(next)) this.rejectBoundary();
                return;
            }
            this.element('warning').hidden = true;
            this.points = [next];
            this.refreshRecords();
        });
        this.refreshRecords();
        this.element('instruction').textContent = 'Click a borehole or choose one below. Click the map or drag the house to move the site.';
    }

    refreshRecords() {
        if (!this.active || this.mode !== 'site' || !this.points.length) return;
        const site = this.points[0];
        this.element('coordinates').textContent = 'Site: ' + site.lat.toFixed(6) + ', ' + site.lng.toFixed(6);
        this.element('warning').hidden = true;
        const loaded = this.records();
        const updatedSelection = this.selected && loaded.find(record => this.recordKey(record) === this.recordKey(this.selected));
        if (updatedSelection && !this.boundary.contains(this.recordPoint(updatedSelection))) this.selected = null;
        const records = loaded.filter(record => this.boundary.contains(this.recordPoint(record)));
        if (this.selected && this.boundary.contains(this.recordPoint(this.selected)) &&
            !records.some(record => this.recordKey(record) === this.recordKey(this.selected))) records.push(this.selected);
        this.nearby = records.sort((a, b) => this.distanceTo(a) - this.distanceTo(b));
        const validKeys = new Set(this.nearby.filter(record => this.boundary.containsLine(site, this.recordPoint(record)))
            .map(record => this.recordKey(record)));
        const select = this.element('borehole');
        select.replaceChildren();
        this.nearby.forEach((record, index) => {
            const valid = validKeys.has(this.recordKey(record));
            const option = new Option((record.borehole_code || 'Borehole') + ' · ' + (valid
                ? this.formatDistance(this.distanceTo(record)) : 'Connection crosses outside Southern Leyte'), String(index));
            option.disabled = !valid;
            select.add(option);
        });
        select.disabled = !validKeys.size;
        this.element('catalogue').textContent = window.SBCIS_CATALOGUE_TRUNCATED
            ? 'Options use currently loaded map records and the selected borehole; other boreholes may be closer.'
            : 'Boreholes are ordered by straight-line distance from your site.';
        this.lineLayer.clearLayers();
        this.element('distance').textContent = 'Straight-line distance: —';
        this.element('details').textContent = '';
        const previousKey = this.recordKey(this.selected);
        this.selected = null;
        const next = this.nearby.find(record => this.recordKey(record) === previousKey && validKeys.has(previousKey)) ||
            this.nearby.find(record => validKeys.has(this.recordKey(record)));
        if (next) this.selectBorehole(next);
        else if (this.nearby.length) {
            select.value = '';
            this.rejectBoundary(true);
        }
        else {
            this.lineLayer.clearLayers();
            this.element('distance').textContent = 'Straight-line distance: —';
            this.element('details').textContent = 'No borehole records within Southern Leyte are loaded in this map area.';
        }
    }

    recordKey(record) { return record ? String(record.borehole_id ?? record.borehole_code) : null; }
    distanceTo(record) { return this.map.distance(this.points[0], L.latLng(Number(record.latitude), Number(record.longitude))); }

    selectBorehole(record) {
        const point = this.recordPoint(record);
        if (!this.validConnection(this.points[0], point)) {
            const previous = this.nearby?.findIndex(item => this.recordKey(item) === this.recordKey(this.selected));
            this.element('borehole').value = previous >= 0 ? String(previous) : '';
            return false;
        }
        this.selected = record;
        const index = this.nearby?.findIndex(item => this.recordKey(item) === this.recordKey(record));
        if (index >= 0) this.element('borehole').value = String(index);
        const capacity = window.SBCIS_BEARING_CAPACITY.boreholeValue(record);
        const band = window.SBCIS_BEARING_CAPACITY.boreholeClass(record);
        const value = window.SBCIS_BEARING_CAPACITY.classify(capacity)
            ? Number(capacity).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' kPa' : 'Not recorded';
        this.element('details').textContent = (record.borehole_code || 'Borehole') + ' · ' + value + ' · ' + band.label;
        return this.drawLine(this.points[0], point);
    }

    drawLine(start, end) {
        if (!this.validConnection(start, end)) return false;
        this.element('warning').hidden = true;
        this.lineLayer.clearLayers();
        const text = 'Straight-line distance: ' + this.formatDistance(this.map.distance(start, end));
        const label = document.createElement('span');
        label.textContent = text;
        L.polyline([start, end], { pane: 'measurementPane', color: '#155e75', weight: 3,
            dashArray: '7 6', interactive: false }).addTo(this.lineLayer)
            .bindTooltip(label, { permanent: true, direction: 'center', className: 'gis-distance-label', interactive: false });
        this.element('distance').textContent = text;
        return true;
    }
}

window.SbcisDistanceMeasure = SbcisDistanceMeasure;
window.SbcisMeasurementBoundary = SbcisMeasurementBoundary;
