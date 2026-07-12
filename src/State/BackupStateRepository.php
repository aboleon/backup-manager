<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\State;

use Aboleon\BackupManager\Models\BackupSource;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Collection;

final class BackupStateRepository
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ?string $connection = null,
    ) {}

    public function available(): bool
    {
        return $this->schema()->hasTable((new BackupSource)->getTable());
    }

    public function ensure(string $key, string $type): BackupSource
    {
        return $this->query()->firstOrCreate(
            ['key' => $key],
            [
                'type' => $type,
                'change_sequence' => 1,
                'last_changed_at' => now(),
            ],
        );
    }

    public function markChanged(string $key, string $type): void
    {
        $now = now();
        $this->connection()->table((new BackupSource)->getTable())->insertOrIgnore([
            'key' => $key,
            'type' => $type,
            'change_sequence' => 0,
            'backed_up_sequence' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->query()->where('key', $key)->update([
            'change_sequence' => $this->connection()->raw('change_sequence + 1'),
            'last_changed_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function markAttempted(string $key): void
    {
        $this->query()->where('key', $key)->update([
            'last_attempted_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markSuccessful(string $key, int $coveredSequence): void
    {
        $this->connection()->transaction(function () use ($key, $coveredSequence): void {
            $query = $this->query()->where('key', $key);
            $query->getQuery()->lockForUpdate();
            $source = $query->firstOrFail();
            $source->forceFill([
                'backed_up_sequence' => max($source->backed_up_sequence, $coveredSequence),
                'last_successful_backup_at' => now(),
                'last_error' => null,
            ])->save();
        });
    }

    public function markFailed(string $key, string $error): void
    {
        $this->query()->where('key', $key)->update([
            'last_error' => $error,
        ]);
    }

    /** @return Collection<int, BackupSource> */
    public function all(): Collection
    {
        return $this->query()->get()->sortBy('key')->values();
    }

    /** @return Builder<BackupSource> */
    private function query(): Builder
    {
        return (new BackupSource)->setConnection($this->connection)->newQuery();
    }

    private function connection(): Connection
    {
        return $this->database->connection($this->connection);
    }

    private function schema(): SchemaBuilder
    {
        return $this->connection()->getSchemaBuilder();
    }
}
