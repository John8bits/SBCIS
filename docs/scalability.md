# System scalability and reliability audit

Audit date: 2026-09-20

## Architecture and risks found

SBCIS is a server-rendered PHP/PDO application backed by MySQL, with shared
repository/services for geotechnical records and interpolation and Leaflet/D3
maps. The design is suitable for incremental scaling; it does not require
microservices.

The main growth risks were unbounded `fetchAll()` calls in every admin dataset,
client-only table paging/search, complete multi-layer borehole graphs embedded
in map HTML, DOM-based map markers, synchronous interpolation after every CRUD
operation, repeated full interpolation snapshots on public cache reads, and no
lost-update detection for concurrent editors.

The schema already had useful primary, foreign-key, unique-code, coordinate,
bearing-capacity, and soil-type indexes. The standalone `idx_soil_borehole`
duplicated the leading column of `unique_borehole_layer`. Recent-record queries
lacked `(created_at, id)` indexes. CRUD was already transactional and CSV/SQL
exports were already streamed.

No upload, archive/restore, history, or document subsystem currently exists;
those cannot be load-tested or secured until their data model and retention
semantics are defined.

## Implemented changes

- Database-side pagination, bounded page sizes (10-100), server-side search,
  allowlisted sorting, and separate count queries for all admin record/report
  datasets and the Soil Records management table.
- `(created_at, id)` indexes for boreholes and soil layers; removal of the
  redundant soil-layer index.
- `lock_version` optimistic concurrency checking for edit forms, while retaining
  row locks and transactions for atomic borehole/layer replacement.
- Environment/local-file database configuration; the local credential file is
  ignored by Git and a safe example is committed.
- A database-owned interpolation revision counter maintained by transactional
  triggers. Public cached-result reads now validate one revision row instead of
  loading and hashing the full borehole/layer dataset. Direct SQL writes also
  advance the revision.
- Large interpolation regeneration is deferred after CRUD above 1,000
  boreholes, avoiding a long blocking save request. Regeneration remains
  available through the admin GIS action and CLI job.
- Map queries return one shallowest-layer summary and total layer count per
  borehole, omit large descriptions, and use Leaflet Canvas points rather than
  one DOM element per marker.
- The interpolation joined-row guard was raised to 250,000; leave-one-out
  validation remains capped at a deterministic 500 points.
- Backup output now includes revision state and invalidation triggers.
- Unsupported methods on the locations endpoint return HTTP 405.
- Map eligibility and marker summaries are read in one repeatable-read
  transaction, preventing a response from mixing pre-edit and post-edit rows.
- Seeded administrator credentials were removed from the baseline schema, and
  the application reuses one PDO connection per request.

Apply [scalability_migration.sql](../database/scalability_migration.sql) once to
an existing database. New databases receive the same schema from `sbcdb.sql`.

## Measured load results

The benchmark uses deterministic in-memory interpolation inputs and
connection-local temporary MySQL tables. It does not alter production records.
Times are from the local development machine and should be treated as relative,
not deployment guarantees.

| Records | Input validation | Exact IDW + validation | Page query (50 rows) | Map query | Map JSON | Peak PHP memory |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 100 | 6.53 ms | 128.48 ms | 3.78 ms | 8.86 ms | 43 KB | 8 MB |
| 1,000 | 23.73 ms | 1.94 s | 17.17 ms | 33.14 ms | 436 KB | 12 MB |
| 5,000 | 152.37 ms | 12.42 s | 60.61 ms | 144.16 ms | 2.19 MB | 38 MB |
| 10,000 | 421.50 ms | 25.72 s | 128.78 ms | 340.51 ms | 4.38 MB | 68 MB |

The exact all-neighbor IDW calculation is intentionally retained for
mathematical compatibility. It is the dominant large-data cost and must remain
an explicit/background regeneration task at scale. Paginated administration
and Canvas map preparation remain usable at 10,000 records, although a future
viewport/bounding-box endpoint is recommended before substantially exceeding
that level because the full map payload reaches several megabytes.

On the current 52-row development database, MySQL deliberately chooses a tiny
table scan plus filesort for the recent-borehole query; the correlated layer
count uses the covering `unique_borehole_layer` index. The revision lookup is a
single-row `const` primary-key lookup. The new recent-record index is present
for larger tables, where avoiding a scan becomes beneficial. Run
`php tools/explain-scalability.php` to inspect these plans locally.

## Verification

- 26 changed/new PHP files passed syntax validation.
- 11 PHP suites passed 1,724 checks in total, including CRUD, stale-edit
  rejection, exports, authorization, pagination/search, locations,
  interpolation correctness/publication/cache revision, and admin/public map
  rendering.
- Three JavaScript suites passed interpolation state/error behavior and both
  admin/public map integrations.
- `git diff --check` reported no whitespace errors. The local credential file
  is confirmed ignored by Git.

## Remaining recommendations

1. Add a scheduled worker/Task Scheduler entry for
   `php tools/regenerate-interpolation.php` in deployments expected to exceed
   the synchronous threshold.
2. Add viewport-based map point loading and a separate indexed server-side map
   search before growth beyond roughly 10,000 boreholes.
3. Design archive/history/document tables with explicit retention and access
   rules before adding those features; do not overload deletion semantics.
4. For six-figure datasets, evaluate MySQL spatial columns/indexes and compare
   scientifically justified local-neighbor IDW policies using real-field
   cross-validation before changing interpolation mathematics.
5. Run HTTP concurrency and browser-frame profiling in the target deployment;
   CLI timings do not include network latency, web-server limits, or device GPU
   behavior.
