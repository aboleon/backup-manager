<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Tests\Feature;

use Aboleon\BackupManager\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class BackupDashboardDisabledTest extends TestCase
{
    public function test_dashboard_route_is_not_registered_by_default(): void
    {
        $this->assertFalse(Route::has('backup-manager.index'));
    }
}
