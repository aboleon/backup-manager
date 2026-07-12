<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Data;

final readonly class PreparedMediaSource
{
    public function __construct(
        public string $path,
        public ?string $temporaryPath = null,
    ) {}
}
