# Interpolation map

## Current behavior

The public and admin GIS pages use one Leaflet map implementation. Individual
borehole dots are not rendered. Instead, compact count badges identify only the
municipalities that contain data; after selecting a municipality, the badges
switch to its barangays. Selecting a badge or boundary opens a non-blocking bottom sheet
with borehole count, layer count, average bearing capacity, average SPT N-value,
individual layer records, and the current estimated surface value when available.

The location explorer and map-information panel are hidden drawers. Only one is
open at a time, so the map keeps its working area.

## Production interpolation

The approved production variable is `bearing_capacity_kpa`. Regeneration runs on
the server and uses deterministic inverse-distance weighting with:

- power `p = 2`;
- the shallowest valid layer from each borehole;
- at least five distinct eligible observation coordinates;
- a 35 km maximum distance from a barangay centroid to its nearest observation;
- exact Southern Leyte barangay polygons as output cells.

Cells beyond the support limit remain uncolored. The scale is numeric and does
not assign safe/unsafe or foundation-suitability classes. The result is a
screening visualization, not a substitute for a site-specific geotechnical
investigation or signed design recommendation.

The browser cannot submit measurements, choose a method, or manufacture a
surface. The server re-reads the database, hashes the source/configuration,
generates the surface, validates the geometry and metadata, and atomically
publishes only a successful source-matching result. A failed or stale attempt
does not replace a previous valid artifact, and an outdated artifact is never
returned by the public endpoint.

Run regeneration from the admin GIS page or with:

```text
php tools/regenerate-interpolation.php
```

## Data provenance

All valid in-boundary records now participate in the live map and interpolation
pipeline. Seeded records are checked with the same coordinate, measurement,
depth, duplicate-coordinate, and support-distance rules as manually entered
records. The separate synthetic preview route has been removed.

Use [sample_interpolation_50.sql](../database/sample_interpolation_50.sql) to
seed the live dataset when required. The script is idempotent. Its values are
synthetic, so they must not be represented as field evidence or used for final
engineering decisions even though they are visible on the final map.

## API

All actions use `app/Controllers/interpolation.php`.

| Action | Method | Access | Purpose |
| --- | --- | --- | --- |
| `result` | GET | Public | Return only a current approved artifact |
| `status` | GET | Admin | Read source/publication diagnostics |
| `measurements` | GET | Admin | Read validated system-selected inputs |
| `regenerate` | POST | Admin + CSRF | Validate, generate, and publish |

Anonymous management requests return 401, CSRF failures 403, invalid parameters
400, wrong methods 405, and backend failures 503. Public responses never expose
source points or admin diagnostics.

## Verification

The automated suites cover boundary geometry, provenance exclusions, shallowest
layer selection, duplicate-coordinate rejection, source versioning, publication
locking and failure safety, controller authorization/CSRF, CRUD invalidation,
public/admin JavaScript behavior, marker-free rendering, polygon selection,
bottom-sheet aggregation, responsive drawers, exports, records, and the PSGC
location directory.

Visual verification was also performed against the local public GIS page at
desktop resolution. The selected-location bottom sheet leaves the map visible
and shows measured and estimated information as distinct data types.
