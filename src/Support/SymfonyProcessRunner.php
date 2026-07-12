<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Support;

use Aboleon\BackupManager\Contracts\ProcessRunner;
use RuntimeException;
use Symfony\Component\Process\Process;

final class SymfonyProcessRunner implements ProcessRunner
{
    public function run(array $command, int $timeout): void
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Backup process failed.');
        }
    }
}
