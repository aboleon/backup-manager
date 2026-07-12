<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Destinations;

use InvalidArgumentException;

final readonly class GoogleDriveConfig
{
    public function __construct(
        public string $binary,
        public ?string $config,
        public string $remote,
        public string $root,
        public int $timeout,
        public int $transfers,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $instance = new self(
            binary: trim((string) ($config['binary'] ?? 'rclone')),
            config: filled($config['config'] ?? null) ? (string) $config['config'] : null,
            remote: trim((string) ($config['remote'] ?? 'google-drive')),
            root: trim((string) ($config['root'] ?? 'backups'), '/'),
            timeout: (int) ($config['timeout'] ?? 3600),
            transfers: (int) ($config['transfers'] ?? 4),
        );

        $instance->validate();

        return $instance;
    }

    private function validate(): void
    {
        if ($this->binary === '') {
            throw new InvalidArgumentException('The rclone binary cannot be empty.');
        }

        if ($this->remote === '' || str_contains($this->remote, ':')) {
            throw new InvalidArgumentException('The rclone remote must be a non-empty name without a colon.');
        }

        if ($this->root !== '' && in_array('..', explode('/', $this->root), true)) {
            throw new InvalidArgumentException('The rclone root cannot contain parent-directory segments.');
        }

        if ($this->timeout < 1 || $this->transfers < 1) {
            throw new InvalidArgumentException('The rclone timeout and transfers values must be positive.');
        }

        if ($this->config !== null && ! is_file($this->config)) {
            throw new InvalidArgumentException("The rclone configuration file does not exist: {$this->config}");
        }
    }
}
