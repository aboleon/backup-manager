<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\State;

use Aboleon\BackupManager\Models\BackupRun;
use Illuminate\Database\Eloquent\Builder;

final class BackupRunRepository
{
    public function __construct(private readonly ?string $connection = null) {}

    public function start(string $sourceKey, string $destination, ?int $coveredSequence = null): BackupRun
    {
        return $this->query()->create([
            'source_key' => $sourceKey,
            'destination' => $destination,
            'status' => 'running',
            'covered_sequence' => $coveredSequence,
            'started_at' => now(),
        ]);
    }

    public function succeed(
        BackupRun $run,
        ?string $artifactPath = null,
        ?string $checksum = null,
        ?int $size = null,
    ): void {
        $run->forceFill([
            'status' => 'successful',
            'artifact_path' => $artifactPath,
            'checksum' => $checksum,
            'size' => $size,
            'completed_at' => now(),
        ])->save();
    }

    public function fail(BackupRun $run, string $error): void
    {
        $run->forceFill([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
        ])->save();
    }

    /** @return Builder<BackupRun> */
    private function query(): Builder
    {
        return (new BackupRun)->setConnection($this->connection)->newQuery();
    }
}
