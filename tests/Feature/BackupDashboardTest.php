<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Feature;

use Aboleon\BackupManager\Models\BackupRun;
use Aboleon\BackupManager\Models\BackupSource;
use Aboleon\BackupManager\Tests\TestCase;

final class BackupDashboardTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('backup-manager.ui.enabled', true);
        $app['config']->set('backup-manager.ui.middleware', ['web']);
        $app['config']->set('backup-manager.ui.per_page', 1);
    }

    public function test_dashboard_displays_source_state_and_paginated_run_history(): void
    {
        BackupSource::query()->create([
            'key' => 'database:testing',
            'type' => 'database',
            'change_sequence' => 2,
            'backed_up_sequence' => 1,
            'last_error' => '<script>alert(1)</script>',
        ]);
        BackupRun::query()->create([
            'source_key' => 'database:testing',
            'destination' => 'google-drive',
            'status' => 'failed',
            'error' => 'Upload failed',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
        BackupRun::query()->create([
            'source_key' => 'project-media',
            'destination' => 'google-drive',
            'status' => 'successful',
            'artifact_path' => 'media/project',
            'size' => 1024,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('backup-manager.index'));

        $response->assertOk()
            ->assertSeeInOrder(['Source', 'Type', 'State', 'Last change', 'Last attempt', 'Last success', 'Last error'])
            ->assertSeeInOrder(['Started', 'Source', 'Status', 'Destination', 'Artifact', 'Size', 'Completed', 'Error'])
            ->assertSee('database:testing')
            ->assertSee('Backup required')
            ->assertSee('Upload failed')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('class="pagination"', false)
            ->assertSee('?page=2', false);
    }
}
