<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Feature;

use Aboleon\BackupManager\Dumpers\MySqlDatabaseDumper;
use Aboleon\BackupManager\Tests\Fakes\FakeMySql;
use Aboleon\BackupManager\Tests\Fakes\FakeMySqlDumperFactory;
use Aboleon\BackupManager\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MySqlDatabaseDumperTest extends TestCase
{
    public function test_it_dumps_application_data_and_appends_backup_table_schemas(): void
    {
        $this->app['config']->set('database.connections.dump-test', [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'application',
            'username' => 'user',
            'password' => 'secret',
        ]);
        $this->app['config']->set('backup-manager.dumper.options.exclude_data_tables', [
            'backup_manager_sources',
            'backup_manager_runs',
        ]);
        $dataDumper = new FakeMySql;
        $schemaDumper = new FakeMySql;
        $target = sys_get_temp_dir().'/'.Str::uuid().'.sql.gz';
        $dumper = new MySqlDatabaseDumper(
            $this->app['config'],
            new Filesystem,
            new FakeMySqlDumperFactory([$dataDumper, $schemaDumper]),
        );

        try {
            $dumper->dump('dump-test', $target);

            $this->assertSame(
                ['backup_manager_sources', 'backup_manager_runs'],
                $dataDumper->excludedTables,
            );
            $this->assertSame(
                ['backup_manager_sources', 'backup_manager_runs'],
                $schemaDumper->includedTables,
            );
            $this->assertTrue($schemaDumper->schemaOnly);
            $this->assertTrue($schemaDumper->append);
            $this->assertSame("application data\nbackup manager schemas\n", gzdecode((string) file_get_contents($target)));
        } finally {
            @unlink($target);
        }
    }
}
