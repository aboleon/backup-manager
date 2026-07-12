<?php

declare(strict_types=1);

namespace Aboleon\BackupManager;

use Aboleon\BackupManager\Actions\BackupDatabase;
use Aboleon\BackupManager\Actions\SyncMedia;
use Aboleon\BackupManager\Data\BackupOutcome;
use Aboleon\BackupManager\State\BackupStateRepository;
use Illuminate\Contracts\Config\Repository;
use Throwable;

final class BackupManager
{
    public function __construct(
        private readonly BackupDatabase $backupDatabase,
        private readonly SyncMedia $syncMedia,
        private readonly BackupStateRepository $state,
        private readonly DatabaseSource $databaseSource,
        private readonly ConfigurationValidator $configuration,
        private readonly Repository $config,
    ) {}

    /** @return array<int, BackupOutcome> */
    public function run(bool $forceDatabase = false): array
    {
        $this->configuration->validate();
        $this->guardStateTablesExist();
        $outcomes = [];

        if ((bool) $this->config->get('backup-manager.database.enabled', true)) {
            try {
                $outcomes[] = $this->backupDatabase->execute($forceDatabase);
            } catch (Throwable $exception) {
                $outcomes[] = new BackupOutcome(
                    $this->databaseSource->key(),
                    'failed',
                    $exception->getMessage(),
                );
            }
        }

        foreach ((array) $this->config->get('backup-manager.media', []) as $source) {
            try {
                $outcomes[] = $this->syncMedia->execute((array) $source);
            } catch (Throwable $exception) {
                $outcomes[] = new BackupOutcome(
                    (string) ($source['key'] ?? 'media'),
                    'failed',
                    $exception->getMessage(),
                );
            }
        }

        return $outcomes;
    }

    public function markDatabaseChanged(): void
    {
        $this->configuration->validate();
        $this->guardStateTablesExist();
        $this->state->markChanged(
            $this->databaseSource->key(),
            'database',
        );
    }

    private function guardStateTablesExist(): void
    {
        if (! $this->state->available()) {
            throw new \RuntimeException('Backup Manager tables are missing. Run the application migrations first.');
        }
    }
}
