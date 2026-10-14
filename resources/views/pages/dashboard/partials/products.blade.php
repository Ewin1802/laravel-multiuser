@php
    $topProducts = $topProducts ?? collect();
    $lowStockProducts = $lowStockProducts ?? collect();
    $maxQty = max(1, (float) ($topProducts->max('total_qty') ?? 1));
@endphp

<div class="product-panel">

    {{-- ==========================
         PRODUK TERLARIS
    =========================== --}}
    <div class="panel-card">
        <div class="panel-header">
            <div>
                <h3>Produk Terlaris</h3>
                <small class="panel-note">Omzet &amp; untung (penjualan − modal), semua transaksi</small>
            </div>
            <span>Top {{ $topProducts->count() ?: 10 }}</span>
        </div>

        @forelse ($topProducts as $index => $product)
            <div class="product-item">
                <div class="product-left">
                    <span class="product-rank {{ $index < 3 ? 'is-top' : '' }}">{{ $index + 1 }}</span>
                    <div class="product-info">
                        <div class="product-name" title="{{ $product->name }}">{{ $product->name }}</div>
                        <small>{{ $qty($product->total_qty) }} terjual</small>
                        {{-- bar relatif terhadap produk terlaris #1 --}}
                        <span class="rank-bar"><span
                                style="width: {{ round(($product->total_qty / $maxQty) * 100) }}%"></span></span>
                    </div>
                </div>
                @php
                    $omzet = (float) ($product->omzet ?? 0);
                    $profit = $omzet - (float) ($product->modal ?? 0);
                    $margin = $omzet > 0 ? round(($profit / $omzet) * 100, 1) : 0;
                @endphp
                <div class="product-right">
                    <div>{{ $rp($omzet) }}</div>
                    @if (($product->no_cost ?? 0) > 0)
                        <small class="profit-tag is-unknown">harga beli belum ada</small>
                    @else
                        <small class="profit-tag {{ $profit < 0 ? 'is-loss' : '' }}">
                            {{ $profit < 0 ? 'Rugi' : 'Untung' }} {{ $rp(abs($profit)) }} ·
                            {{ number_format($margin, 1, ',', '.') }}%
                        </small>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-state">
                <i data-lucide="chart-bar"></i>
                Belum ada transaksi.
            </div>
        @endforelse
    </div>

    {{-- ==========================
         STOK MENIPIS
    =========================== --}}
    <div class="panel-card">
        <div class="panel-header">
            <h3>Stok Menipis</h3>
            <span class="is-warning">≤ 5</span>
        </div>

        @forelse ($lowStockProducts as $product)
            @php $stock = (float) $product->stock; @endphp
            <div class="stock-item">
                <div class="product-info">
                    <div class="product-name" title="{{ $product->name }}">{{ $product->name }}</div>
                    <small>{{ $product->category->name ?? '-' }}</small>
                </div>
                <span class="stock-badge {{ $stock <= 0 ? 'is-empty' : '' }}">
                    {{ $stock <= 0 ? 'Habis' : $qty($stock) . ' ' . ($product->base_unit ?? 'PCS') }}
                </span>
            </div>
        @empty
            <div class="empty-state is-ok">
                <i data-lucide="circle-check"></i>
                Semua stok masih aman.
            </div>
        @endforelse

        @if ($lowStockProducts->isNotEmpty() && Route::has('products.index'))
            <a href="{{ route('products.index') }}" class="panel-link">
                Kelola stok <i data-lucide="arrow-right"></i>
            </a>
        @endif
    </div>

</div>

@once
    @push('css')
        <style>
            .product-right {
                text-align: right;
            }

            .profit-tag {
                display: inline-block;
                margin-top: 4px;
                padding: 2px 8px;
                border-radius: 999px;
                font-size: 11.5px;
                font-weight: 700;
                white-space: nowrap;
                background: #e9f9ef;
                color: #15803d;
            }

            .profit-tag.is-loss {
                background: #fef2f2;
                color: #b91c1c;
            }

            .profit-tag.is-unknown {
                background: #f4f5f7;
                color: #6b7380;
                font-weight: 600;
            }

            .panel-note {
                display: block;
                margin-top: 2px;
                font-size: 12px;
                color: #6b7380;
                font-weight: 400;
            }
        </style>
    @endpush
@endonce
