<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Fakes;

use Spatie\DbDumper\Databases\MySql;

final class FakeMySql extends MySql
{
    /** @var array<int, string> */
    public array $includedTables = [];

    /** @var array<int, string> */
    public array $excludedTables = [];

    public bool $schemaOnly = false;

    public bool $append = false;

    public function includeTables(string|array $includeTables): self
    {
        $this->includedTables = (array) $includeTables;

        return $this;
    }

    public function excludeTables(string|array $excludeTables): self
    {
        $this->excludedTables = (array) $excludeTables;

        return $this;
    }

    public function doNotDumpData(): self
    {
        $this->schemaOnly = true;

        return $this;
    }

    public function useAppendMode(): self
    {
        $this->append = true;

        return $this;
    }

    public function dumpToFile(string $dumpFile): void
    {
        file_put_contents(
            $dumpFile,
            $this->schemaOnly ? "backup manager schemas\n" : "application data\n",
            $this->append ? FILE_APPEND : 0,
        );
    }
}
