@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="se-pagination">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-center text-sm text-neutral-600 sm:text-left">
                @if ($paginator->total() > 0)
                    Mostrando
                    <span class="font-semibold text-neutral-800">{{ $paginator->firstItem() }}</span>
                    a
                    <span class="font-semibold text-neutral-800">{{ $paginator->lastItem() }}</span>
                    de
                    <span class="font-semibold text-neutral-800">{{ $paginator->total() }}</span>
                    registros
                    <span class="hidden text-neutral-400 sm:inline">·</span>
                    <span class="mt-0.5 block font-medium text-primary-700 sm:mt-0 sm:inline">
                        Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
                    </span>
                @endif
            </p>

            <div class="flex flex-wrap items-center justify-center gap-1 sm:justify-end">
                @if ($paginator->onFirstPage())
                    <span class="se-pagination-btn se-pagination-btn-disabled" aria-disabled="true">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        {{ __('pagination.previous') }}
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       class="se-pagination-btn se-pagination-btn-nav"
                       wire:click.prevent="previousPage('{{ $paginator->getPageName() }}')"
                       wire:loading.attr="disabled">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        {{ __('pagination.previous') }}
                    </a>
                @endif

                <div class="hidden items-center gap-0.5 px-1 sm:flex" aria-label="Números de página">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="se-pagination-ellipsis">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span class="se-pagination-page se-pagination-page-active"
                                          aria-current="page">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}"
                                       class="se-pagination-page"
                                       wire:click.prevent="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                       wire:loading.attr="disabled"
                                       aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>

                <span class="se-pagination-page se-pagination-page-active px-3 sm:hidden" aria-current="page">
                    {{ $paginator->currentPage() }}
                </span>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       class="se-pagination-btn se-pagination-btn-nav"
                       wire:click.prevent="nextPage('{{ $paginator->getPageName() }}')"
                       wire:loading.attr="disabled">
                        {{ __('pagination.next') }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @else
                    <span class="se-pagination-btn se-pagination-btn-disabled" aria-disabled="true">
                        {{ __('pagination.next') }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif


