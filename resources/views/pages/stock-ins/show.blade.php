@extends('layouts.app')

@section('title', 'Detail Stok Masuk')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $qty = fn($n) => rtrim(rtrim(number_format((float) ($n ?? 0), 2, ',', '.'), '0'), ',');
    @endphp

    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('stock-ins.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke stok masuk
                </a>
                <h1>{{ $stockIn->invoice_number ?: 'Nota #' . $stockIn->id }}</h1>
                <p>{{ $stockIn->supplier->name ?? '-' }} · diterima {{ $stockIn->received_at->translatedFormat('l, d F Y') }}</p>
            </div>

            <div class="p-header-actions">
                <button type="button" class="s-guide-trigger" data-guide-open="stockInGuide">
                    <span class="s-guide-trigger-icon"><i data-lucide="sparkles"></i></span>
                    <span>Panduan</span>
                </button>
                <a href="{{ route('stock-ins.edit', $stockIn->id) }}" class="p-btn p-btn-ghost">
                    <i data-lucide="pencil"></i> Edit
                </a>
                <a href="{{ route('stock-ins.create', ['supplier_id' => $stockIn->supplier_id]) }}" class="p-btn p-btn-primary">
                    <i data-lucide="plus"></i> Nota Baru
                </a>
            </div>
        </div>

        <div class="p-form-layout">
            <div class="p-form-main">
                <div class="p-card">
                    <div class="p-table-wrap">
                        <table class="p-table p-table-simple">
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-end">Harga beli</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stockIn->items as $item)
                                    @php
                                        $product = $item->product;
                                        $unit = $item->unit_name ?: ($product->base_unit ?? 'PCS');
                                        $baseUnit = $product->base_unit ?? 'PCS';
                                        // margin dihitung per satuan terkecil (harga jual dasar − modal per satuan terkecil)
                                        $margin = $product ? (float) $product->price - (float) $item->cost_per_base : null;
                                    @endphp
                                    <tr>
                                        <td class="cell-main">
                                            <div class="p-item">
                                                <div class="p-thumb">
                                                    @if ($product?->image)
                                                        <img src="{{ asset($product->image) }}" alt="" width="52" height="52" loading="lazy">
                                                    @else
                                                        <i data-lucide="package"></i>
                                                    @endif
                                                </div>
                                                <div class="p-item-text">
                                                    <div class="p-name">{{ $product->name ?? 'Produk dihapus' }}</div>
                                                    @if ($product)
                                                        <small class="p-desc">
                                                            Harga jual {{ $rp($product->price) }}/{{ $baseUnit }}
                                                            · margin {{ $rp($margin) }}/{{ $baseUnit }}
                                                            @if ($product->price > 0)
                                                                ({{ round($margin / $product->price * 100) }}%)
                                                            @endif
                                                        </small>
                                                    @endif
                                                    @if ($item->expiry_date)
                                                        <small class="p-desc">Exp {{ $item->expiry_date->format('d/m/Y') }}@if ($item->batch_code) · Batch {{ $item->batch_code }}@endif</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="cell-stock text-center" data-label="Jumlah">
                                            <span class="p-stock is-mid">{{ $qty($item->quantity) }} {{ $unit }}</span>
                                            @if ((float) $item->unit_conversion > 1)
                                                <small class="p-sub">= {{ $qty($item->base_quantity) }} {{ $baseUnit }}</small>
                                            @endif
                                        </td>
                                        <td class="cell-hide-sm text-end">{{ $rp($item->cost_price) }}</td>
                                        <td class="cell-actions text-end">
                                            <span class="p-price">{{ $rp($item->quantity * $item->cost_price) }}</span>
                                            <small class="p-sub p-show-sm">@ {{ $rp($item->cost_price) }}</small>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <aside class="p-form-side">
                <section class="p-card p-section">
                    <dl class="p-sim p-sim-plain">
                        <div><dt>Supplier</dt><dd>{{ $stockIn->supplier->name ?? '-' }}</dd></div>
                        <div><dt>Tanggal terima</dt><dd>{{ $stockIn->received_at->translatedFormat('d M Y') }}</dd></div>
                        <div><dt>No. nota</dt><dd>{{ $stockIn->invoice_number ?: '-' }}</dd></div>
                        <div><dt>Dicatat oleh</dt><dd>{{ $stockIn->user->name ?? '-' }}</dd></div>
                        <div>
                            <dt>Pembayaran</dt>
                            <dd>
                                @if ($stockIn->isPaid())
                                    Lunas{{ $stockIn->paid_at ? ' · ' . $stockIn->paid_at->translatedFormat('d M Y') : '' }}
                                @else
                                    Tempo{{ $stockIn->due_date ? ' · jatuh tempo ' . $stockIn->due_date->translatedFormat('d M Y') : '' }}
                                @endif
                            </dd>
                        </div>
                        <div><dt>Jenis barang</dt><dd>{{ $stockIn->items->count() }}</dd></div>
                        <div class="is-total"><dt>Total pembelian</dt><dd>{{ $rp($stockIn->total) }}</dd></div>
                    </dl>

                    @if ($stockIn->notes)
                        <p class="p-send-note is-off">{{ $stockIn->notes }}</p>
                    @endif

                    @unless ($stockIn->isPaid())
                        <form method="POST" action="{{ route('stock-ins.pay', $stockIn->id) }}" class="p-pay-action"
                            data-confirm="Tandai nota ini sudah LUNAS hari ini?">
                            @csrf
                            <button type="submit" class="p-btn p-btn-primary">
                                <i data-lucide="circle-check"></i> Tandai Lunas
                            </button>
                        </form>
                    @endunless

                    <form method="POST" action="{{ route('stock-ins.destroy', $stockIn->id) }}" class="p-danger-zone"
                        data-confirm="Hapus stok masuk ini? Stok produk akan dikurangi kembali.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-link is-danger">
                            <i data-lucide="trash-2"></i> Hapus stok masuk ini
                        </button>
                    </form>
                </section>
            </aside>
        </div>

    </div>

    @include('pages.stock-ins._guide')
@endsection
