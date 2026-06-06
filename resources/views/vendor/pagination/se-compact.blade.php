@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="se-pagination se-pagination--compact">
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
            @if ($paginator->total() > 0)
                <p class="text-[11px] leading-tight text-neutral-600 tabular-nums">
                    <span class="font-semibold text-neutral-800">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                    <span class="text-neutral-400"> de </span>
                    <span class="font-semibold text-neutral-800">{{ $paginator->total() }}</span>
                    <span class="mx-1 text-neutral-300" aria-hidden="true">·</span>
                    <span class="font-medium text-primary-700">pág. {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}</span>
                </p>
            @endif

            <div class="flex items-center gap-0.5" aria-label="Controles de página">
                @if ($paginator->onFirstPage())
                    <span class="se-pagination-btn se-pagination-btn-disabled se-pagination-btn--icon" aria-disabled="true" title="Anterior">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        <span class="sr-only">{{ __('pagination.previous') }}</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       class="se-pagination-btn se-pagination-btn-nav se-pagination-btn--icon"
                       wire:click.prevent="previousPage('{{ $paginator->getPageName() }}')"
                       wire:loading.attr="disabled"
                       title="Anterior">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        <span class="sr-only">{{ __('pagination.previous') }}</span>
                    </a>
                @endif

                <div class="hidden items-center gap-px px-0.5 md:flex" aria-label="Números de página">
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

                <span class="se-pagination-page se-pagination-page-active min-w-[1.75rem] px-2 md:hidden tabular-nums"
                      aria-current="page">{{ $paginator->currentPage() }}</span>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       class="se-pagination-btn se-pagination-btn-nav se-pagination-btn--icon"
                       wire:click.prevent="nextPage('{{ $paginator->getPageName() }}')"
                       wire:loading.attr="disabled"
                       title="Siguiente">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="sr-only">{{ __('pagination.next') }}</span>
                    </a>
                @else
                    <span class="se-pagination-btn se-pagination-btn-disabled se-pagination-btn--icon" aria-disabled="true" title="Siguiente">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="sr-only">{{ __('pagination.next') }}</span>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
