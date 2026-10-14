@extends('layouts.app')

@section('title', 'Cetak Label Barcode')

@section('content')
    @php
        $rp = fn ($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $qtyFmt = fn ($n) => rtrim(rtrim(number_format((float) ($n ?? 0), 2, ',', '.'), '0'), ',');
    @endphp

    <div class="crud-page">

        <div class="p-header">
            <div>
                <span class="p-eyebrow">Katalog</span>
                <h1>Cetak Label</h1>
                <p>Pilih produk dan jumlah label, lalu cetak ke printer label atau kertas stiker.</p>
            </div>

            <a href="{{ route('products.index') }}" class="p-btn p-btn-ghost">
                <i data-lucide="arrow-left"></i>
                Kembali ke Produk
            </a>
        </div>

        <form method="GET" action="{{ route('products.labels') }}" id="labelForm">

            {{-- produk terpilih yang tidak tampil di hasil cari tetap ikut terkirim --}}
            @foreach ($hiddenSelected as $id => $qty)
                <input type="hidden" name="qty[{{ $id }}]" value="{{ $qty }}">
            @endforeach

            <div class="p-form-layout">

                {{-- ===================== DAFTAR PRODUK ===================== --}}
                <div class="p-form-main">
                    <div class="p-card">
                        <div class="p-toolbar">
                            <label class="p-search">
                                <i data-lucide="search"></i>
                                <input type="search" name="q" value="{{ $search }}"
                                    placeholder="Cari nama, kode part, atau scan barcode..." autocomplete="off"
                                    aria-label="Cari produk">
                            </label>

                            <button type="submit" class="p-btn p-btn-dark">Cari</button>

                            <span class="p-count"><strong>{{ $products->count() }}</strong> produk</span>
                        </div>

                        <div class="p-table-wrap">
                            <table class="p-table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Barcode</th>
                                        <th class="text-center">Stok</th>
                                        <th class="text-end">Jumlah Label</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($products as $product)
                                        @php $stock = (float) $product->stock; @endphp
                                        <tr>
                                            <td class="cell-main">
                                                <div class="p-item-text">
                                                    <div class="p-name">{{ $product->name }}</div>
                                                    <small class="p-desc">
                                                        {{ $product->category_name ?? '-' }}
                                                        @if ($product->code)
                                                            · <span class="lb-mono">{{ $product->code }}</span>
                                                        @endif
                                                        · {{ $rp($product->price) }}
                                                    </small>
                                                </div>
                                            </td>

                                            <td data-label="Barcode">
                                                <span class="lb-mono">{{ $product->barcode ?: 'dibuat saat cetak' }}</span>
                                            </td>

                                            <td class="text-center" data-label="Stok">
                                                {{ $qtyFmt($stock) }} {{ $product->base_unit ?? 'PCS' }}
                                            </td>

                                            <td class="text-end" data-label="Jumlah">
                                                <div class="lb-qty">
                                                    <button type="button" class="p-icon-btn" data-step="-1"
                                                        aria-label="Kurangi">
                                                        <i data-lucide="minus"></i>
                                                    </button>
                                                    <input type="number" name="qty[{{ $product->id }}]" min="0"
                                                        max="100" inputmode="numeric" data-qty
                                                        data-stock="{{ max(0, min(100, (int) ceil($stock))) }}"
                                                        value="{{ $selected[$product->id] ?? 0 }}"
                                                        aria-label="Jumlah label {{ $product->name }}">
                                                    <button type="button" class="p-icon-btn" data-step="1"
                                                        aria-label="Tambah">
                                                        <i data-lucide="plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="p-empty-row">
                                            <td colspan="4">
                                                <div class="p-empty">
                                                    <span class="p-empty-icon"><i data-lucide="package-search"></i></span>
                                                    <h4>Produk tidak ditemukan</h4>
                                                    <p>Coba kata kunci lain.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ===================== PENGATURAN CETAK ===================== --}}
                <aside class="p-form-side">
                    <section class="p-card p-section">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="printer"></i></span>
                            <div>
                                <h3>Pengaturan Cetak</h3>
                                <p>Label berisi nama, barcode, dan harga.</p>
                            </div>
                        </div>

                        <div class="p-field">
                            <label for="size">Ukuran Kertas</label>
                            <select name="size" id="size">
                                @foreach ($sizes as $value => $label)
                                    <option value="{{ $value }}" @selected($size === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="p-toggle-row">
                            <div>
                                <strong>Tampilkan harga</strong>
                                <small>Matikan kalau harga sering berubah.</small>
                            </div>
                            <label class="p-switch">
                                <input type="hidden" name="price" value="0">
                                <input type="checkbox" name="price" value="1" @checked($showPrice)>
                                <span class="p-switch-track"></span>
                                <span class="sr-only">Tampilkan harga</span>
                            </label>
                        </div>

                        <div class="lb-tools">
                            <button type="button" class="p-btn p-btn-ghost" id="fillStock">
                                <i data-lucide="boxes"></i> Isi sesuai stok
                            </button>
                            <button type="button" class="p-btn p-btn-ghost" id="clearAll">
                                <i data-lucide="eraser"></i> Kosongkan
                            </button>
                        </div>

                        <p class="lb-total">
                            Total <strong id="labelTotal">{{ $totalLabels }}</strong> label
                            @if ($hiddenSelected->count())
                                <small>(termasuk {{ $hiddenSelected->sum() }} dari produk di luar hasil cari)</small>
                            @endif
                        </p>

                        <div class="p-form-actions">
                            <button type="submit" class="p-btn p-btn-primary" id="printBtn"
                                formaction="{{ route('products.labels.print') }}" formtarget="_blank">
                                <i data-lucide="printer"></i>
                                Cetak Label
                            </button>
                        </div>

                        <small class="p-hint">Maksimal 500 label sekali cetak. Halaman cetak terbuka di tab baru.</small>
                    </section>
                </aside>
            </div>
        </form>
    </div>

    <style>
        .crud-page .lb-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; }
        .crud-page .lb-qty { display: inline-flex; align-items: center; gap: 6px; }
        .crud-page .lb-qty input { width: 64px; text-align: center; padding: 8px 6px; }
        .crud-page .lb-tools { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0 6px; }
        .crud-page .lb-total { margin: 10px 0 0; font-size: 14px; }
        .crud-page .lb-total small { display: block; color: var(--pm, #667080); font-size: 12px; }
    </style>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('labelForm');
            const inputs = () => [...form.querySelectorAll('[data-qty]')];
            const hiddenTotal = [...form.querySelectorAll('input[type=hidden][name^="qty["]')]
                .reduce((sum, el) => sum + (parseInt(el.value, 10) || 0), 0);

            const clamp = (n) => Math.max(0, Math.min(100, n || 0));
            const update = () => {
                const total = hiddenTotal + inputs().reduce((s, el) => s + clamp(parseInt(el.value, 10)), 0);
                document.getElementById('labelTotal').textContent = total;
                document.getElementById('printBtn').disabled = total === 0;
            };

            form.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-step]');
                if (!btn) return;
                const input = btn.parentElement.querySelector('[data-qty]');
                input.value = clamp(parseInt(input.value, 10) + Number(btn.dataset.step));
                update();
            });

            form.addEventListener('input', (e) => {
                if (e.target.matches('[data-qty]')) update();
            });

            // Enter dari scanner di kotak cari → cari, bukan cetak
            form.querySelector('input[name=q]').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    form.removeAttribute('target');
                    form.submit();
                }
            });

            document.getElementById('fillStock').addEventListener('click', () => {
                inputs().forEach((el) => { el.value = el.dataset.stock; });
                update();
            });

            document.getElementById('clearAll').addEventListener('click', () => {
                inputs().forEach((el) => { el.value = 0; });
                update();
            });

            update();
        })();
    </script>
@endpush
