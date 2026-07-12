<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Data;

final readonly class BackupOutcome
{
    public function __construct(
        public string $sourceKey,
        public string $status,
        public string $message,
    ) {}
}
