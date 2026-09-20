# Cleanup audit

Audit date: 2026-09-19

## Dependency map

- `index.php` is the public entry point. It calls the home and map controllers and loads the landing-page JavaScript, including the compact hero map.
- `views/gis.php`, `views/admin/admin_gis.php`, and `views/map_embed.php` use the shared map explorer partial, `gis.js`, `interpolation.js`, and the three GeoJSON boundary files.
- `app/Controllers/interpolation.php` supplies the public interpolation result and authenticated administration actions. It depends on the interpolation services, repositories, boundary service, and database connection.
- `views/admin/soil_records.php` and the dashboard use `GeotechnicalRepository`; record mutations invalidate the published interpolation artifact.
- Authentication is handled by `AuthController`, `AdminSession`, and the login/logout controller entry points.
- The location directory uses the PSGC cache/snapshot plus municipality and barangay GeoJSON data. The JSON snapshot is a required offline fallback.

## Cleanup assessment

| Resource | Status | Evidence | Action | Risk |
| --- | --- | --- | --- | --- |
| `src/css/login.css` | Definitely unused | No HTML page links it and none of its selectors occur outside the file. | Removed | Low |
| `src/css/map_frames.css` | Definitely unused | No page links it and its `shared-map-frame` selectors occur nowhere else. | Removed | Low |
| `src/js/interpolation-worker.js` | Legacy | It declares itself retired; no source creates a `Worker` or references the filename. Server-side interpolation is the active implementation. | Removed | Low |
| `src/images/hero.jpg`, `src/images/sample_map.png` | Definitely unused | No PHP, JavaScript, CSS, template, or documentation reference exists. | Removed | Low |
| `src/images/southern-leyte-map.svg` and `tools/build-hero-map.js` | Generated, unused | The generator's only output was that SVG; neither is loaded by the application. The active hero map uses Leaflet with GeoJSON. | Removed | Low |
| `src/qgis/*.geojson` | Required | Loaded dynamically by public/admin maps and server-side boundary/location services. | Kept | High |
| `src/qgis/*.qmd` | Generated / manual-verification | QGIS sidecars are not used at runtime, but can be useful when editing GIS data in QGIS. | Kept | Medium |
| `database/locations_snapshot.json` | Required fallback | Used when PSGC is unavailable. | Kept | High |
| `database/sample_interpolation_50.sql` | Development/support data | Removed when the installation was cleared for verified field-data entry. | Removed | Low |
| `tools/test-*` | Development-only but active | Current test suites validate interpolation, GIS, locations, CRUD invalidation, and exports. | Kept | Low |
| `tools/interpolation-ui-preview.php`, `tools/audit-recent-map-data.php`, `tools/regenerate-interpolation.php`, `tools/hash.php` | Operational/support tools | Local-only preview, data audit, documented regeneration, and password utility. | Kept | Medium |

No package-manager manifest exists, so there are no declared dependencies to remove. External browser libraries are intentionally loaded by URL.

## Verification after cleanup

- PHP syntax lint: all PHP files pass.
- JavaScript tests: interpolation, map integration, and hero-map suites pass.
- PHP tests: interpolation, locations, and interpolation-publication suites pass.
- Database-dependent flows require the configured database and authenticated browser session; they were not mutated by this cleanup.
