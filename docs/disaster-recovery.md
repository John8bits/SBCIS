# SBCIS disaster recovery

The administrator SQL download is a research-data disaster-recovery backup. It contains the application schema, locations, active and archived boreholes, soil layers, audit history, revision state, view, and interpolation invalidation triggers. Administrator rows are intentionally excluded so a stolen export cannot be used to recover account hashes. Audit administrator IDs are cleared during export so the history can restore before administrator bootstrap.

## Backup handling

1. Download only over HTTPS from an authenticated administrator session.
2. Encrypt the downloaded SQL file at rest using the organization's approved backup system.
3. Store at least one copy off-host and restrict it to recovery personnel.
4. Record the backup timestamp and periodically test it on an isolated empty database.

## Restore to an empty database

1. Create a new empty MySQL 8 database and least-privilege database account.
2. Import the SQL backup. Never automate this import against the production database name.
3. Set the `SBCIS_DB_*` environment variables for the restored instance.
4. Set `SBCIS_BOOTSTRAP_EMAIL` and `SBCIS_BOOTSTRAP_PASSWORD` through the deployment secret manager. Use a unique password of at least 12 characters.
5. Run `php tools/bootstrap-admin.php` once from the command line. It refuses to run if any administrator already exists.
6. Immediately clear both bootstrap environment variables and sign in to verify access.
7. Run `php tools/regenerate-interpolation.php`. The candidate remains withheld unless the reviewed publication policy variables are configured.
8. Run the regression and HTTP health checks before directing production traffic to the restored system.

The CSV downloads are research exports and are not disaster-recovery backups.
