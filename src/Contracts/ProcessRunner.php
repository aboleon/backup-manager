<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Contracts;

interface ProcessRunner
{
    /** @param array<int, string> $command */
    public function run(array $command, int $timeout): void;
}
