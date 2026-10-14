{{--
    Pagination custom — cuma tombol halaman, tanpa teks "Showing X to Y of Z results"
    karena teks ringkasan sudah ditampilkan manual lewat .pagination-info di blade halaman.

    Cara pakai:
    {{ $orders->links('vendor.pagination.custom') }}
--}}

@if ($paginator->hasPages())
    <nav class="trx-pagination" aria-label="Navigasi halaman">

        <ul class="trx-pagination-list">

            {{-- PREVIOUS --}}
            @if ($paginator->onFirstPage())
                <li class="trx-page disabled" aria-disabled="true">
                    <span class="trx-page-link"><i data-lucide="chevron-left"></i></span>
                </li>
            @else
                <li class="trx-page">
                    <a href="{{ $paginator->previousPageUrl() }}" class="trx-page-link" rel="prev">
                        <i data-lucide="chevron-left"></i>
                    </a>
                </li>
            @endif

            {{-- PAGE NUMBERS --}}
            @foreach ($elements as $element)

                @if (is_string($element))
                    <li class="trx-page disabled"><span class="trx-page-link trx-page-dots">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="trx-page active" aria-current="page">
                                <span class="trx-page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="trx-page">
                                <a href="{{ $url }}" class="trx-page-link">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif

            @endforeach

            {{-- NEXT --}}
            @if ($paginator->hasMorePages())
                <li class="trx-page">
                    <a href="{{ $paginator->nextPageUrl() }}" class="trx-page-link" rel="next">
                        <i data-lucide="chevron-right"></i>
                    </a>
                </li>
            @else
                <li class="trx-page disabled" aria-disabled="true">
                    <span class="trx-page-link"><i data-lucide="chevron-right"></i></span>
                </li>
            @endif

        </ul>

    </nav>
@endif
