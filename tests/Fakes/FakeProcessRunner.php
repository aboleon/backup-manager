<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Fakes;

use Aboleon\BackupManager\Contracts\ProcessRunner;

final class FakeProcessRunner implements ProcessRunner
{
    /** @var array<int, array{command: array<int, string>, timeout: int}> */
    public array $runs = [];

    public function run(array $command, int $timeout): void
    {
        $this->runs[] = compact('command', 'timeout');
    }
}
