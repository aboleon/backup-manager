<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests;

use Aboleon\BackupManager\BackupManagerServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BackupManagerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('backup-manager.database.connection', 'testing');
        $app['config']->set('backup-manager.temporary_directory', sys_get_temp_dir().'/backup-manager-tests');
        $app['config']->set('backup-manager.media', []);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require __DIR__.'/../database/migrations/2026_07_11_122816_create_backup_manager_tables.php';
        $migration->up();
        $this->connection()->forgetRecordModificationState();
    }

    protected function tearDown(): void
    {
        $migration = require __DIR__.'/../database/migrations/2026_07_11_122816_create_backup_manager_tables.php';
        $migration->down();

        parent::tearDown();
    }

    protected function connection(): Connection
    {
        /** @var DatabaseManager $database */
        $database = $this->app->make('db');
        /** @var Connection $connection */
        $connection = $database->connection('testing');

        return $connection;
    }
}
