<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Console;

use Aboleon\BackupManager\BackupManager;
use Illuminate\Console\Command;
use Throwable;

final class MarkDatabaseChangedCommand extends Command
{
    protected $signature = 'backup-manager:mark-database-changed';

    protected $description = 'Mark the configured database as requiring a backup';

    public function handle(BackupManager $manager): int
    {
        try {
            $manager->markDatabaseChanged();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Database marked as changed.');

        return self::SUCCESS;
    }
}
