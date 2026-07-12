<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tracking;

use Aboleon\BackupManager\DatabaseSource;
use Aboleon\BackupManager\State\BackupStateRepository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;

final class LaravelWriteTracker
{
    private bool $pending = false;

    private int $ignoreDepth = 0;

    /** @param array<int, string> $ignoredTables */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly BackupStateRepository $state,
        private readonly DatabaseSource $source,
        private readonly MutationTable $mutationTable,
        private readonly array $ignoredTables,
    ) {}

    public function record(QueryExecuted $query): void
    {
        if ($this->ignoreDepth > 0 || $query->connectionName !== $this->source->connection()) {
            return;
        }

        $table = $this->mutationTable->fromSql($query->sql);

        if (! $table && ! $this->mutationTable->isMutation($query->sql)) {
            return;
        }

        if ($table) {
            $table = $this->withoutConnectionPrefix($query, $table);
        }

        if ($table && Str::is($this->ignoredTables, $table)) {
            return;
        }

        $this->pending = true;
    }

    public function flush(): void
    {
        $connection = $this->connection();

        try {
            if ($this->pending && $this->state->available()) {
                $this->state->markChanged($this->source->key(), 'database');
            }
        } finally {
            $this->pending = false;
            $connection->forgetRecordModificationState();
        }
    }

    public function forget(): void
    {
        $this->pending = false;
        $this->ignoreDepth = 0;
        $this->connection()->forgetRecordModificationState();
    }

    public function beginIgnoring(): void
    {
        $this->ignoreDepth++;
    }

    public function endIgnoring(): void
    {
        $this->ignoreDepth = max(0, $this->ignoreDepth - 1);
    }

    private function connection(): Connection
    {
        return $this->database->connection($this->source->connection());
    }

    private function withoutConnectionPrefix(QueryExecuted $query, string $table): string
    {
        $prefix = $query->connection->getTablePrefix();

        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            return substr($table, strlen($prefix));
        }

        return $table;
    }
}
