// Run with: node tools/build-hero-map.js
// Builds the static hero map from the same boundary layers as the GIS page.
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const datasets = ['boundary', 'municipalities', 'barangays'].map(name =>
    JSON.parse(fs.readFileSync(path.join(root, 'src/qgis', `southern_leyte_${name}.geojson`), 'utf8'))
);
const project = ([lng, lat]) => [lng * Math.PI / 180, -Math.log(Math.tan(Math.PI / 4 + lat * Math.PI / 360))];
function rings(geometry) {
    if (geometry.type === 'Polygon') return geometry.coordinates;
    if (geometry.type === 'MultiPolygon') return geometry.coordinates.flat();
    throw new Error(`Unsupported geometry: ${geometry.type}`);
}
const points = datasets.flatMap(data => data.features.flatMap(f => rings(f.geometry).flat().map(project)));
const xs = points.map(p => p[0]), ys = points.map(p => p[1]);
const minX = Math.min(...xs), maxX = Math.max(...xs), minY = Math.min(...ys), maxY = Math.max(...ys);
const scale = Math.min(400 / (maxX - minX), 308 / (maxY - minY));
const offsetX = (480 - (maxX - minX) * scale) / 2;
const offsetY = (360 - (maxY - minY) * scale) / 2;
const draw = data => data.features.map(f => `<path d="${rings(f.geometry).map(ring =>
    ring.map((point, index) => {
        const [x, y] = project(point);
        return `${index ? 'L' : 'M'}${((x - minX) * scale + offsetX).toFixed(2)},${((y - minY) * scale + offsetY).toFixed(2)}`;
    }).join('') + 'Z').join('')}"/>`).join('\n');
const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 360" role="img" aria-labelledby="title desc">
<title id="title">Southern Leyte administrative boundaries</title>
<desc id="desc">Province, municipality and barangay boundaries from the supplied GIS layers. No soil investigation locations are shown.</desc>
<defs><pattern id="grid" width="30" height="30" patternUnits="userSpaceOnUse"><path d="M30 0H0V30" fill="none" stroke="#bad0ca" stroke-width="0.5"/></pattern></defs>
<rect width="480" height="360" fill="#e8f0ed"/><rect width="480" height="360" fill="url(#grid)"/>
<g fill="#b9d9c6" fill-rule="evenodd" stroke="none">${draw(datasets[0])}</g>
<g fill="none" stroke="#4788ac" stroke-opacity="0.65" stroke-width="0.45" stroke-linejoin="round">${draw(datasets[2])}</g>
<g fill="none" stroke="#176b4d" stroke-width="1.2" stroke-linejoin="round">${draw(datasets[1])}</g>
<g fill="none" stroke="#0b3d2e" stroke-width="1.8" stroke-linejoin="round">${draw(datasets[0])}</g>
<g fill="#0b3d2e" font-family="Arial, sans-serif" text-anchor="middle"><text x="444" y="30" font-size="11">N</text><path d="M444 39L439 53L444 50L449 53Z"/></g>
</svg>`;
fs.writeFileSync(path.join(root, 'src/images/southern-leyte-map.svg'), svg);
console.log('Built hero map from province, municipality and barangay layers.');
