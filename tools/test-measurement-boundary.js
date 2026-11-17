const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const context = vm.createContext({ window: {} });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/js/distance-measure.js'), 'utf8'), context);
const Boundary = context.window.SbcisMeasurementBoundary;
const point = (lng, lat) => ({ lng, lat });
const ring = (left, bottom, right, top) => [[left,bottom],[right,bottom],[right,top],[left,top],[left,bottom]];
const polygon = (...rings) => ({ type: 'Polygon', coordinates: rings });
const feature = geometry => ({ type: 'Feature', geometry });
const hole = new Boundary(feature(polygon(ring(0,0,10,10), ring(4,4,6,6))));
assert.ok(hole.contains(point(2,5)));
assert.ok(!hole.contains(point(5,5)), 'Points in holes are outside');
assert.ok(!hole.contains(point(11,5)));
assert.ok(hole.contains(point(0,5)), 'The provincial boundary itself is included');
assert.ok(hole.containsLine(point(0,0), point(10,0)), 'Lines along the boundary are valid');
assert.ok(hole.containsLine(point(2,5), point(2,5)), 'Zero distance is valid inside');
assert.ok(!hole.containsLine(point(2,5), point(8,5)), 'Inside endpoints cannot cross a hole');
assert.ok(hole.containsLine(point(2,4), point(8,4)), 'Touching a hole boundary is allowed');
assert.ok(!hole.containsLine(point(-1,2), point(2,2)), 'Outside endpoints are rejected');
assert.ok(!new Boundary(null).contains(point(2,2)), 'Missing boundary fails closed');
const islands = new Boundary({ type: 'FeatureCollection', features: [feature({ type: 'MultiPolygon', coordinates: [
    [ring(0,0,3,3)], [ring(5,0,8,3)]
] })] });
assert.ok(islands.contains(point(6,1)), 'All MultiPolygon parts participate');
assert.ok(!islands.containsLine(point(1,1), point(6,1)), 'A connection between islands crosses outside');
const adjacent = new Boundary({ type: 'FeatureCollection', features: [feature(polygon(ring(0,0,3,3))), feature(polygon(ring(3,0,6,3)))] });
assert.ok(adjacent.containsLine(point(1,1), point(5,1)), 'Shared polygon edges form a valid union');
const notch = new Boundary(polygon([[0,0],[10,0],[10,10],[5.000001,10],[5.000001,4],[5,4],[5,10],[0,10],[0,0]]));
assert.ok(!notch.containsLine(point(1,5), point(9,5)), 'Even a very narrow concave crossing is rejected without sampling');
assert.ok(notch.containsLine(point(1,3), point(9,3)));
// Validate the actual province dataset as well as the synthetic edge cases.
const official = new Boundary(JSON.parse(fs.readFileSync(path.join(__dirname, '../src/qgis/southern_leyte_boundary.geojson'), 'utf8')));
assert.ok(official.contains(point(124.841426,10.140057)), 'Known Maasin borehole is inside Southern Leyte');
assert.ok(!official.contains(point(0,0)));
assert.ok(!official.contains(point(124.85,10.9)), 'Northern Leyte is outside the official province geometry');
console.log('Measurement boundary checks passed: official GeoJSON, holes, MultiPolygon islands, shared edges, coastlines, zero distance, and narrow concave crossings.');
