<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Dumpers;

use Aboleon\BackupManager\Contracts\DatabaseDumper;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Spatie\DbDumper\Databases\MySql;

final class MySqlDatabaseDumper implements DatabaseDumper
{
    public function __construct(
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly MySqlDumperFactory $factory,
    ) {}

    public function dump(string $connection, string $targetPath): void
    {
        if (! str_ends_with($targetPath, '.gz')) {
            throw new RuntimeException('MySQL dump target must use the .gz extension.');
        }

        $sqlPath = substr($targetPath, 0, -3);
        $excludedDataTables = $this->excludedDataTables();

        try {
            $dumper = $this->configuredDumper($connection);
            if ($excludedDataTables !== []) {
                $dumper->excludeTables($excludedDataTables);
            }
            $dumper->dumpToFile($sqlPath);
            $this->files->chmod($sqlPath, 0600);

            if ($excludedDataTables !== []) {
                $this->appendSchemas($connection, $sqlPath, $excludedDataTables);
            }

            $this->compress($sqlPath, $targetPath);
            $this->files->chmod($targetPath, 0600);
        } finally {
            $this->files->delete($sqlPath);
        }
    }

    private function configuredDumper(string $connection): MySql
    {
        /** @var array<string, mixed> $database */
        $database = $this->config->get("database.connections.{$connection}", []);

        if (($database['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException("Connection [{$connection}] is not a MySQL connection.");
        }

        $dumper = $this->factory->create();

        if (filled($database['url'] ?? null)) {
            $dumper->setDatabaseUrl((string) $database['url']);
        } else {
            $dumper
                ->setHost((string) ($database['host'] ?? '127.0.0.1'))
                ->setPort((int) ($database['port'] ?? 3306))
                ->setDbName((string) ($database['database'] ?? ''))
                ->setUserName((string) ($database['username'] ?? ''))
                ->setPassword((string) ($database['password'] ?? ''))
                ->setSocket((string) ($database['unix_socket'] ?? ''));
        }

        $dump = (array) $this->config->get('backup-manager.dumper.options', []);
        $timeout = (int) ($dump['timeout'] ?? 300);
        if ($timeout < 1) {
            throw new RuntimeException('MySQL dump timeout must be positive.');
        }

        $dumper->setTimeout($timeout);
        $dumper->useSingleTransaction();
        $dumper->useQuick();
        $dumper->skipLockTables();

        if (filled($dump['binary_path'] ?? null)) {
            $dumper->setDumpBinaryPath((string) $dump['binary_path']);
        }

        if ((bool) ($dump['skip_ssl'] ?? false)) {
            $dumper->setSkipSsl();
        }

        if ((bool) ($dump['disable_column_statistics'] ?? false)) {
            $dumper->doNotUseColumnStatistics();
        }

        return $dumper;
    }

    /** @param array<int, string> $tables */
    private function appendSchemas(string $connection, string $sqlPath, array $tables): void
    {
        $dumper = $this->configuredDumper($connection);
        $dumper->includeTables($tables);
        $dumper->doNotDumpData();
        $dumper->useAppendMode();
        $dumper->dumpToFile($sqlPath);
    }

    /** @return array<int, string> */
    private function excludedDataTables(): array
    {
        $tables = (array) $this->config->get('backup-manager.dumper.options.exclude_data_tables', []);

        foreach ($tables as $table) {
            if (! is_string($table) || trim($table) === '') {
                throw new RuntimeException('MySQL exclude_data_tables must contain non-empty table names.');
            }
        }

        return array_values($tables);
    }

    private function compress(string $sourcePath, string $targetPath): void
    {
        $source = fopen($sourcePath, 'rb');
        $target = gzopen($targetPath, 'wb9');

        if (! $source || ! $target) {
            $this->closeStreams($source, $target);
            throw new RuntimeException('Could not open the database dump for compression.');
        }

        try {
            while (! feof($source)) {
                $chunk = fread($source, 1024 * 1024);

                if ($chunk === false || gzwrite($target, $chunk) === false) {
                    throw new RuntimeException('Could not compress the database dump.');
                }
            }
        } finally {
            fclose($source);
            gzclose($target);
        }
    }

    /** @param resource|false $source
     * @param  resource|false  $target
     */
    private function closeStreams(mixed $source, mixed $target): void
    {
        if (is_resource($source)) {
            fclose($source);
        }

        if (is_resource($target)) {
            gzclose($target);
        }
    }
}
