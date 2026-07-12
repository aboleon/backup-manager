<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $source_key
 * @property string $destination
 * @property string $status
 * @property int|null $covered_sequence
 * @property string|null $artifact_path
 * @property string|null $checksum
 * @property int|null $size
 * @property string|null $error
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
class BackupRun extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'backup_manager_runs';

    protected $keyType = 'string';

    protected $fillable = [
        'source_key',
        'destination',
        'status',
        'covered_sequence',
        'artifact_path',
        'checksum',
        'size',
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'covered_sequence' => 'integer',
            'size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
