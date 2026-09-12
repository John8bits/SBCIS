# Southern Leyte location directory

The shared location service uses the public [PSGC API](https://psgc.gitlab.io/api/):

- `https://psgc.gitlab.io/api/provinces/086400000/cities-municipalities/`
- `https://psgc.gitlab.io/api/provinces/086400000/barangays/`

This is a public third-party PSGC service, not the authenticated PSA API. No API key is required. Only public location lists are requested; saved borehole information is never sent to the provider.

`app/Models/locations.php` validates the province, unique codes, and parent relationships. It caches successful responses for 24 hours in PHP's temporary directory. Failed refreshes use the last successful response, or `database/locations_snapshot.json` when no cache exists. Retries are limited to one attempt every five minutes during an outage. The directory UI includes the retrieval date and identifies saved copies.

`app/Controllers/locations.php` exposes the normalized directory to the map. Admin location pages, record-entry choices, homepage totals, and dashboard location totals use the same service. Reference locations are not automatically inserted into the application's saved-record tables; the backup still exports saved application data.

PSGC lists are not map geometries. Existing GeoJSON files supply those. Names are matched within the parent municipality, with normalization for city naming, accents, and Poblacion abbreviations. Ambiguous or missing matches remain searchable without fabricating a boundary. In the bundled snapshot, San Pablo Island and San Pedro Island have no corresponding geometry in the existing boundary files.

`views/partials/map_explorer.php`, `src/js/gis.js`, and `src/css/gis.css` are shared by public and admin GIS pages. The landing page and dashboard embed `views/map_embed.php`, which uses that same component. Map links support `?municipality=PSGC_CODE`, `?barangay=PSGC_CODE`, or `?q=SEARCH_TEXT`.

Run `php tools/test-locations.php`, `php tools/test-record-entry.php`, and `php tools/test-data-export.php` for validation. Database tests use connection-local temporary tables and do not modify saved application records.
