<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $type
 * @property int $change_sequence
 * @property int $backed_up_sequence
 * @property Carbon|null $last_changed_at
 * @property Carbon|null $last_attempted_at
 * @property Carbon|null $last_successful_backup_at
 * @property string|null $last_error
 */
class BackupSource extends Model
{
    protected $table = 'backup_manager_sources';

    protected $fillable = [
        'key',
        'type',
        'change_sequence',
        'backed_up_sequence',
        'last_changed_at',
        'last_attempted_at',
        'last_successful_backup_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'change_sequence' => 'integer',
            'backed_up_sequence' => 'integer',
            'last_changed_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'last_successful_backup_at' => 'datetime',
        ];
    }

    public function needsBackup(): bool
    {
        return $this->change_sequence > $this->backed_up_sequence;
    }
}
