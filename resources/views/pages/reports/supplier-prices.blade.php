@extends('layouts.app')

@section('title', 'Supplier Termurah')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $qty = fn($n) => rtrim(rtrim(number_format((float) ($n ?? 0), 2, ',', '.'), '0'), ',');
        $date = fn($d) => \Carbon\Carbon::parse($d)->translatedFormat('d M Y');

        $url = fn(array $change) => route('reports.supplier-prices', array_filter(
            array_merge(['period' => $period, 'q' => $q, 'category_id' => $category_id, 'multi' => $onlyMulti ? 1 : null, 'sort' => $sort], $change),
            fn($v) => $v !== null && $v !== ''
        ));

        $topSupplier = $stats->ranking->keys()->first();
        $topCount = $stats->ranking->first() ?? 0;
        $isFiltered = $q !== '' || filled($category_id) || $onlyMulti;
    @endphp

    <div class="crud-page">

        <div class="p-header">
            <div>
                <span class="p-eyebrow">Laporan</span>
                <h1>Supplier Termurah</h1>
                <p>Perbandingan harga beli terakhir tiap supplier, dihitung dari riwayat Stok Masuk.</p>
            </div>

            <a href="{{ route('stock-ins.create') }}" class="p-btn p-btn-primary">
                <i data-lucide="plus"></i>
                Catat Stok Masuk
            </a>
        </div>

        {{-- FILTER --}}
        <section class="p-card p-filter-card" aria-label="Filter laporan">
            <form method="GET" action="{{ route('reports.supplier-prices') }}" class="p-filter">
                <input type="hidden" name="period" value="{{ $period }}">

                <div class="p-field">
                    <label for="q">Produk</label>
                    <input type="search" id="q" name="q" value="{{ $q }}" placeholder="Cari nama produk...">
                </div>

                <div class="p-field">
                    <label for="category_id">Kategori</label>
                    <select id="category_id" name="category_id">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($category_id == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="p-field">
                    <label for="sort">Urutkan</label>
                    <select id="sort" name="sort">
                        <option value="saving" @selected($sort === 'saving')>Selisih harga terbesar</option>
                        <option value="overpaid" @selected($sort === 'overpaid')>Terakhir beli kemahalan</option>
                        <option value="name" @selected($sort === 'name')>Nama produk</option>
                    </select>
                </div>

                <div class="p-filter-actions">
                    <button type="submit" class="p-btn p-btn-dark">
                        <i data-lucide="filter"></i>
                        Terapkan
                    </button>
                    <a href="{{ route('reports.supplier-prices') }}" class="p-icon-btn p-filter-reset" title="Reset filter"
                        aria-label="Reset filter">
                        <i data-lucide="rotate-ccw"></i>
                    </a>
                </div>
            </form>

            <nav class="p-chips" aria-label="Periode riwayat harga">
                @foreach ($periods as $days => $label)
                    <a href="{{ $url(['period' => $days, 'page' => null]) }}"
                        class="p-chip {{ $period === $days ? 'is-active' : '' }}"
                        @if ($period === $days) aria-current="true" @endif>{{ $label }}</a>
                @endforeach

                <a href="{{ $url(['multi' => $onlyMulti ? null : 1, 'page' => null]) }}"
                    class="p-chip p-chip-toggle {{ $onlyMulti ? 'is-active' : '' }}"
                    @if ($onlyMulti) aria-current="true" @endif>
                    Hanya yang punya ≥ 2 supplier
                </a>
            </nav>
        </section>

        {{-- RINGKASAN --}}
        <section class="p-stats is-3" aria-label="Ringkasan">
            <div class="p-stat tone-orange">
                <span class="p-stat-icon"><i data-lucide="trophy"></i></span>
                <span class="p-stat-label">Paling sering termurah</span>
                <strong class="p-stat-value is-text">{{ $topSupplier ?? '—' }}</strong>
                <span class="p-stat-sub">
                    {{ $topSupplier ? "Termurah di {$topCount} dari {$stats->multi} produk yang bisa dibandingkan" : 'Butuh produk dengan ≥ 2 supplier' }}
                </span>
            </div>

            <div class="p-stat tone-blue">
                <span class="p-stat-icon"><i data-lucide="scale"></i></span>
                <span class="p-stat-label">Produk bisa dibandingkan</span>
                <strong class="p-stat-value">{{ $stats->multi }} <small>/ {{ $stats->products }}</small></strong>
                <span class="p-stat-sub">Punya harga dari 2 supplier atau lebih</span>
            </div>

            <a href="{{ $url(['sort' => 'overpaid', 'page' => null]) }}" class="p-stat tone-red">
                <span class="p-stat-icon"><i data-lucide="trending-up"></i></span>
                <span class="p-stat-label">Terakhir beli bukan dari termurah</span>
                <strong class="p-stat-value">{{ $stats->overpaid_count }} <small>produk</small></strong>
                <span class="p-stat-sub">
                    {{ $stats->overpaid_count ? 'Selisih total ' . $rp($stats->overpaid_sum) . ' per satuan' : 'Semua sudah dari supplier termurah' }}
                </span>
            </a>
        </section>

        @if ($stats->ranking->count() > 1)
            <section class="p-card p-section p-rank" aria-label="Peringkat supplier">
                <div class="p-section-head">
                    <span class="p-section-icon"><i data-lucide="list-ordered"></i></span>
                    <div>
                        <h3>Peringkat Supplier</h3>
                        <p>Jumlah produk di mana supplier ini memberi harga terakhir paling murah.</p>
                    </div>
                </div>

                <ol class="p-rank-list">
                    @foreach ($stats->ranking->take(6) as $name => $wins)
                        <li>
                            <span class="p-rank-name">{{ $name }}</span>
                            <span class="p-stat-bar" aria-hidden="true">
                                <span style="width: {{ round($wins / max(1, $topCount) * 100) }}%"></span>
                            </span>
                            <strong>{{ $wins }} produk</strong>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- DAFTAR PRODUK --}}
        <div class="p-compare-list">
            @forelse ($items as $item)
                @php
                    $cheapest = $item->cheapest;
                    $multi = $item->suppliers->count() > 1;
                    $maxPrice = $item->suppliers->max('last_price') ?: 1;
                @endphp

                <details class="p-card p-compare" @if ($loop->first && $multi) open @endif>
                    <summary>
                        <div class="p-item">
                            <div class="p-thumb">
                                @if ($item->image)
                                    <img src="{{ asset($item->image) }}" alt="" width="52" height="52" loading="lazy" decoding="async">
                                @else
                                    <i data-lucide="package"></i>
                                @endif
                            </div>
                            <div class="p-item-text">
                                <div class="p-name">{{ $item->name }}</div>
                                <small class="p-desc">
                                    {{ $item->category_name }} · stok {{ $qty($item->stock) }} {{ $item->base_unit }}
                                    · jual {{ $rp($item->price) }}
                                </small>
                            </div>
                        </div>

                        <div class="p-compare-best">
                            <small>Termurah</small>
                            <strong>{{ $rp($cheapest->last_price) }}</strong>
                            <span class="p-compare-supplier"><i data-lucide="badge-check"></i> {{ $cheapest->supplier_name }}</span>
                        </div>

                        <div class="p-compare-meta">
                            @if (! $multi)
                                <span class="p-status is-off">1 supplier</span>
                            @elseif ($item->overpaid > 0)
                                <span class="p-stock is-low" title="Pembelian terakhir dari {{ $item->latest->supplier_name }}">
                                    Terakhir +{{ $rp($item->overpaid) }}
                                </span>
                            @else
                                <span class="p-status is-on">Sudah termurah</span>
                            @endif

                            @if ($multi && $item->spread > 0)
                                <small>{{ $item->suppliers->count() }} supplier · selisih {{ $rp($item->spread) }}</small>
                            @elseif ($multi)
                                <small>{{ $item->suppliers->count() }} supplier · harga sama</small>
                            @endif
                        </div>

                        <i data-lucide="chevron-down" class="p-compare-chevron"></i>
                    </summary>

                    <div class="p-compare-body">
                        @if ($item->overpaid > 0)
                            <p class="p-send-note is-send">
                                Pembelian terakhir ({{ $date($item->latest->date) }}) dari <b>{{ $item->latest->supplier_name }}</b>
                                seharga {{ $rp($item->latest->price) }} —
                                {{ $rp($item->overpaid) }} lebih mahal per {{ $item->base_unit }} dibanding {{ $cheapest->supplier_name }}.
                            </p>
                        @endif

                        <div class="p-table-wrap">
                            <table class="p-table p-table-compare">
                                <thead>
                                    <tr>
                                        <th>Supplier</th>
                                        <th class="text-end">Harga terakhir</th>
                                        <th class="text-end">Selisih</th>
                                        <th class="text-end cell-hide-sm">Rata-rata</th>
                                        <th class="cell-hide-sm">Terakhir beli</th>
                                        <th class="text-end cell-hide-sm">Total dibeli</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($item->suppliers as $s)
                                        @php
                                            $diff = $s->last_price - $cheapest->last_price;
                                            $pct = $cheapest->last_price > 0 ? $diff / $cheapest->last_price * 100 : 0;
                                            $isBest = $loop->first;
                                        @endphp
                                        <tr class="{{ $isBest ? 'is-best' : '' }}">
                                            <td class="cell-main">
                                                <div class="p-name">
                                                    {{ $s->supplier_name }}
                                                    @if ($isBest)
                                                        <span class="p-tag-best"><i data-lucide="badge-check"></i> Termurah</span>
                                                    @endif
                                                    @if ($s->supplier_id === $item->latest->supplier_id)
                                                        <span class="p-you">beli terakhir</span>
                                                    @endif
                                                </div>
                                                <span class="p-stat-bar {{ $isBest ? 'tone-green' : 'tone-gray' }}" aria-hidden="true">
                                                    <span style="width: {{ round($s->last_price / $maxPrice * 100) }}%"></span>
                                                </span>
                                                <small class="p-sub p-show-sm">
                                                    {{ $date($s->last_date) }} · {{ $s->times }}× beli · rata-rata {{ $rp($s->avg_price) }}
                                                </small>
                                            </td>
                                            <td class="cell-price text-end"><span class="p-price">{{ $rp($s->last_price) }}</span></td>
                                            <td class="cell-actions text-end">
                                                @if ($diff > 0)
                                                    <span class="p-diff is-up">+{{ $rp($diff) }} <small>({{ number_format($pct, 1, ',', '.') }}%)</small></span>
                                                @else
                                                    <span class="p-diff is-zero">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end cell-hide-sm">{{ $rp($s->avg_price) }}</td>
                                            <td class="cell-hide-sm">{{ $date($s->last_date) }}</td>
                                            <td class="text-end cell-hide-sm">{{ $qty($s->total_qty) }} {{ $item->base_unit }} <small class="p-sub">{{ $s->times }}× beli</small></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="p-compare-foot">
                            <span>
                                Margin dengan harga termurah:
                                <b class="{{ $item->margin <= 0 ? 'is-minus' : '' }}">{{ $rp($item->margin) }}</b>
                                @if ($item->price > 0)
                                    ({{ round($item->margin / $item->price * 100) }}% dari harga jual)
                                @endif
                            </span>
                            <a href="{{ route('stock-ins.create', ['supplier_id' => $cheapest->supplier_id, 'product_id' => $item->id]) }}"
                                class="p-link">
                                Pesan lagi dari {{ $cheapest->supplier_name }} →
                            </a>
                        </div>
                    </div>
                </details>
            @empty
                <div class="p-card">
                    <div class="p-empty">
                        <span class="p-empty-icon"><i data-lucide="scale"></i></span>
                        <h4>{{ $isFiltered ? 'Tidak ada produk yang cocok' : 'Belum ada data harga' }}</h4>
                        <p>
                            {{ $isFiltered
                                ? 'Ubah filter atau perpanjang periodenya.'
                                : 'Laporan ini terisi otomatis setelah kamu mencatat Stok Masuk dari supplier.' }}
                        </p>
                        <div class="p-empty-actions">
                            @if ($isFiltered)
                                <a href="{{ route('reports.supplier-prices', ['period' => $period]) }}" class="p-btn p-btn-ghost">
                                    <i data-lucide="rotate-ccw"></i> Reset filter
                                </a>
                            @endif
                            <a href="{{ route('stock-ins.create') }}" class="p-btn p-btn-primary">
                                <i data-lucide="plus"></i> Catat Stok Masuk
                            </a>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($items->hasPages())
            <div class="p-card p-footer p-footer-solo">
                <span class="p-page-info">
                    Menampilkan <strong>{{ $items->firstItem() }}</strong>–<strong>{{ $items->lastItem() }}</strong>
                    dari <strong>{{ $items->total() }}</strong> produk
                </span>
                {{ $items->links('vendor.pagination.custom') }}
            </div>
        @endif

    </div>
@endsection
