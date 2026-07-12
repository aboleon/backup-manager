<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Unit;

use Aboleon\BackupManager\Destinations\GoogleDriveConfig;
use Aboleon\BackupManager\Destinations\GoogleDriveDestination;
use Aboleon\BackupManager\Tests\Fakes\FakeProcessRunner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GoogleDriveDestinationTest extends TestCase
{
    public function test_it_builds_a_versioned_google_drive_sync_command(): void
    {
        $runner = new FakeProcessRunner;
        $destination = new GoogleDriveDestination($runner, new GoogleDriveConfig(
            binary: 'rclone',
            config: null,
            remote: 'google-drive',
            root: 'site-backups',
            timeout: 600,
            transfers: 3,
        ));

        $destination->sync('/srv/media', 'media/project', 'media-archive/project/2026-07-11');

        $this->assertSame([
            'rclone',
            'sync',
            '/srv/media',
            'google-drive:site-backups/media/project',
            '--backup-dir',
            'google-drive:site-backups/media-archive/project/2026-07-11',
            '--create-empty-src-dirs',
            '--transfers',
            '3',
        ], $runner->runs[0]['command']);
    }

    public function test_it_rejects_an_invalid_remote_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GoogleDriveConfig::fromArray([
            'remote' => 'google-drive:',
        ]);
    }
}
