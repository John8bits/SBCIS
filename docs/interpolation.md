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
- Haversine great-circle distance in kilometres (WGS 84 latitude/longitude input);
- all eligible observation coordinates as neighbors (no unvalidated nearest-N cutoff);
- no province, municipality, or barangay membership check on observation coordinates;
- at least five distinct eligible observation coordinates;
- a 35 km maximum distance from a barangay centroid to its nearest observation;
- exact Southern Leyte barangay polygons as output cells.

The published value for a barangay is evaluated at that polygon's area centroid;
it is not a per-pixel raster. A zero-distance check returns the observed value
without dividing by zero. Duplicate coordinates are excluded rather than
silently averaged. Each successful generation also calculates leave-one-out
MAE, RMSE, and bias for the admin diagnostic view and verifies exact-location
reproduction. Validation includes every point up to 500 observations; larger
datasets use a deterministic, evenly distributed 500-point sample to bound the
otherwise quadratic validation cost.

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

Only verified field records are eligible for interpolation. Legacy records with
reserved non-field prefixes or explicit non-field provenance are detected and
excluded even if a borehole code is later renamed. This safety rule also
controls which records appear as interpolation inputs.

Coordinate inputs are checked only for numeric WGS 84 ranges: latitude must be
between -90 and 90 and longitude between -180 and 180. The system does not
compare an entered point with the selected province, municipality, or barangay.
Administrators are responsible for confirming that those selections match the
surveyed coordinate. Generated display cells remain clipped to Southern Leyte.

## Technical assessment (2026-09-20)

| Item | Audited result |
| --- | --- |
| Current method | Server-side IDW, power 2 |
| Data used | Shallowest valid bearing-capacity layer per eligible verified borehole |
| Distance | Haversine, kilometres; latitude/longitude are not treated as Cartesian metres |
| Units | Bearing capacity is stored, calculated, returned, and displayed in kPa (`kN/m²`) |
| Output resolution | One estimate at each barangay polygon centroid; the polygon is the display cell |
| Neighbors | All eligible distinct coordinates; 35 km nearest-support publication limit |
| Boundary | Actual Southern Leyte barangay geometries, guarded against out-of-boundary publication |
| Source of truth | One repository/data service/generator/publication endpoint shared by admin, public GIS, and landing map |
| Refresh | CRUD commits invalidate the source hash and immediately attempt regeneration; stale artifacts are hidden |

The earlier development records and their generated surface were removed before
field-data entry began. With an empty record database, the GIS correctly shows
that no current interpolation is available until enough verified observations
have been entered and regeneration succeeds.

The all-neighbor strategy is retained because the current live field dataset
cannot support empirical tuning of nearest-N or radius parameters. Once enough
real observations exist, compare candidate neighborhood policies using the
reported leave-one-out errors before changing this configuration.

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
location directory. Generator checks also cover exact-location behavior and
leave-one-out metrics.

Visual verification was also performed against the local public GIS page at
desktop resolution. The selected-location bottom sheet leaves the map visible
and shows measured and estimated information as distinct data types.
