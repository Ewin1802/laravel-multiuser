@extends('layouts.app')

@section('title', 'Diskon')

@section('content')
    @php
        $isPaginated = $discounts instanceof \Illuminate\Contracts\Pagination\Paginator;
        $total = $isPaginated && method_exists($discounts, 'total') ? $discounts->total() : $discounts->count();
    @endphp

    <div class="crud-page">

        {{-- HEADER --}}
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Promo</span>
                <h1>Diskon</h1>
                <p>Atur potongan harga yang bisa dipilih saat transaksi.</p>
            </div>

            <a href="{{ route('discounts.create') }}" class="p-btn p-btn-primary">
                <i data-lucide="plus"></i>
                Tambah Diskon
            </a>
        </div>

        <div class="p-card">

            {{-- PENCARIAN INSTAN (tanpa reload) --}}
            <div class="p-toolbar">
                <label class="p-search">
                    <i data-lucide="search"></i>
                    <input type="search" placeholder="Cari nama diskon..." autocomplete="off"
                        aria-label="Cari diskon" data-table-filter="#discountTable">
                </label>

                <span class="p-count">
                    <strong data-filter-count>{{ $total }}</strong> diskon
                </span>
            </div>

            {{-- DAFTAR --}}
            <div class="p-table-wrap">
                <table class="p-table p-table-simple" id="discountTable">
                    <thead>
                        <tr>
                            <th>Diskon</th>
                            <th class="text-center">Potongan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($discounts as $discount)
                            <tr data-filter-row>
                                <td class="cell-main">
                                    <div class="p-item">
                                        <div class="p-thumb"><i data-lucide="badge-percent"></i></div>
                                        <div class="p-item-text">
                                            <div class="p-name">{{ $discount->name }}</div>
                                            <small class="p-desc">{{ $discount->description ?: '-' }}</small>
                                        </div>
                                    </div>
                                </td>

                                <td class="cell-stock text-center" data-label="Potongan">
                                    <span class="p-big">{{ rtrim(rtrim(number_format((float) $discount->value, 2, ',', '.'), '0'), ',') }}%</span>
                                </td>

                                <td class="cell-actions">
                                    <div class="p-actions">
                                        <a href="{{ route('discounts.edit', $discount->id) }}" class="p-icon-btn"
                                            title="Edit" aria-label="Edit {{ $discount->name }}">
                                            <i data-lucide="pencil"></i>
                                        </a>

                                        <form action="{{ route('discounts.destroy', $discount->id) }}" method="POST"
                                            data-confirm="Hapus diskon &quot;{{ $discount->name }}&quot;?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-icon-btn is-danger" title="Hapus"
                                                aria-label="Hapus {{ $discount->name }}">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="p-empty-row">
                                <td colspan="3">
                                    <div class="p-empty">
                                        <span class="p-empty-icon"><i data-lucide="badge-percent"></i></span>
                                        <h4>Belum ada diskon</h4>
                                        <p>Contoh: Diskon Bengkel Langganan 5%, Promo Ganti Oli 10%.</p>
                                        <a href="{{ route('discounts.create') }}" class="p-btn p-btn-primary">
                                            <i data-lucide="plus"></i> Tambah Diskon
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse

                        {{-- tampil saat pencarian tidak menemukan apa-apa --}}
                        <tr class="p-empty-row" data-filter-empty hidden>
                            <td colspan="3">
                                <div class="p-empty">
                                    <span class="p-empty-icon"><i data-lucide="search-x"></i></span>
                                    <h4>Diskon tidak ditemukan</h4>
                                    <p>Coba kata kunci lain.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($isPaginated && $discounts->hasPages())
                <div class="p-footer">
                    <span class="p-page-info">
                        Menampilkan <strong>{{ $discounts->firstItem() }}</strong>–<strong>{{ $discounts->lastItem() }}</strong>
                        dari <strong>{{ $total }}</strong>
                    </span>
                    {{ $discounts->withQueryString()->links('vendor.pagination.custom') }}
                </div>
            @endif
        </div>

    </div>
@endsection
