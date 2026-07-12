<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Destinations;

use Aboleon\BackupManager\Contracts\BackupDestination;
use Aboleon\BackupManager\Contracts\ProcessRunner;

final class GoogleDriveDestination implements BackupDestination
{
    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly GoogleDriveConfig $config,
    ) {}

    public function name(): string
    {
        return 'google-drive';
    }

    public function upload(string $localPath, string $remotePath): void
    {
        $this->runner->run(
            $this->command('copyto', [$localPath, $this->remotePath($remotePath)]),
            $this->config->timeout,
        );
    }

    public function sync(string $localPath, string $remotePath, string $archivePath): void
    {
        $arguments = [
            $localPath,
            $this->remotePath($remotePath),
            '--backup-dir',
            $this->remotePath($archivePath),
            '--create-empty-src-dirs',
            '--transfers',
            (string) $this->config->transfers,
        ];

        $this->runner->run($this->command('sync', $arguments), $this->config->timeout);
    }

    public function prune(string $remotePath, int $retentionDays): void
    {
        if ($retentionDays < 1) {
            return;
        }

        $this->runner->run(
            $this->command('delete', [
                $this->remotePath($remotePath),
                '--min-age',
                $retentionDays.'d',
                '--rmdirs',
            ]),
            $this->config->timeout,
        );
    }

    /** @param array<int, string> $arguments
     * @return array<int, string>
     */
    private function command(string $operation, array $arguments): array
    {
        $command = [$this->config->binary, $operation, ...$arguments];

        if ($this->config->config) {
            $command[] = '--config';
            $command[] = $this->config->config;
        }

        return $command;
    }

    private function remotePath(string $path): string
    {
        $path = trim($path, '/');
        $root = $this->config->root ? $this->config->root.'/' : '';

        return $this->config->remote.':'.$root.$path;
    }
}
