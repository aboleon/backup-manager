<?php

declare(strict_types=1);

namespace Aboleon\BackupManager;

use Aboleon\BackupManager\Data\PreparedMediaSource;
use FilesystemIterator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class MediaSourcePreparer
{
    public function __construct(
        private Filesystem $files,
        private Repository $config,
    ) {}

    /** @param array<string, mixed> $source */
    public function prepare(array $source): PreparedMediaSource
    {
        $path = (string) $source['path'];
        $overlays = array_values((array) ($source['overlays'] ?? []));
        $excludes = array_values((array) ($source['exclude'] ?? []));

        if ($overlays === [] && $excludes === []) {
            return new PreparedMediaSource($path);
        }

        $temporaryPath = rtrim(
            (string) $this->config->get('backup-manager.temporary_directory'),
            DIRECTORY_SEPARATOR,
        ).DIRECTORY_SEPARATOR.'media-union-'.Str::uuid();
        $this->files->ensureDirectoryExists($temporaryPath, 0700);
        $this->files->chmod($temporaryPath, 0700);

        try {
            foreach ([$path, ...$overlays] as $sourcePath) {
                $this->merge((string) $sourcePath, $temporaryPath, $excludes);
            }
        } catch (\Throwable $exception) {
            $this->files->deleteDirectory($temporaryPath);

            throw $exception;
        }

        return new PreparedMediaSource($temporaryPath, $temporaryPath);
    }

    public function cleanup(PreparedMediaSource $source): void
    {
        if ($source->temporaryPath) {
            $this->files->deleteDirectory($source->temporaryPath);
        }
    }

    /** @param array<int, string> $excludes */
    private function merge(string $sourcePath, string $targetPath, array $excludes): void
    {
        $sourcePath = rtrim($sourcePath, '/\\');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($sourcePath) + 1));
            if (Str::is($excludes, $relativePath)) {
                continue;
            }

            $target = $targetPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($target)) {
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($target), 0700);
            if (@link($file->getPathname(), $target)) {
                continue;
            }

            if (! $this->files->copy($file->getPathname(), $target)) {
                throw new RuntimeException("Could not prepare media file: {$relativePath}");
            }

            $this->files->chmod($target, 0600);
            touch($target, $file->getMTime());
        }
    }
}
