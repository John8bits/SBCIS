# SBCIS production deployment

**InfinityFree free hosting limitation:** This production profile is not deployable
there unchanged. InfinityFree does not provide custom PHP process environment
variables or the scheduled PHP CLI worker required below. The password-reset
EmailJS keys alone can be configured through the ignored
`config/email.local.php` file (see [password-reset.md](password-reset.md)), but
that does not satisfy the database, persistent cache, worker, and production
checks for the full SBCIS installation. Select a host with these capabilities
or separately redesign and verify the production infrastructure before treating
the site as deployment ready.

Use PHP 8.2+ and MySQL 8 with the web server document root restricted by the supplied Apache `.htaccess`, IIS `web.config`, or equivalent Nginx rules. The repository root remains the application root for compatibility, so the server must deny HTTP access to `tools`, `database`, `config`, `docs`, tests, dot-directories, SQL files, database files, logs, and backups.

## Required environment

- `SBCIS_ENV=production`
- `SBCIS_DB_HOST`, `SBCIS_DB_NAME`, `SBCIS_DB_USER`, `SBCIS_DB_PASSWORD`
- `SBCIS_INTERPOLATION_CACHE_DIR`: absolute persistent directory outside the web root, writable by PHP and shared by every instance
- `SBCIS_TRUST_PROXY=1` only when a trusted reverse proxy terminates TLS and overwrites `X-Forwarded-Proto`
- `SBCIS_SESSION_IDLE_SECONDS` and `SBCIS_SESSION_ABSOLUTE_SECONDS` when the defaults of 30 minutes and 8 hours are unsuitable

The application refuses production startup when database settings or persistent interpolation storage are absent, when the database user is `root`, or when HTTPS is not detected. HTTPS responses receive HSTS. Do not enable proxy trust unless direct client traffic cannot reach the application server.

## Interpolation publication

Pre-create the persistent cache as a writable directory outside the web root;
paths resolving into the project are rejected. Do not enable QA mode in production.
Valid source data produces a screening estimate after server-side regeneration.
The software checks boundary membership, duplicate coordinates, support distance,
minimum input count, and exact-location reproduction. MAE, RMSE, and spatial span
are reported as diagnostics; they are not unpublished thresholds supplied by the
application. An administrator must inspect source provenance and diagnostics.
The surface is an estimate for reference and planning, never a signed engineering
recommendation or substitute for site investigation. Policy version 5 invalidates
older cache artifacts; regenerate with the normal server configuration after upgrade.

## Scheduled regeneration

Run `php tools/regenerate-interpolation.php` from a single controlled worker after data changes and at a regular recovery interval. Restrict the command to the deployment service account. Monitor non-zero exit codes and the interpolation status shown in the admin GIS page.

The measured worker guard is 10,000 joined observation rows. Larger snapshots are rejected before generation because a 50,000-row preparation exceeded a 128 MB PHP worker. Split/review the source data or provision and re-benchmark a dedicated higher-memory worker before raising this limit.

## Database migrations

Back up the database, then apply these additive migrations in order before switching application traffic:

1. `database/auth_security_migration.sql`
2. `database/location_integrity_migration.sql`
3. `database/governance_migration.sql`
4. `database/password_reset_migration.sql`

Configure the server-side email API and public base URL as described in
[password-reset.md](password-reset.md) before advertising password recovery.

Run `php tools/test-production-readiness.php` using the deployment database account afterward. Existing out-of-bound boreholes are retained and flagged; no migration deletes or rewrites research values.

## Web-server checks

Before traffic is enabled, verify that `/tools/`, `/database/`, `/config/`, `/docs/`, `/db.db`, `/.git/`, and SQL/backup extensions return 403 or 404. Verify HTTP redirects to HTTPS and that security headers are present on HTML, JSON, and error responses.

## Rollback

Keep the pre-deployment application artifact and an encrypted database snapshot. If rollback is required, stop writes, preserve a separate copy of any post-deployment data, restore the pre-deployment database snapshot into the intended database, deploy the prior application artifact, and repeat the access/security checks before reopening traffic. Do not run the prior application against the migrated live database: it does not understand archived-state semantics.

## Post-deployment monitoring

- Alert on repeated login failures/throttling and unexpected administrator/audit events.
- Alert on interpolation worker non-zero exits, failed/review-required state, or revision lag.
- Monitor PHP/database errors, HTTP 5xx rates, map API latency, and truncated viewport responses.
- Confirm backup completion and periodically repeat the isolated restore test.
- Review out-of-bound and configured domain-warning records without changing research values automatically.
