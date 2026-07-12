<?php

declare(strict_types=1);

namespace Aboleon\BackupManager\Http\Controllers;

use Aboleon\BackupManager\State\BackupRunRepository;
use Aboleon\BackupManager\State\BackupStateRepository;
use Illuminate\Contracts\View\View;

final class BackupDashboardController
{
    public function __invoke(BackupStateRepository $state, BackupRunRepository $runs): View
    {
        abort_unless($state->available(), 503, __('backup-manager::dashboard.tables_missing'));

        return view('backup-manager::dashboard', [
            'sources' => $state->all(),
            'runs' => $runs->paginate(max(1, (int) config('backup-manager.ui.per_page', 25))),
        ]);
    }
}
