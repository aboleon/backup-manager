<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Console;

use Aboleon\BackupManager\Models\BackupSource;
use Aboleon\BackupManager\State\BackupStateRepository;
use Illuminate\Console\Command;

final class BackupStatusCommand extends Command
{
    protected $signature = 'backup-manager:status';

    protected $description = 'Display backup source freshness and the most recent error';

    public function handle(BackupStateRepository $state): int
    {
        if (! $state->available()) {
            $this->error('Backup Manager tables are missing. Run the application migrations first.');

            return self::FAILURE;
        }

        $this->table(
            ['Source', 'Type', 'State', 'Changed', 'Last success', 'Last error'],
            $state->all()->map(fn (BackupSource $source): array => [
                $source->key,
                $source->type,
                $source->needsBackup() ? 'dirty' : 'clean',
                $source->last_changed_at?->toDateTimeString() ?? '—',
                $source->last_successful_backup_at?->toDateTimeString() ?? '—',
                $source->last_error ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
