<?php

declare(strict_types=1);

use Aboleon\BackupManager\Destinations\GoogleDriveDestination;
use Aboleon\BackupManager\Dumpers\MySqlDatabaseDumper;

return [
    'state_connection' => env('BACKUP_MANAGER_STATE_CONNECTION'),

    'tracking' => [
        'enabled' => env('BACKUP_MANAGER_TRACK_WRITES', true),
        'ignored_tables' => [
            'backup_manager_*',
            'cache',
            'cache_locks',
            'sessions',
            'jobs',
            'job_batches',
            'failed_jobs',
        ],
    ],

    'database' => [
        'enabled' => true,
        'connection' => env('DB_CONNECTION', 'mysql'),
        'remote_path' => 'database',
        'retention_days' => 30,
    ],

    'dumper' => [
        'driver' => MySqlDatabaseDumper::class,
        'options' => [
            'timeout' => 300,
            'binary_path' => null,
            'skip_ssl' => false,
            'disable_column_statistics' => false,
            'exclude_data_tables' => [
                'backup_manager_sources',
                'backup_manager_runs',
            ],
        ],
    ],

    'media' => [],

    'destination' => [
        'driver' => GoogleDriveDestination::class,
        'options' => [
            'binary' => env('BACKUP_MANAGER_RCLONE_BINARY', 'rclone'),
            'config' => env('BACKUP_MANAGER_RCLONE_CONFIG'),
            'remote' => env('BACKUP_MANAGER_RCLONE_REMOTE', 'google-drive'),
            'root' => env('BACKUP_MANAGER_REMOTE_ROOT', 'backups'),
            'timeout' => (int) env('BACKUP_MANAGER_PROCESS_TIMEOUT', 3600),
            'transfers' => (int) env('BACKUP_MANAGER_TRANSFERS', 4),
        ],
    ],

    'temporary_directory' => storage_path('app/backup-manager'),

    'schedule' => [
        'enabled' => env('BACKUP_MANAGER_SCHEDULE_ENABLED', false),
        'time' => env('BACKUP_MANAGER_SCHEDULE_TIME', '23:30'),
        'timezone' => env('BACKUP_MANAGER_SCHEDULE_TIMEZONE', config('app.timezone')),
        'environments' => ['production'],
        'on_one_server' => env('BACKUP_MANAGER_ON_ONE_SERVER', false),
    ],
];
