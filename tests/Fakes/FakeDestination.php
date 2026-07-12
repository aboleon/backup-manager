<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Fakes;

use Aboleon\BackupManager\Contracts\BackupDestination;
use RuntimeException;

final class FakeDestination implements BackupDestination
{
    /** @var array<int, array{local: string, remote: string}> */
    public array $uploads = [];

    /** @var array<int, array{local: string, remote: string, archive: string}> */
    public array $syncs = [];

    public bool $failUpload = false;

    public bool $failSync = false;

    public function name(): string
    {
        return 'fake';
    }

    public function upload(string $localPath, string $remotePath): void
    {
        if ($this->failUpload) {
            throw new RuntimeException('Upload failed.');
        }

        $this->uploads[] = ['local' => $localPath, 'remote' => $remotePath];
    }

    public function sync(string $localPath, string $remotePath, string $archivePath): void
    {
        if ($this->failSync) {
            throw new RuntimeException('Synchronization failed.');
        }

        $this->syncs[] = ['local' => $localPath, 'remote' => $remotePath, 'archive' => $archivePath];
    }

    public function prune(string $remotePath, int $retentionDays): void
    {
        // No-op for tests.
    }
}
