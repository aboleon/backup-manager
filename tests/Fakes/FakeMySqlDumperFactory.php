<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Fakes;

use Aboleon\BackupManager\Dumpers\MySqlDumperFactory;
use RuntimeException;
use Spatie\DbDumper\Databases\MySql;

final class FakeMySqlDumperFactory extends MySqlDumperFactory
{
    /** @param array<int, FakeMySql> $dumpers */
    public function __construct(private array $dumpers) {}

    public function create(): MySql
    {
        return array_shift($this->dumpers)
            ?? throw new RuntimeException('No fake MySQL dumper remains.');
    }
}
