# Aboleon Backup Manager

Change-aware database backups and incremental media synchronization for Laravel applications.

The package records when the Laravel application changes the database, creates a compressed database dump only when that database is dirty, and synchronizes configured media directories on a schedule. Google Drive through `rclone` is the first destination implementation. Additional destinations can implement the same contract later.

## Requirements

- PHP 8.3 or newer
- PHP `zlib` extension
- Laravel 11, 12, or 13
- `mysqldump` available on the server for MySQL backups
- `rclone` available on the server for Google Drive transfers
- A cron entry that invokes Laravel's scheduler

## Installation

Require the package:

```bash
composer require aboleon/backup-manager
```

Optionally publish its configuration:

```bash
php artisan vendor:publish --tag=backup-manager-config
```

Run the application migrations:

```bash
php artisan migrate
```

This creates:

- `backup_manager_sources`, which stores dirty and successfully backed-up sequences.
- `backup_manager_runs`, which stores execution status, remote artifact paths, checksums, sizes, and errors.

## Change tracking

The package listens to SQL mutations executed through the configured backup database connection. Eloquent, Laravel's query builder, raw Laravel statements, common-table-expression mutations, bulk data loads, and migrations are covered.

Tracking is flushed after normal HTTP requests and non-backup Artisan commands. Queue jobs are deliberately not tracked. Writes to the following infrastructure tables are ignored by default:

```php
'ignored_tables' => [
    'backup_manager_*',
    'cache',
    'cache_locks',
    'sessions',
    'jobs',
    'job_batches',
    'failed_jobs',
],
```

The source key is derived from the configured connection as `database:{connection}`; it is not configured separately. The database needs a new backup when its `change_sequence` is greater than its `backed_up_sequence`. A backup only covers the sequence captured when the dump starts. If another tracked change occurs while the backup is running, the database remains dirty for the next run.

For an exceptional workflow, the database can be marked explicitly:

```bash
php artisan backup-manager:mark-database-changed
```

Application code may also inject `Aboleon\BackupManager\BackupManager` and call:

```php
$backupManager->markDatabaseChanged();
```

## Database backup

MySQL dumps use `mysqldump` with a single transaction, quick row retrieval, and table locking disabled. Dumps use collision-resistant UUID filenames, request `0700` directory and `0600` file permissions, are compressed as `.sql.gz`, hashed with SHA-256, uploaded, and then removed from local temporary storage.

Backup Manager table schemas are included in the dump, but their runtime data is excluded. A restored database therefore does not inherit stale backup state.

Example configuration:

```php
'database' => [
    'enabled' => true,
    'connection' => env('DB_CONNECTION', 'mysql'),
    'remote_path' => 'database',
    'retention_days' => 30,
],

'dumper' => [
    'driver' => Aboleon\BackupManager\Dumpers\MySqlDatabaseDumper::class,
    'options' => [
        'timeout' => 300,
        'binary_path' => env('BACKUP_MANAGER_MYSQL_DUMP_PATH'),
        'skip_ssl' => false,
        'disable_column_statistics' => false,
        'exclude_data_tables' => [
            'backup_manager_sources',
            'backup_manager_runs',
        ],
    ],
],
```

`BACKUP_MANAGER_MYSQL_DUMP_PATH` is the directory containing `mysqldump`, not the complete executable path. It can remain empty when the binary is on `PATH`.

## Media synchronization

Media sources are synchronized every time the scheduled command runs. `rclone` scans the source but transfers only changed or new files.

Remote files that are replaced or deleted locally are moved into the configured dated archive before synchronization. Archive files older than `retention_days` are removed.

```php
'media' => [
    [
        'key' => 'project-media',
        'path' => public_path('media'),
        'remote_path' => 'media/project',
        'archive_path' => 'media-archive/project',
        'retention_days' => 30,
    ],
],
```

Each source key and local path must be unique. Live and archive remote paths must not duplicate, contain, or overlap one another or the database backup path. Invalid configurations fail before a backup starts.

Use `exclude` patterns to omit a subtree from one source. Use `overlays` when two local directories represent the same relative media tree: the primary path wins for duplicate relative paths, while files found only in overlays are retained. Backup Manager prepares a temporary merged tree using hard links where supported, so duplicate content is not uploaded twice.

```php
[
    'key' => 'legacy-pages',
    'path' => public_path('legacy/pages'),
    'overlays' => [public_path('old-media/pages')],
    'exclude' => ['temporary/**'],
    'remote_path' => 'media/legacy-pages',
    'archive_path' => 'media-archive/legacy-pages',
    'retention_days' => 30,
],
```

## Google Drive setup

Install `rclone` on the server, then create a Google Drive remote:

```bash
rclone config
```

Use a dedicated Google account or a Google Workspace Shared Drive. The configured remote name must match `BACKUP_MANAGER_RCLONE_REMOTE`.

For sensitive database backups, configure an `rclone crypt` remote on top of the Google Drive remote and use the crypt remote name in `BACKUP_MANAGER_RCLONE_REMOTE`. This encrypts file contents and names before they reach Google Drive.

Relevant environment settings:

```dotenv
BACKUP_MANAGER_RCLONE_BINARY=rclone
BACKUP_MANAGER_RCLONE_CONFIG=/secure/path/rclone.conf
BACKUP_MANAGER_RCLONE_REMOTE=google-drive
BACKUP_MANAGER_REMOTE_ROOT=my-application
BACKUP_MANAGER_PROCESS_TIMEOUT=3600
BACKUP_MANAGER_TRANSFERS=4
```

Keep `rclone.conf` outside the application repository and restrict its filesystem permissions because it contains access credentials.

Test the configured remote before enabling scheduled backups:

```bash
rclone lsd google-drive:
```

Official setup documentation: <https://rclone.org/drive/>

## Scheduling

Enable the package-owned schedule:

```dotenv
BACKUP_MANAGER_SCHEDULE_ENABLED=true
BACKUP_MANAGER_SCHEDULE_TIME=23:30
BACKUP_MANAGER_SCHEDULE_TIMEZONE=Europe/Paris
BACKUP_MANAGER_ON_ONE_SERVER=false
```

The schedule runs only in the `production` environment by default and uses Laravel's `withoutOverlapping()` protection.

The server must invoke Laravel's scheduler every minute:

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

Confirm the event is registered:

```bash
php artisan schedule:list
```

## Commands

Run all configured backups:

```bash
php artisan backup-manager:run
```

Force a database dump even when the database is clean:

```bash
php artisan backup-manager:run --force-database
```

Inspect source state and recent errors:

```bash
php artisan backup-manager:status
```

Explicitly mark the database dirty:

```bash
php artisan backup-manager:mark-database-changed
```

The run command returns a non-zero exit code when any configured source fails.

## Restoring

Backup Manager currently creates and transports backups; it does not automatically overwrite or restore application data.

To restore a database, download the required `.sql.gz` artifact, verify its SHA-256 checksum against `backup_manager_runs`, decompress it, and import it with the appropriate MySQL client. Restore media by copying the selected synchronized directory or archived files from the remote destination.

Restoration should always be tested in a non-production environment first.

## Adding another destination

Implement `Aboleon\BackupManager\Contracts\BackupDestination`:

```php
interface BackupDestination
{
    public function name(): string;

    public function upload(string $localPath, string $remotePath): void;

    public function sync(string $localPath, string $remotePath, string $archivePath): void;

    public function prune(string $remotePath, int $retentionDays): void;
}
```

Then configure the implementation class:

```php
'destination' => [
    'driver' => App\Backup\FtpDestination::class,
],
```

Database generation, dirty-state tracking, commands, and scheduling do not need to change when a new destination is introduced.

## Testing

Install development dependencies inside the package and run:

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```
