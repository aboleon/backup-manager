<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Contracts;

interface DatabaseDumper
{
    public function dump(string $connection, string $targetPath): void;
}
