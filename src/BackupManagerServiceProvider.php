<?php

declare(strict_types=1);

namespace Aboleon\BackupManager;

use Aboleon\BackupManager\Console\BackupStatusCommand;
use Aboleon\BackupManager\Console\MarkDatabaseChangedCommand;
use Aboleon\BackupManager\Console\RunBackupsCommand;
use Aboleon\BackupManager\Contracts\BackupDestination;
use Aboleon\BackupManager\Contracts\DatabaseDumper;
use Aboleon\BackupManager\Contracts\ProcessRunner;
use Aboleon\BackupManager\Destinations\GoogleDriveConfig;
use Aboleon\BackupManager\Http\Controllers\BackupDashboardController;
use Aboleon\BackupManager\State\BackupRunRepository;
use Aboleon\BackupManager\State\BackupStateRepository;
use Aboleon\BackupManager\Support\SymfonyProcessRunner;
use Aboleon\BackupManager\Tracking\LaravelWriteTracker;
use Aboleon\BackupManager\Tracking\MutationTable;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class BackupManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/backup-manager.php', 'backup-manager');

        $this->app->singleton(ProcessRunner::class, SymfonyProcessRunner::class);
        $this->app->singleton(GoogleDriveConfig::class, fn (): GoogleDriveConfig => GoogleDriveConfig::fromArray(
            (array) config('backup-manager.destination.options', []),
        ));
        $this->app->singleton(BackupStateRepository::class, fn (Application $app): BackupStateRepository => new BackupStateRepository(
            $app->make('db'),
            $this->stateConnection(),
        ));
        $this->app->singleton(BackupRunRepository::class, fn (): BackupRunRepository => new BackupRunRepository(
            $this->stateConnection(),
        ));
        $this->app->singleton(LaravelWriteTracker::class, fn (Application $app): LaravelWriteTracker => new LaravelWriteTracker(
            $app->make('db'),
            $app->make(BackupStateRepository::class),
            $app->make(DatabaseSource::class),
            $app->make(MutationTable::class),
            array_values(array_filter((array) config('backup-manager.tracking.ignored_tables', []), 'is_string')),
        ));

        $this->bindConfiguredClass(DatabaseDumper::class, 'backup-manager.dumper.driver');
        $this->bindConfiguredClass(BackupDestination::class, 'backup-manager.destination.driver');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'backup-manager');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'backup-manager');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunBackupsCommand::class,
                MarkDatabaseChangedCommand::class,
                BackupStatusCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/backup-manager.php' => config_path('backup-manager.php'),
            ], 'backup-manager-config');
        }

        $this->registerWriteTracking();
        $this->registerUi();
        $this->registerSchedule();
    }

    private function registerUi(): void
    {
        if (! (bool) config('backup-manager.ui.enabled', false)) {
            return;
        }

        Route::middleware((array) config('backup-manager.ui.middleware', ['web', 'auth']))
            ->get((string) config('backup-manager.ui.path', 'backup-manager'), BackupDashboardController::class)
            ->name('backup-manager.index');
    }

    private function registerWriteTracking(): void
    {
        if (! (bool) config('backup-manager.tracking.enabled', true)) {
            return;
        }

        $tracker = $this->app->make(LaravelWriteTracker::class);
        $events = $this->app->make('events');
        $events->listen(QueryExecuted::class, function (QueryExecuted $query) use ($tracker): void {
            $tracker->record($query);
        });
        $events->listen(RequestHandled::class, function () use ($tracker): void {
            $tracker->flush();
        });
        $events->listen(JobProcessing::class, function () use ($tracker): void {
            $tracker->beginIgnoring();
        });
        $events->listen(JobProcessed::class, function () use ($tracker): void {
            $tracker->endIgnoring();
        });
        $events->listen(JobExceptionOccurred::class, function () use ($tracker): void {
            $tracker->endIgnoring();
        });
        $events->listen(CommandFinished::class, function (CommandFinished $event) use ($tracker): void {
            str_starts_with($event->command, 'backup-manager:')
                ? $tracker->forget()
                : $tracker->flush();
        });
    }

    private function registerSchedule(): void
    {
        if (! (bool) config('backup-manager.schedule.enabled', false)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $event = $schedule
                ->command('backup-manager:run')
                ->dailyAt((string) config('backup-manager.schedule.time', '23:30'))
                ->withoutOverlapping();

            $timezone = config('backup-manager.schedule.timezone');
            if (is_string($timezone) && $timezone !== '') {
                $event->timezone($timezone);
            }

            $environments = array_values(array_filter(
                (array) config('backup-manager.schedule.environments', []),
                'is_string',
            ));
            if ($environments !== []) {
                $event->environments($environments);
            }

            if ((bool) config('backup-manager.schedule.on_one_server', false)) {
                $event->onOneServer();
            }
        });
    }

    private function bindConfiguredClass(string $contract, string $configKey): void
    {
        $this->app->bind($contract, function (Application $app) use ($configKey, $contract): object {
            $implementation = config($configKey);

            if (! is_string($implementation) || ! class_exists($implementation) || ! is_a($implementation, $contract, true)) {
                throw new RuntimeException("Backup Manager implementation [{$configKey}] must implement {$contract}.");
            }

            return $app->make($implementation);
        });
    }

    private function stateConnection(): ?string
    {
        $connection = config('backup-manager.state_connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }
}
