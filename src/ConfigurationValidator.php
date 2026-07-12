<?php

declare(strict_types=1);

namespace Aboleon\BackupManager;

use Aboleon\BackupManager\Contracts\BackupDestination;
use Aboleon\BackupManager\Contracts\DatabaseDumper;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

final readonly class ConfigurationValidator
{
    public function __construct(
        private Repository $config,
        private DatabaseSource $databaseSource,
    ) {}

    public function validate(): void
    {
        $this->validateDatabase();
        $this->validateDriver('backup-manager.dumper.driver', DatabaseDumper::class);
        $this->validateDriver('backup-manager.destination.driver', BackupDestination::class);
        $this->validateTemporaryDirectory();
        $this->validateMediaSources();
    }

    private function validateDatabase(): void
    {
        $connection = $this->databaseSource->connection();

        if ($connection === '' || ! is_array($this->config->get("database.connections.{$connection}"))) {
            throw new InvalidArgumentException("Backup database connection [{$connection}] is not configured.");
        }

        $stateConnection = trim((string) $this->config->get('backup-manager.state_connection'));
        if ($stateConnection !== '' && ! is_array($this->config->get("database.connections.{$stateConnection}"))) {
            throw new InvalidArgumentException("Backup state connection [{$stateConnection}] is not configured.");
        }

        if ((int) $this->config->get('backup-manager.database.retention_days', 30) < 0) {
            throw new InvalidArgumentException('Database retention_days cannot be negative.');
        }

        $remotePath = trim((string) $this->config->get('backup-manager.database.remote_path'), '/');
        if ($remotePath === '' || in_array('..', explode('/', $remotePath), true)) {
            throw new InvalidArgumentException('Database remote_path is invalid.');
        }
    }

    /** @param class-string $contract */
    private function validateDriver(string $key, string $contract): void
    {
        $driver = $this->config->get($key);

        if (! is_string($driver) || ! class_exists($driver) || ! is_a($driver, $contract, true)) {
            throw new InvalidArgumentException("Configured driver [{$key}] must implement {$contract}.");
        }
    }

    private function validateTemporaryDirectory(): void
    {
        if (trim((string) $this->config->get('backup-manager.temporary_directory')) === '') {
            throw new InvalidArgumentException('Backup temporary_directory cannot be empty.');
        }
    }

    private function validateMediaSources(): void
    {
        $keys = [$this->databaseSource->key()];
        $localPaths = [];
        $remotePaths = [trim((string) $this->config->get('backup-manager.database.remote_path'), '/')];
        $archivePaths = [];

        foreach ((array) $this->config->get('backup-manager.media', []) as $index => $source) {
            if (! is_array($source)) {
                throw new InvalidArgumentException("Media source [{$index}] must be an array.");
            }

            $key = $this->requiredValue($source, 'key', $index);
            $localPath = $this->requiredValue($source, 'path', $index);
            $normalizedLocalPath = str_replace('\\', '/', rtrim($localPath, '/\\'));
            $remotePath = $this->remoteValue($source, 'remote_path', $index);
            $archivePath = $this->remoteValue($source, 'archive_path', $index);

            $this->guardUnique($keys, $key, 'media key');
            $this->guardNoOverlap($localPaths, $normalizedLocalPath, 'media local path');
            $this->guardNoOverlap($remotePaths, $remotePath, 'media remote path');
            $this->guardNoOverlap($archivePaths, $archivePath, 'media archive path');

            if (! is_dir($localPath)) {
                throw new InvalidArgumentException("Media source [{$key}] does not exist: {$localPath}");
            }

            foreach ((array) ($source['overlays'] ?? []) as $overlay) {
                if (! is_string($overlay) || ! is_dir($overlay)) {
                    throw new InvalidArgumentException("Media source [{$key}] has an invalid overlay path.");
                }
            }

            foreach ((array) ($source['exclude'] ?? []) as $exclude) {
                if (! is_string($exclude) || trim($exclude) === '') {
                    throw new InvalidArgumentException("Media source [{$key}] has an invalid exclude pattern.");
                }
            }

            if ((int) ($source['retention_days'] ?? 30) < 0) {
                throw new InvalidArgumentException("Media source [{$key}] retention_days cannot be negative.");
            }

            $keys[] = $key;
            $localPaths[] = $normalizedLocalPath;
            $remotePaths[] = $remotePath;
            $archivePaths[] = $archivePath;
        }

        foreach ($archivePaths as $archivePath) {
            $this->guardNoOverlap($remotePaths, $archivePath, 'media archive and remote paths');
        }
    }

    /** @param array<string, mixed> $source */
    private function requiredValue(array $source, string $field, int|string $index): string
    {
        $value = trim((string) ($source[$field] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("Media source [{$index}] requires {$field}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $source */
    private function remoteValue(array $source, string $field, int|string $index): string
    {
        $value = trim($this->requiredValue($source, $field, $index), '/');

        if ($value === '' || in_array('..', explode('/', $value), true)) {
            throw new InvalidArgumentException("Media source [{$index}] has an invalid {$field}.");
        }

        return $value;
    }

    /** @param array<int, string> $values */
    private function guardUnique(array $values, string $candidate, string $label): void
    {
        if (in_array($candidate, $values, true)) {
            throw new InvalidArgumentException("Duplicate {$label} [{$candidate}].");
        }
    }

    /** @param array<int, string> $paths */
    private function guardNoOverlap(array $paths, string $candidate, string $label): void
    {
        foreach ($paths as $path) {
            if ($path === $candidate || str_starts_with($path, $candidate.'/') || str_starts_with($candidate, $path.'/')) {
                throw new InvalidArgumentException("Overlapping {$label} [{$path}] and [{$candidate}].");
            }
        }
    }
}
