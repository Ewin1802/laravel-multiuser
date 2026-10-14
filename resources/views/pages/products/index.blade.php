@extends('layouts.app')

@section('title', 'Produk')

@section('content')
    @php
        $rp = fn ($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $qty = fn ($n) => rtrim(rtrim(number_format((float) ($n ?? 0), 2, ',', '.'), '0'), ',');
        $search = request('name');
    @endphp

    <div class="crud-page">

        {{-- =========================================================
             HEADER
        ========================================================== --}}
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Katalog</span>
                <h1>Produk</h1>
                <p>Kelola produk, harga, dan stok toko.</p>
            </div>

            <div class="p-header-actions">
                @if (Route::has('products.labels'))
                    <a href="{{ route('products.labels') }}" class="p-btn p-btn-ghost">
                        <i data-lucide="barcode"></i>
                        Cetak Label
                    </a>
                @endif

                @if (Route::has('products.import'))
                    <a href="{{ route('products.import') }}" class="p-btn p-btn-ghost">
                        <i data-lucide="file-spreadsheet"></i>
                        Import Excel
                    </a>
                @endif

                <a href="{{ route('products.create') }}" class="p-btn p-btn-primary">
                    <i data-lucide="plus"></i>
                    Tambah Produk
                </a>
            </div>
        </div>

        <div class="p-card">

            {{-- PENCARIAN --}}
            <form method="GET" action="{{ route('products.index') }}" class="p-toolbar" role="search">
                <label class="p-search">
                    <i data-lucide="search"></i>
                    <input type="search" name="name" value="{{ $search }}"
                        placeholder="Cari nama, kode part, atau scan barcode..." autocomplete="off"
                        aria-label="Cari produk">
                </label>

                <button type="submit" class="p-btn p-btn-dark">Cari</button>

                @if ($search)
                    <a href="{{ route('products.index') }}" class="p-btn p-btn-ghost">Reset</a>
                @endif

                <span class="p-count">
                    <strong>{{ number_format($products->total(), 0, ',', '.') }}</strong> produk
                </span>
            </form>

            {{-- DAFTAR — tabel di layar lebar, kartu di layar sempit --}}
            <div class="p-table-wrap">
                <table class="p-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Stok</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $stock = (float) $product->stock;
                                $unit = $product->base_unit ?? 'PCS';
                                $stockClass = $stock <= 0 ? 'is-empty' : ($stock <= 5 ? 'is-low' : ($stock <= 10 ? 'is-mid' : 'is-ok'));
                            @endphp
                            <tr>
                                <td class="cell-main">
                                    <div class="p-item">
                                        <div class="p-thumb">
                                            @if ($product->image)
                                                <img src="{{ asset($product->image) }}" alt="{{ $product->name }}"
                                                    width="52" height="52" loading="lazy" decoding="async">
                                            @else
                                                <i data-lucide="package"></i>
                                            @endif
                                        </div>
                                        <div class="p-item-text">
                                            <div class="p-name">
                                                {{ $product->name }}
                                                @if ($product->is_favorite)
                                                    <span class="p-tag-best" title="Ditandai terlaris">
                                                        <i data-lucide="flame"></i> Terlaris
                                                    </span>
                                                @endif
                                            </div>
                                            @if ($product->code || $product->barcode)
                                                <small class="p-codes">
                                                    @if ($product->code)
                                                        <span title="Kode part">{{ $product->code }}</span>
                                                    @endif
                                                    @if ($product->barcode)
                                                        <span title="Barcode"><i data-lucide="barcode"></i>{{ $product->barcode }}</span>
                                                    @endif
                                                </small>
                                            @endif
                                            <small class="p-desc">{{ \Illuminate\Support\Str::limit($product->description, 70) }}</small>
                                        </div>
                                    </div>
                                </td>

                                <td class="cell-category" data-label="Kategori">
                                    <span class="p-category">{{ $product->category_name ?? '-' }}</span>
                                </td>

                                <td class="cell-price text-end" data-label="Harga">
                                    <strong class="p-price">{{ $rp($product->price) }}</strong>
                                </td>

                                <td class="cell-stock text-center" data-label="Stok">
                                    <span class="p-stock {{ $stockClass }}">
                                        {{ $stock == 0 ? 'Habis' : $product->stockBreakdown() }}
                                    </span>
                                </td>

                                <td class="cell-status text-center" data-label="Status">
                                    <span class="p-status {{ $product->status ? 'is-on' : 'is-off' }}">
                                        {{ $product->status ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>

                                <td class="cell-actions">
                                    <div class="p-actions">
                                        @if (Route::has('products.labels'))
                                            <a href="{{ route('products.labels', ['qty' => [$product->id => 1]]) }}"
                                                class="p-icon-btn" title="Cetak label"
                                                aria-label="Cetak label {{ $product->name }}">
                                                <i data-lucide="barcode"></i>
                                            </a>
                                        @endif

                                        <a href="{{ route('products.edit', $product->id) }}" class="p-icon-btn"
                                            title="Edit" aria-label="Edit {{ $product->name }}">
                                            <i data-lucide="pencil"></i>
                                        </a>

                                        <form action="{{ route('products.destroy', $product->id) }}" method="POST"
                                            data-confirm="Hapus produk &quot;{{ $product->name }}&quot;? Tindakan ini tidak bisa dibatalkan.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-icon-btn is-danger" title="Hapus"
                                                aria-label="Hapus {{ $product->name }}">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="p-empty-row">
                                <td colspan="6">
                                    <div class="p-empty">
                                        <span class="p-empty-icon"><i data-lucide="package-search"></i></span>
                                        @if ($search)
                                            <h4>Produk tidak ditemukan</h4>
                                            <p>Tidak ada produk dengan kata kunci "{{ $search }}".</p>
                                            <a href="{{ route('products.index') }}" class="p-btn p-btn-ghost">Tampilkan semua</a>
                                        @else
                                            <h4>Belum ada produk</h4>
                                            <p>Tambahkan produk pertama Anda.</p>
                                            <a href="{{ route('products.create') }}" class="p-btn p-btn-primary">
                                                <i data-lucide="plus"></i> Tambah Produk
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="p-footer">
                    <span class="p-page-info">
                        Menampilkan <strong>{{ $products->firstItem() }}</strong>–<strong>{{ $products->lastItem() }}</strong>
                        dari <strong>{{ number_format($products->total(), 0, ',', '.') }}</strong>
                    </span>
                    {{ $products->withQueryString()->links('vendor.pagination.custom') }}
                </div>
            @endif
        </div>

    </div>

    <style>
        .crud-page .p-codes { display: flex; flex-wrap: wrap; gap: 4px 10px; margin: 2px 0; color: var(--pm, #667080); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11.5px; }
        .crud-page .p-codes span { display: inline-flex; align-items: center; gap: 3px; }
        .crud-page .p-codes svg { width: 12px; height: 12px; }
    </style>
@endsection

