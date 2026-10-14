@extends('layouts.app')

@section('title', 'Supplier')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $total = $suppliers->total();
    @endphp

    <div class="crud-page">

        <div class="p-header">
            <div>
                <span class="p-eyebrow">Stok & Pembelian</span>
                <h1>Supplier</h1>
                <p>Tempat membeli barang. Harga tiap supplier tercatat otomatis dari Stok Masuk.</p>
            </div>

            <a href="{{ route('suppliers.create') }}" class="p-btn p-btn-primary">
                <i data-lucide="plus"></i>
                Tambah Supplier
            </a>
        </div>

        <div class="p-card">
            <div class="p-toolbar">
                <label class="p-search">
                    <i data-lucide="search"></i>
                    <input type="search" placeholder="Cari nama, telepon, alamat..." autocomplete="off"
                        aria-label="Cari supplier" data-table-filter="#supplierTable">
                </label>

                <span class="p-count">
                    <strong data-filter-count>{{ $suppliers->count() }}</strong> dari {{ $total }} supplier
                </span>

                @if (Route::has('reports.supplier-prices'))
                    <a href="{{ route('reports.supplier-prices') }}" class="p-btn p-btn-ghost">
                        <i data-lucide="scale"></i>
                        Bandingkan Harga
                    </a>
                @endif
            </div>

            <div class="p-table-wrap">
                <table class="p-table p-table-supplier" id="supplierTable">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th class="cell-hide-sm">Terakhir kirim</th>
                            <th class="text-end">Total pembelian</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($suppliers as $supplier)
                            <tr data-filter-row>
                                <td class="cell-main">
                                    <div class="p-item">
                                        <div class="p-thumb has-tone tone-orange"><i data-lucide="truck"></i></div>
                                        <div class="p-item-text">
                                            <div class="p-name">{{ $supplier->name }}</div>
                                            <small class="p-desc">
                                                {{ collect([$supplier->phone, $supplier->address])->filter()->implode(' · ') ?: 'Belum ada kontak' }}
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td class="cell-status">
                                    <span class="p-status {{ $supplier->is_active ? 'is-on' : 'is-off' }}">
                                        {{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>

                                <td class="cell-hide-sm">
                                    @if ($supplier->last_received_at)
                                        <span class="p-date">
                                            <strong>{{ \Carbon\Carbon::parse($supplier->last_received_at)->translatedFormat('d M Y') }}</strong>
                                            <small>{{ $supplier->stock_ins_count }} nota</small>
                                        </span>
                                    @else
                                        <span class="p-sub">Belum pernah</span>
                                    @endif
                                </td>

                                <td class="cell-price text-end">
                                    <span class="p-price is-out">{{ $rp($supplier->purchases_total) }}</span>
                                </td>

                                <td class="cell-actions">
                                    <div class="p-actions">
                                        <a href="{{ route('stock-ins.index', ['supplier_id' => $supplier->id, 'start_date' => '2000-01-01']) }}"
                                            class="p-icon-btn" title="Riwayat stok masuk"
                                            aria-label="Riwayat stok masuk {{ $supplier->name }}">
                                            <i data-lucide="history"></i>
                                        </a>

                                        <a href="{{ route('suppliers.edit', $supplier->id) }}" class="p-icon-btn"
                                            title="Edit" aria-label="Edit {{ $supplier->name }}">
                                            <i data-lucide="pencil"></i>
                                        </a>

                                        <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST"
                                            data-confirm="Hapus supplier &quot;{{ $supplier->name }}&quot;?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-icon-btn is-danger" title="Hapus"
                                                aria-label="Hapus {{ $supplier->name }}">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="p-empty-row">
                                <td colspan="5">
                                    <div class="p-empty">
                                        <span class="p-empty-icon"><i data-lucide="truck"></i></span>
                                        <h4>Belum ada supplier</h4>
                                        <p>Tambahkan supplier dulu, lalu catat barang datang di menu Stok Masuk.</p>
                                        <a href="{{ route('suppliers.create') }}" class="p-btn p-btn-primary">
                                            <i data-lucide="plus"></i> Tambah Supplier
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse

                        <tr class="p-empty-row" data-filter-empty hidden>
                            <td colspan="5">
                                <div class="p-empty">
                                    <span class="p-empty-icon"><i data-lucide="search-x"></i></span>
                                    <h4>Supplier tidak ditemukan</h4>
                                    <p>Coba kata kunci lain.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="p-footer">
                    <span class="p-page-info">
                        Menampilkan <strong>{{ $suppliers->firstItem() }}</strong>–<strong>{{ $suppliers->lastItem() }}</strong>
                        dari <strong>{{ $total }}</strong>
                    </span>
                    {{ $suppliers->links('vendor.pagination.custom') }}
                </div>
            @endif
        </div>

    </div>
@endsection
