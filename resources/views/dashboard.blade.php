@extends(config('backup-manager.ui.layout', 'backup-manager::layout'))

@section('page_title', __('backup-manager::dashboard.title'))

@section('content')
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h1 class="h3 mb-1">{{ __('backup-manager::dashboard.title') }}</h1>
                <p class="text-body-secondary mb-0">{{ __('backup-manager::dashboard.subtitle') }}</p>
            </div>
            <span class="badge text-bg-secondary">{{ __('backup-manager::dashboard.read_only') }}</span>
        </div>

        <section class="mb-5" aria-labelledby="backup-sources-title">
            <h2 id="backup-sources-title" class="h5 mb-3">{{ __('backup-manager::dashboard.sources.title') }}</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('backup-manager::dashboard.sources.source') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.sources.type') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.sources.state') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.sources.last_change') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.sources.last_attempt') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.sources.last_success') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.sources.last_error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sources as $source)
                            <tr>
                                <th scope="row">{{ $source->key }}</th>
                                <td>{{ $source->type }}</td>
                                <td>
                                    <span class="badge {{ $source->needsBackup() ? 'text-bg-warning' : 'text-bg-success' }}">
                                        {{ $source->needsBackup() ? __('backup-manager::dashboard.states.dirty') : __('backup-manager::dashboard.states.clean') }}
                                    </span>
                                </td>
                                <td>{{ $source->last_changed_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td>{{ $source->last_attempted_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td>{{ $source->last_successful_backup_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td class="text-break">{{ $source->last_error ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-body-secondary py-4">{{ __('backup-manager::dashboard.sources.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="backup-runs-title">
            <h2 id="backup-runs-title" class="h5 mb-3">{{ __('backup-manager::dashboard.runs.title') }}</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('backup-manager::dashboard.runs.started') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.runs.source') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.runs.status') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.runs.destination') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.runs.artifact') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.runs.size') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.runs.completed') }}</th>
                            <th scope="col">{{ __('backup-manager::dashboard.runs.error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr>
                                <td class="text-nowrap">{{ $run->started_at->format('Y-m-d H:i:s') }}</td>
                                <th scope="row">{{ $run->source_key }}</th>
                                <td>
                                    <span class="badge {{ match ($run->status) {
                                        'successful' => 'text-bg-success',
                                        'failed' => 'text-bg-danger',
                                        default => 'text-bg-info',
                                    } }}">{{ __('backup-manager::dashboard.statuses.'.$run->status) }}</span>
                                </td>
                                <td>{{ $run->destination }}</td>
                                <td class="text-break">{{ $run->artifact_path ?? '—' }}</td>
                                <td class="text-nowrap">{{ $run->size === null ? '—' : Illuminate\Support\Number::fileSize($run->size) }}</td>
                                <td class="text-nowrap">{{ $run->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td class="text-break">{{ $run->error ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-body-secondary py-4">{{ __('backup-manager::dashboard.runs.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $runs->links() }}
        </section>
    </div>
@endsection
