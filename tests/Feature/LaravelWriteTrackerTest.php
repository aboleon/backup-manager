<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Feature;

use Aboleon\BackupManager\Models\BackupSource;
use Aboleon\BackupManager\State\BackupStateRepository;
use Aboleon\BackupManager\Tests\TestCase;
use Aboleon\BackupManager\Tracking\LaravelWriteTracker;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Mockery;

final class LaravelWriteTrackerTest extends TestCase
{
    public function test_it_marks_the_database_after_a_laravel_write(): void
    {
        $this->connection()->getSchemaBuilder()->create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });
        $this->app->make(LaravelWriteTracker::class)->forget();
        $this->connection()->table('articles')->insert(['title' => 'Changed']);

        $this->app->make(LaravelWriteTracker::class)->flush();

        $source = BackupSource::query()->where('key', 'database:testing')->firstOrFail();
        $this->assertSame(1, $source->change_sequence);
        $this->assertTrue($source->needsBackup());
        $this->assertFalse($this->connection()->hasModifiedRecords());
    }

    public function test_it_ignores_routine_infrastructure_table_writes(): void
    {
        $this->connection()->getSchemaBuilder()->create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('payload');
        });
        $tracker = $this->app->make(LaravelWriteTracker::class);
        $tracker->forget();
        $this->connection()->table('jobs')->insert(['payload' => 'routine queue state']);
        $this->connection()->table('jobs')->first();

        $tracker->flush();

        $this->assertFalse(BackupSource::query()->where('key', 'database:testing')->exists());
        $this->assertFalse($this->connection()->hasModifiedRecords());
    }

    public function test_it_ignores_prefixed_routine_infrastructure_table_writes(): void
    {
        $this->app['config']->set('database.connections.prefixed', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => 'app_',
        ]);
        $this->app['config']->set('backup-manager.database.connection', 'prefixed');

        $connection = $this->app->make('db')->connection('prefixed');
        $connection->getSchemaBuilder()->create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('payload');
        });

        $tracker = $this->app->make(LaravelWriteTracker::class);
        $tracker->forget();
        $connection->table('jobs')->insert(['payload' => 'routine queue state']);

        $tracker->flush();

        $this->assertFalse(BackupSource::query()->where('key', 'database:prefixed')->exists());
        $this->assertFalse($connection->hasModifiedRecords());
    }

    public function test_a_write_during_backup_remains_dirty_after_the_covered_sequence_succeeds(): void
    {
        $state = $this->app->make(BackupStateRepository::class);
        $state->markChanged('database:testing', 'database');
        $coveredSequence = $state->ensure('database:testing', 'database')->change_sequence;

        $state->markChanged('database:testing', 'database');
        $state->markSuccessful('database:testing', $coveredSequence);

        $source = BackupSource::query()->where('key', 'database:testing')->firstOrFail();
        $this->assertSame(2, $source->change_sequence);
        $this->assertSame(1, $source->backed_up_sequence);
        $this->assertTrue($source->needsBackup());
    }

    public function test_it_discards_changes_made_during_queue_jobs(): void
    {
        $this->connection()->getSchemaBuilder()->create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });
        $tracker = $this->app->make(LaravelWriteTracker::class);
        $tracker->forget();
        $job = $this->job();
        $this->app->make('events')->dispatch(new JobProcessing('database', $job));
        $this->connection()->table('articles')->insert(['title' => 'Queued change']);
        $this->app->make('events')->dispatch(new JobProcessed('database', $job));
        $tracker->flush();

        $this->assertFalse(BackupSource::query()->where('key', 'database:testing')->exists());
    }

    public function test_a_synchronous_job_does_not_erase_prior_request_changes(): void
    {
        $this->connection()->getSchemaBuilder()->create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });
        $tracker = $this->app->make(LaravelWriteTracker::class);
        $tracker->forget();
        $this->connection()->table('articles')->insert(['title' => 'Request change']);

        $job = $this->job();
        $this->app->make('events')->dispatch(new JobProcessing('sync', $job));
        $this->connection()->table('articles')->insert(['title' => 'Ignored queued change']);
        $this->app->make('events')->dispatch(new JobProcessed('sync', $job));
        $tracker->flush();

        $source = BackupSource::query()->where('key', 'database:testing')->firstOrFail();
        $this->assertSame(1, $source->change_sequence);
    }

    private function job(): Job
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn([]);

        return $job;
    }
}
