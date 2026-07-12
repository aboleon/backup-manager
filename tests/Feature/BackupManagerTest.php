<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Feature;

use Aboleon\BackupManager\BackupManager;
use Aboleon\BackupManager\Contracts\BackupDestination;
use Aboleon\BackupManager\Contracts\DatabaseDumper;
use Aboleon\BackupManager\Models\BackupRun;
use Aboleon\BackupManager\Models\BackupSource;
use Aboleon\BackupManager\Tests\Fakes\FakeDatabaseDumper;
use Aboleon\BackupManager\Tests\Fakes\FakeDestination;
use Aboleon\BackupManager\Tests\TestCase;

final class BackupManagerTest extends TestCase
{
    public function test_it_dumps_a_dirty_database_and_skips_it_after_success(): void
    {
        $dumper = new FakeDatabaseDumper;
        $destination = new FakeDestination;
        $this->app->instance(DatabaseDumper::class, $dumper);
        $this->app->instance(BackupDestination::class, $destination);

        $manager = $this->app->make(BackupManager::class);
        $first = $manager->run();
        $second = $manager->run();
        $forced = $manager->run(true);

        $this->assertSame('successful', $first[0]->status);
        $this->assertSame('skipped', $second[0]->status);
        $this->assertSame('successful', $forced[0]->status);
        $this->assertSame(2, $dumper->dumpCount);
        $this->assertCount(2, $destination->uploads);
        $this->assertNotSame($destination->uploads[0]['remote'], $destination->uploads[1]['remote']);
        $this->assertFileDoesNotExist($destination->uploads[0]['local']);
    }

    public function test_a_failed_upload_leaves_the_database_dirty_and_records_a_failed_run(): void
    {
        $destination = new FakeDestination;
        $destination->failUpload = true;
        $this->app->instance(DatabaseDumper::class, new FakeDatabaseDumper);
        $this->app->instance(BackupDestination::class, $destination);

        $outcome = $this->app->make(BackupManager::class)->run()[0];

        $this->assertSame('failed', $outcome->status);
        $this->assertTrue(BackupSource::query()->where('key', 'database:testing')->firstOrFail()->needsBackup());
        $this->assertSame('failed', BackupRun::query()->firstOrFail()->status);
    }
}
