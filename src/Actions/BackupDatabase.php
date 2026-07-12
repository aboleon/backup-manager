<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Actions;

use Aboleon\BackupManager\Contracts\BackupDestination;
use Aboleon\BackupManager\Contracts\DatabaseDumper;
use Aboleon\BackupManager\Data\BackupOutcome;
use Aboleon\BackupManager\DatabaseSource;
use Aboleon\BackupManager\Models\BackupRun;
use Aboleon\BackupManager\State\BackupRunRepository;
use Aboleon\BackupManager\State\BackupStateRepository;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class BackupDatabase
{
    public function __construct(
        private readonly BackupStateRepository $state,
        private readonly BackupRunRepository $runs,
        private readonly DatabaseSource $source,
        private readonly DatabaseDumper $dumper,
        private readonly BackupDestination $destination,
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(bool $force = false): BackupOutcome
    {
        $sourceKey = $this->source->key();
        $source = $this->state->ensure($sourceKey, 'database');

        if (! $force && ! $source->needsBackup()) {
            return new BackupOutcome($sourceKey, 'skipped', 'Database unchanged since the last successful backup.');
        }

        $coveredSequence = $source->change_sequence;
        $run = $this->runs->start($sourceKey, $this->destination->name(), $coveredSequence);
        $this->state->markAttempted($sourceKey);
        $startedAt = now();
        $filename = $this->filename($startedAt);
        $localPath = $this->localPath($filename);

        try {
            $this->files->ensureDirectoryExists(dirname($localPath), 0700);
            $this->files->chmod(dirname($localPath), 0700);
            $this->dumper->dump($this->source->connection(), $localPath);
            $this->files->chmod($localPath, 0600);
            $checksum = hash_file('sha256', $localPath);
            $size = filesize($localPath);

            if ($checksum === false || $size === false || $size < 1) {
                throw new RuntimeException('The generated database backup is empty or unreadable.');
            }

            $remotePath = $this->remotePath($filename, $startedAt);
            $this->destination->upload($localPath, $remotePath);
            $this->runs->succeed($run, $remotePath, $checksum, $size);
            $this->state->markSuccessful($sourceKey, $coveredSequence);
            $this->prune();

            return new BackupOutcome($sourceKey, 'successful', "Uploaded {$remotePath}.");
        } catch (Throwable $exception) {
            $error = Str::limit($exception->getMessage(), 65000, '');
            $this->recordFailure($run, $sourceKey, $error);

            throw $exception;
        } finally {
            $this->files->delete($localPath);
        }
    }

    private function recordFailure(BackupRun $run, string $sourceKey, string $error): void
    {
        try {
            $this->state->markFailed($sourceKey, $error);
        } catch (Throwable $metadataException) {
            $this->logger->error('Could not update database backup source failure.', [
                'source' => $sourceKey,
                'exception' => $metadataException,
            ]);
        }

        try {
            $this->runs->fail($run, $error);
        } catch (Throwable $metadataException) {
            $this->logger->error('Could not update database backup run failure.', [
                'source' => $sourceKey,
                'exception' => $metadataException,
            ]);
        }
    }

    private function localPath(string $filename): string
    {
        return rtrim((string) $this->config->get('backup-manager.temporary_directory'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$filename;
    }

    private function remotePath(string $filename, CarbonInterface $startedAt): string
    {
        $path = trim((string) $this->config->get('backup-manager.database.remote_path', 'database'), '/');

        return $path.'/'.$startedAt->format('Y/m').'/'.$filename;
    }

    private function filename(CarbonInterface $startedAt): string
    {
        $application = Str::slug((string) $this->config->get('app.name', 'laravel')) ?: 'laravel';

        return $application.'-'.$startedAt->format('Y-m-d_His').'-'.Str::uuid().'.sql.gz';
    }

    private function prune(): void
    {
        try {
            $this->destination->prune(
                trim((string) $this->config->get('backup-manager.database.remote_path', 'database'), '/'),
                (int) $this->config->get('backup-manager.database.retention_days', 30),
            );
        } catch (Throwable $exception) {
            $this->logger->warning('Database backup succeeded, but pruning failed.', [
                'exception' => $exception,
            ]);
        }
    }
}
