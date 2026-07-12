<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Feature;

use Aboleon\BackupManager\MediaSourcePreparer;
use Aboleon\BackupManager\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MediaSourcePreparerTest extends TestCase
{
    public function test_it_merges_overlays_without_duplicating_paths_and_applies_exclusions(): void
    {
        $files = new Filesystem;
        $root = sys_get_temp_dir().'/media-source-'.Str::uuid();
        $base = $root.'/base';
        $overlay = $root.'/overlay';
        $temporary = $root.'/temporary';
        $files->ensureDirectoryExists($base.'/pages');
        $files->ensureDirectoryExists($overlay);
        file_put_contents($base.'/shared.jpg', 'canonical');
        file_put_contents($base.'/pages/excluded.jpg', 'excluded');
        file_put_contents($overlay.'/shared.jpg', 'fallback');
        file_put_contents($overlay.'/fallback.jpg', 'fallback-only');
        $this->app['config']->set('backup-manager.temporary_directory', $temporary);
        $preparer = new MediaSourcePreparer($files, $this->app['config']);

        try {
            $prepared = $preparer->prepare([
                'path' => $base,
                'overlays' => [$overlay],
                'exclude' => ['pages/**'],
            ]);

            $this->assertSame('canonical', file_get_contents($prepared->path.'/shared.jpg'));
            $this->assertSame('fallback-only', file_get_contents($prepared->path.'/fallback.jpg'));
            $this->assertFileDoesNotExist($prepared->path.'/pages/excluded.jpg');

            $preparer->cleanup($prepared);
            $this->assertDirectoryDoesNotExist($prepared->path);
        } finally {
            $files->deleteDirectory($root);
        }
    }

    public function test_it_preserves_relative_paths_when_sources_have_trailing_slashes(): void
    {
        $files = new Filesystem;
        $root = sys_get_temp_dir().'/media-source-'.Str::uuid();
        $base = $root.'/base';
        $overlay = $root.'/overlay';
        $temporary = $root.'/temporary';
        $files->ensureDirectoryExists($base);
        $files->ensureDirectoryExists($overlay);
        file_put_contents($base.'/photo.jpg', 'canonical');
        file_put_contents($overlay.'/fallback.jpg', 'fallback');
        $this->app['config']->set('backup-manager.temporary_directory', $temporary);
        $preparer = new MediaSourcePreparer($files, $this->app['config']);

        try {
            $prepared = $preparer->prepare([
                'path' => $base.'/',
                'overlays' => [$overlay.'/'],
            ]);

            $this->assertSame('canonical', file_get_contents($prepared->path.'/photo.jpg'));
            $this->assertSame('fallback', file_get_contents($prepared->path.'/fallback.jpg'));

            $preparer->cleanup($prepared);
        } finally {
            $files->deleteDirectory($root);
        }
    }
}
