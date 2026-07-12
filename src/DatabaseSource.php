<?php

declare(strict_types=1);

namespace Aboleon\BackupManager;

use Illuminate\Contracts\Config\Repository;

final readonly class DatabaseSource
{
    public function __construct(private Repository $config) {}

    public function connection(): string
    {
        return trim((string) $this->config->get('backup-manager.database.connection'));
    }

    public function key(): string
    {
        return 'database:'.$this->connection();
    }
}
