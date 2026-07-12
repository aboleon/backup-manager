<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Contracts;

interface BackupDestination
{
    public function name(): string;

    public function upload(string $localPath, string $remotePath): void;

    public function sync(string $localPath, string $remotePath, string $archivePath): void;

    public function prune(string $remotePath, int $retentionDays): void;
}
