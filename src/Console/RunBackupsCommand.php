<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Console;

use Aboleon\BackupManager\BackupManager;
use Illuminate\Console\Command;
use Throwable;

final class RunBackupsCommand extends Command
{
    protected $signature = 'backup-manager:run {--force-database : Back up the database even when no Laravel writes were tracked}';

    protected $description = 'Back up changed databases and synchronize configured media sources';

    public function handle(BackupManager $manager): int
    {
        $failed = false;

        try {
            $outcomes = $manager->run((bool) $this->option('force-database'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($outcomes as $outcome) {
            $this->line(sprintf('[%s] %s: %s', strtoupper($outcome->status), $outcome->sourceKey, $outcome->message));
            $failed = $failed || $outcome->status === 'failed';
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
