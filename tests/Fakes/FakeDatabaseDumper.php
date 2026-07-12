<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Fakes;

use Aboleon\BackupManager\Contracts\DatabaseDumper;

final class FakeDatabaseDumper implements DatabaseDumper
{
    public int $dumpCount = 0;

    public function dump(string $connection, string $targetPath): void
    {
        $this->dumpCount++;
        file_put_contents($targetPath, 'database dump for '.$connection);
    }
}
