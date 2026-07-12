<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Feature;

use Aboleon\BackupManager\ConfigurationValidator;
use Aboleon\BackupManager\Tests\TestCase;
use InvalidArgumentException;

final class ConfigurationValidatorTest extends TestCase
{
    public function test_it_rejects_duplicate_media_keys_before_running_backups(): void
    {
        $this->app['config']->set('backup-manager.media', [
            $this->mediaSource('duplicate', 'media/first', 'archive/first'),
            $this->mediaSource('duplicate', 'media/second', 'archive/second'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate media key [duplicate].');

        $this->app->make(ConfigurationValidator::class)->validate();
    }

    public function test_it_rejects_overlapping_live_and_archive_paths(): void
    {
        $this->app['config']->set('backup-manager.media', [
            $this->mediaSource('media', 'media/project', 'media/project/archive'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Overlapping media archive and remote paths');

        $this->app->make(ConfigurationValidator::class)->validate();
    }

    /** @return array<string, mixed> */
    private function mediaSource(string $key, string $remote, string $archive): array
    {
        return [
            'key' => $key,
            'path' => sys_get_temp_dir(),
            'remote_path' => $remote,
            'archive_path' => $archive,
            'retention_days' => 30,
        ];
    }
}
