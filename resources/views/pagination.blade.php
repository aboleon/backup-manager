@if ($paginator->hasPages())
    <nav aria-label="{{ __('backup-manager::dashboard.pagination.label') }}">
        <ul class="pagination">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $paginator->previousPageUrl() ?? '#' }}" rel="prev" aria-label="{{ __('backup-manager::dashboard.pagination.previous') }}">&lsaquo;</a>
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="page-item {{ $page === $paginator->currentPage() ? 'active' : '' }}">
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                <a class="page-link" href="{{ $paginator->nextPageUrl() ?? '#' }}" rel="next" aria-label="{{ __('backup-manager::dashboard.pagination.next') }}">&rsaquo;</a>
            </li>
        </ul>
    </nav>
@endif
