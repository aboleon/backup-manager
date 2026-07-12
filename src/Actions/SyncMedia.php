<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Actions;

use Aboleon\BackupManager\Contracts\BackupDestination;
use Aboleon\BackupManager\Data\BackupOutcome;
use Aboleon\BackupManager\MediaSourcePreparer;
use Aboleon\BackupManager\Models\BackupRun;
use Aboleon\BackupManager\State\BackupRunRepository;
use Aboleon\BackupManager\State\BackupStateRepository;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class SyncMedia
{
    public function __construct(
        private readonly BackupStateRepository $state,
        private readonly BackupRunRepository $runs,
        private readonly BackupDestination $destination,
        private readonly MediaSourcePreparer $preparer,
        private readonly LoggerInterface $logger,
    ) {}

    /** @param array<string, mixed> $source */
    public function execute(array $source): BackupOutcome
    {
        $key = (string) ($source['key'] ?? 'media');
        $path = (string) ($source['path'] ?? '');

        if (! is_dir($path)) {
            throw new RuntimeException("Media source [{$key}] does not exist: {$path}");
        }

        $state = $this->state->ensure($key, 'media');
        $run = $this->runs->start($key, $this->destination->name(), $state->change_sequence);
        $this->state->markAttempted($key);
        $remotePath = trim((string) ($source['remote_path'] ?? "media/{$key}"), '/');
        $archiveRoot = trim((string) ($source['archive_path'] ?? "media-archive/{$key}"), '/');
        $prepared = null;

        try {
            $prepared = $this->preparer->prepare($source);
            $this->destination->sync(
                $prepared->path,
                $remotePath,
                $archiveRoot.'/'.now()->format('Y-m-d_His').'-'.Str::uuid(),
            );
            $this->runs->succeed($run, $remotePath);
            $this->state->markSuccessful($key, $state->change_sequence);
            $this->prune($archiveRoot, (int) ($source['retention_days'] ?? 30), $key);

            return new BackupOutcome($key, 'successful', "Synchronized {$remotePath}.");
        } catch (Throwable $exception) {
            $error = Str::limit($exception->getMessage(), 65000, '');
            $this->recordFailure($run, $key, $error);

            throw $exception;
        } finally {
            if ($prepared) {
                $this->preparer->cleanup($prepared);
            }
        }
    }

    private function recordFailure(BackupRun $run, string $sourceKey, string $error): void
    {
        try {
            $this->state->markFailed($sourceKey, $error);
        } catch (Throwable $metadataException) {
            $this->logger->error('Could not update media synchronization source failure.', [
                'source' => $sourceKey,
                'exception' => $metadataException,
            ]);
        }

        try {
            $this->runs->fail($run, $error);
        } catch (Throwable $metadataException) {
            $this->logger->error('Could not update media synchronization run failure.', [
                'source' => $sourceKey,
                'exception' => $metadataException,
            ]);
        }
    }

    private function prune(string $archiveRoot, int $retentionDays, string $key): void
    {
        try {
            $this->destination->prune($archiveRoot, $retentionDays);
        } catch (Throwable $exception) {
            $this->logger->warning('Media synchronization succeeded, but archive pruning failed.', [
                'source' => $key,
                'exception' => $exception,
            ]);
        }
    }
}
