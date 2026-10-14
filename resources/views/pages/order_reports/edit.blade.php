@extends('layouts.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/order.css') }}">
@endpush

@section('title', 'Edit Transaksi')

@section('content')
    @php
        // satuan jual per produk untuk pilihan satuan di tiap baris
        $unitsByProduct = $products->mapWithKeys(fn ($p) => [
            $p->id => $p->units->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'conversion' => (float) $u->conversion,
                'price' => (int) $u->price,
            ])->values(),
        ]);

        $rp = fn ($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $qtyFmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $invoice = 'INV' . str_pad($order->id, 6, '0', STR_PAD_LEFT);

        // Kalau validasi gagal, tampilkan isian terakhir user — bukan data lama dari database
        $rows = old('items')
            ? collect(old('items'))->map(fn ($r) => (object) [
                'product_id' => $r['product_id'] ?? null,
                'product_unit_id' => $r['product_unit_id'] ?? null,
                'quantity' => $r['quantity'] ?? 1,
                'price' => $r['price'] ?? 0,
            ])->values()
            : $order->orderItems->map(fn ($i) => (object) [
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'quantity' => $i->quantity,
                'price' => $i->price,
            ])->values();

        $taxOn = (float) old('tax', $order->tax) > 0;
        $serviceOn = (float) old('service_charge', $order->service_charge) > 0;
    @endphp

    <div class="order-page order-edit">

        {{-- =========================================================
             HEADER
        ========================================================== --}}
        <div class="order-header">
            <div>
                <a href="{{ route('orders.index') }}" class="back-link">
                    <i data-lucide="arrow-left"></i> Kembali ke transaksi
                </a>
                <h1>Edit Transaksi</h1>
                <p><span class="invoice-number">{{ $invoice }}</span></p>
            </div>
        </div>

        <div class="notice notice-warning">
            <span class="notice-icon"><i data-lucide="triangle-alert"></i></span>
            <div>
                <strong>Perubahan otomatis mengoreksi stok</strong>
                <p>
                    Stok (dan batch expire) dari item lama dikembalikan, lalu item baru dipotong ulang
                    memakai tanggal transaksi. Koreksi yang membuat stok minus tetap disimpan dan ditandai.
                </p>
            </div>
        </div>

        @if ($errors->any())
            <div class="notice notice-danger" role="alert">
                <span class="notice-icon"><i data-lucide="circle-alert"></i></span>
                <div>
                    <strong>Periksa kembali isian berikut</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('orders.update', $order->id) }}" id="editOrderForm" novalidate>
            @csrf
            @method('PUT')

            <div class="edit-layout">

                {{-- ================= KOLOM KIRI ================= --}}
                <div class="edit-main">

                    {{-- INFO TRANSAKSI --}}
                    <section class="edit-card">
                        <div class="edit-card-head">
                            <span class="edit-card-icon"><i data-lucide="receipt-text"></i></span>
                            <div>
                                <h3>Info Transaksi</h3>
                                <p>Pelanggan, waktu, dan pembayaran.</p>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="field">
                                <label for="customer_name">Nama Pelanggan <small>opsional</small></label>
                                <input type="text" id="customer_name" name="customer_name"
                                    value="{{ old('customer_name', $order->customer_name) }}" placeholder="Umum"
                                    autocomplete="off">
                            </div>

                            <div class="field">
                                <label for="transaction_time">Waktu Transaksi</label>
                                <input type="datetime-local" id="transaction_time" name="transaction_time"
                                    value="{{ \Carbon\Carbon::parse(old('transaction_time', $order->transaction_time))->format('Y-m-d\TH:i') }}"
                                    required>
                            </div>

                            <div class="field">
                                <label for="table_number">No. Antrian <small>opsional</small></label>
                                <input type="number" id="table_number" name="table_number" inputmode="numeric"
                                    value="{{ old('table_number', $order->table_number) }}" min="0">
                            </div>

                            <div class="field field-full">
                                <span class="field-label">Metode Pembayaran</span>
                                <div class="segmented" role="radiogroup" aria-label="Metode pembayaran">
                                    @foreach (['Cash' => 'banknote', 'Transfer' => 'credit-card', 'Tempo' => 'hand-coins'] as $method => $icon)
                                        <label>
                                            <input type="radio" name="payment_method" value="{{ $method }}"
                                                @checked(old('payment_method', $order->payment_method) == $method)>
                                            <span><i data-lucide="{{ $icon }}"></i> {{ $method }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- RINCIAN PART --}}
                    <section class="edit-card order-items-card">
                        <div class="edit-card-head">
                            <span class="edit-card-icon"><i data-lucide="package"></i></span>
                            <div>
                                <h3>Rincian Part</h3>
                                <p>Qty boleh desimal (mis. 1.5 liter oli).</p>
                            </div>
                            <button type="button" class="btn-primary add-item-btn" id="addItemBtn">
                                <i data-lucide="plus"></i>
                                <span>Tambah</span>
                            </button>
                        </div>

                        <div class="item-list" id="itemsBody">
                            @foreach ($rows as $i => $row)
                            <div class="item-row">
                                <span class="item-no">{{ $loop->iteration }}</span>

                                <div class="item-field item-field-product">
                                    <label>Produk</label>
                                    <select name="items[{{ $i }}][product_id]" class="item-product" required>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-price="{{ (int) $product->price }}"
                                                data-unit="{{ $product->base_unit ?? 'PCS' }}"
                                                @selected($product->id == $row->product_id)>
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="item-field item-field-qty">
                                    <label>Qty</label>
                                    <select name="items[{{ $i }}][product_unit_id]" class="item-unit-select"
                                        data-selected="{{ $row->product_unit_id }}"></select>
                                    <input type="number" name="items[{{ $i }}][quantity]" class="item-qty"
                                        value="{{ $qtyFmt($row->quantity) }}" min="0.01" step="any"
                                        inputmode="decimal" required>
                                </div>

                                <div class="item-field item-field-price">
                                    <label>Harga satuan</label>
                                    <div class="money-input">
                                        <span>Rp</span>
                                        <input type="number" name="items[{{ $i }}][price]" class="item-price"
                                            value="{{ (int) $row->price }}" min="0" step="1" inputmode="numeric"
                                            required>
                                    </div>
                                </div>

                                <div class="item-field item-field-subtotal">
                                    <label>Subtotal</label>
                                    <strong class="item-subtotal">{{ $rp($row->quantity * $row->price) }}</strong>
                                </div>

                                <button type="button" class="remove-item-btn" title="Hapus item"
                                    aria-label="Hapus item">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                            @endforeach
                        </div>

                        <div class="items-footer">
                            <div class="items-count">
                                <span class="items-count-icon"><i data-lucide="layers-3"></i></span>
                                <div>
                                    <span>Total qty</span>
                                    <strong id="totalItemDisplay">{{ $qtyFmt($rows->sum('quantity')) }}</strong>
                                </div>
                            </div>
                            <div class="items-footer-note">
                                <i data-lucide="info"></i>
                                <span>Harga terisi otomatis saat produk dipilih, tetap bisa diubah.</span>
                            </div>
                        </div>
                    </section>

                </div>

                {{-- ================= KOLOM KANAN: RINGKASAN ================= --}}
                <aside class="edit-side">
                    <section class="edit-card summary-panel">
                        <div class="edit-card-head">
                            <span class="edit-card-icon"><i data-lucide="calculator"></i></span>
                            <div>
                                <h3>Ringkasan Biaya</h3>
                                <p>Dihitung otomatis.</p>
                            </div>
                        </div>

                        <div class="calc-row">
                            <span>Subtotal</span>
                            <strong id="subTotalDisplay">{{ $rp($order->sub_total) }}</strong>
                        </div>

                        <div class="calc-row calc-input">
                            <label for="discountAmount">Diskon</label>
                            <div class="money-input">
                                <span>Rp</span>
                                <input type="number" name="discount_amount" id="discountAmount" inputmode="numeric"
                                    value="{{ old('discount_amount', (int) $order->discount_amount) }}" min="0"
                                    step="1">
                            </div>
                        </div>

                        <div class="calc-row">
                            <label class="switch">
                                <input type="checkbox" id="taxEnabled" @checked($taxOn)>
                                <span class="switch-track"></span>
                                <span>Pajak <small>10%</small></span>
                            </label>
                            <strong id="taxDisplay">{{ $rp(old('tax', $order->tax)) }}</strong>
                            <input type="hidden" name="tax" id="taxAmount" value="{{ (int) old('tax', $order->tax) }}">
                        </div>

                        <div class="calc-row">
                            <label class="switch">
                                <input type="checkbox" id="serviceEnabled" @checked($serviceOn)>
                                <span class="switch-track"></span>
                                <span>Biaya Jasa <small>5%</small></span>
                            </label>
                            <strong id="serviceDisplay">{{ $rp(old('service_charge', $order->service_charge)) }}</strong>
                            <input type="hidden" name="service_charge" id="serviceChargeAmount"
                                value="{{ (int) old('service_charge', $order->service_charge) }}">
                        </div>

                        <div class="calc-total">
                            <span>Total</span>
                            <strong id="totalDisplay">{{ $rp($order->total) }}</strong>
                        </div>

                        <div class="calc-row calc-input">
                            <label for="paymentAmountInput">Dibayar</label>
                            <div class="money-input">
                                <span>Rp</span>
                                <input type="number" name="payment_amount" id="paymentAmountInput"
                                    inputmode="numeric"
                                    value="{{ old('payment_amount', (int) $order->payment_amount) }}" min="0"
                                    step="1">
                            </div>
                        </div>

                        <div class="calc-row calc-change" id="changeRow">
                            <span id="changeLabel">Kembalian</span>
                            <strong id="changeDisplay">Rp 0</strong>
                        </div>

                        <div class="edit-actions">
                            <a href="{{ route('orders.index') }}" class="btn-ghost">Batal</a>
                            <button type="submit" class="btn-primary" id="saveBtn">
                                <i data-lucide="save"></i>
                                Simpan
                            </button>
                        </div>
                    </section>
                </aside>

            </div>

            {{-- BAR BAWAH DI HP: total + simpan selalu terlihat --}}
            <div class="mobile-savebar">
                <div>
                    <small>Total</small>
                    <strong id="totalDisplayMobile">{{ $rp($order->total) }}</strong>
                </div>
                <button type="submit" class="btn-primary">
                    <i data-lucide="save"></i>
                    Simpan
                </button>
            </div>
        </form>

    </div>

    {{-- Template baris baru (tidak dirender sampai dipakai) --}}
    <template id="itemRowTemplate">
        <div class="item-row">
                                <span class="item-no"></span>

                                <div class="item-field item-field-product">
                                    <label>Produk</label>
                                    <select name="items[__INDEX__][product_id]" class="item-product" required>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-price="{{ (int) $product->price }}"
                                                data-unit="{{ $product->base_unit ?? 'PCS' }}"
                                                >
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="item-field item-field-qty">
                                    <label>Qty</label>
                                    <select name="items[__INDEX__][product_unit_id]" class="item-unit-select"
                                        data-selected=""></select>
                                    <input type="number" name="items[__INDEX__][quantity]" class="item-qty"
                                        value="1" min="0.01" step="any"
                                        inputmode="decimal" required>
                                </div>

                                <div class="item-field item-field-price">
                                    <label>Harga satuan</label>
                                    <div class="money-input">
                                        <span>Rp</span>
                                        <input type="number" name="items[__INDEX__][price]" class="item-price"
                                            value="0" min="0" step="1" inputmode="numeric"
                                            required>
                                    </div>
                                </div>

                                <div class="item-field item-field-subtotal">
                                    <label>Subtotal</label>
                                    <strong class="item-subtotal">Rp 0</strong>
                                </div>

                                <button type="button" class="remove-item-btn" title="Hapus item"
                                    aria-label="Hapus item">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
    </template>
@endsection

@push('scripts')
    <script>
        (() => {
            const $ = (id) => document.getElementById(id);
            const form = $('editOrderForm');
            const list = $('itemsBody');
            const TAX_RATE = 0.10;
            const SERVICE_RATE = 0.05;

            const rupiah = (v) => 'Rp ' + Math.round(Number(v) || 0).toLocaleString('id-ID');
            const num = (el) => parseFloat(el?.value) || 0;
            const fmtQty = (v) => String(Math.round(v * 1000) / 1000).replace('.', ',');

            // satuan jual per produk: { productId: [{id, name, conversion, price}] }
            const UNITS = {{ \Illuminate\Support\Js::from($unitsByProduct) }};

            const payment = $('paymentAmountInput');
            // kalau nominal dibayar tersimpan beda dengan total, jangan ditimpa otomatis
            let paymentTouched = @json(old('payment_amount') !== null)
                || num(payment) !== @json((int) $order->total);
            let dirty = false;

            /* ======================================================
               HITUNG ULANG (rumus sama persis dengan versi lama)
            ====================================================== */
            const recalc = () => {
                let subTotal = 0;
                let totalQty = 0;

                list.querySelectorAll('.item-row').forEach((row) => {
                    const qty = num(row.querySelector('.item-qty'));
                    const price = num(row.querySelector('.item-price'));
                    const line = qty * price;
                    row.querySelector('.item-subtotal').textContent = rupiah(line);
                    subTotal += line;
                    totalQty += qty;
                });

                const discount = num($('discountAmount'));
                const taxable = Math.max(0, subTotal - discount);
                const tax = $('taxEnabled').checked ? Math.round(taxable * TAX_RATE) : 0;
                const service = $('serviceEnabled').checked ? Math.round(subTotal * SERVICE_RATE) : 0;
                const total = taxable + tax + service;

                $('taxAmount').value = tax;
                $('serviceChargeAmount').value = service;

                $('subTotalDisplay').textContent = rupiah(subTotal);
                $('taxDisplay').textContent = rupiah(tax);
                $('serviceDisplay').textContent = rupiah(service);
                $('totalDisplay').textContent = rupiah(total);
                $('totalDisplayMobile').textContent = rupiah(total);
                $('totalItemDisplay').textContent = fmtQty(totalQty);

                $('taxDisplay').classList.toggle('is-off', !tax);
                $('serviceDisplay').classList.toggle('is-off', !service);

                if (!paymentTouched) payment.value = Math.max(0, Math.round(total));

                // kembalian / kurang bayar
                const diff = num(payment) - Math.round(total);
                $('changeLabel').textContent = diff < 0 ? 'Kurang bayar' : 'Kembalian';
                $('changeDisplay').textContent = rupiah(Math.abs(diff));
                $('changeRow').classList.toggle('is-minus', diff < 0);
            };

            const reindex = () => {
                list.querySelectorAll('.item-row').forEach((row, i) => {
                    row.querySelector('.item-product').name = `items[${i}][product_id]`;
                    row.querySelector('.item-qty').name = `items[${i}][quantity]`;
                    row.querySelector('.item-price').name = `items[${i}][price]`;
                    row.querySelector('.item-unit-select').name = `items[${i}][product_unit_id]`;
                    row.querySelector('.item-no').textContent = i + 1;
                });
            };

            // isi pilihan satuan sesuai produk di baris itu
            const syncUnit = (row, keepSelected = true) => {
                const opt = row.querySelector('.item-product').selectedOptions[0];
                const sel = row.querySelector('.item-unit-select');
                const units = UNITS[opt?.value] || [];
                const want = keepSelected ? (sel.value || sel.dataset.selected || '') : '';
                sel.innerHTML = units.length
                    ? units.map((u) => `<option value="${u.id}" data-price="${u.price}" data-conv="${u.conversion}">${u.name}${u.conversion > 1 ? ' (isi ' + u.conversion + ')' : ''}</option>`).join('')
                    : `<option value="">${opt?.dataset.unit || 'PCS'}</option>`;
                if (want && [...sel.options].some((o) => o.value === String(want))) sel.value = String(want);
            };

            /* ======================================================
               SATU LISTENER UNTUK SEMUA BARIS (event delegation)
            ====================================================== */
            list.addEventListener('input', (e) => {
                if (e.target.matches('.item-qty, .item-price')) {
                    dirty = true;
                    recalc();
                }
            });

            list.addEventListener('change', (e) => {
                if (!e.target.matches('.item-product')) return;
                const row = e.target.closest('.item-row');
                syncUnit(row, false);
                const unitOpt = row.querySelector('.item-unit-select').selectedOptions[0];
                row.querySelector('.item-price').value = unitOpt?.dataset.price || e.target.selectedOptions[0]?.dataset.price || 0;
                dirty = true;
                recalc();
            });

            // ganti satuan → harga ikut harga satuan itu
            list.addEventListener('change', (e) => {
                if (!e.target.matches('.item-unit-select')) return;
                const row = e.target.closest('.item-row');
                const unitOpt = e.target.selectedOptions[0];
                if (unitOpt?.dataset.price) row.querySelector('.item-price').value = unitOpt.dataset.price;
                dirty = true;
                recalc();
            });

            list.addEventListener('click', (e) => {
                const btn = e.target.closest('.remove-item-btn');
                if (!btn) return;

                if (list.querySelectorAll('.item-row').length <= 1) {
                    window.showToast?.('warning', 'Tidak bisa dihapus', 'Transaksi harus punya minimal 1 item.')
                        ?? alert('Transaksi harus punya minimal 1 item.');
                    return;
                }

                btn.closest('.item-row').remove();
                dirty = true;
                reindex();
                recalc();
            });

            $('addItemBtn').addEventListener('click', () => {
                const index = list.querySelectorAll('.item-row').length;
                const html = $('itemRowTemplate').innerHTML.replaceAll('__INDEX__', index);
                list.insertAdjacentHTML('beforeend', html);

                const row = list.lastElementChild;
                const select = row.querySelector('.item-product');
                row.querySelector('.item-price').value = select.selectedOptions[0]?.dataset.price || 0;
                syncUnit(row);
                reindex();
                window.lucide?.createIcons();
                dirty = true;
                recalc();
                select.focus();
            });

            ['discountAmount'].forEach((id) => $(id).addEventListener('input', () => { dirty = true; recalc(); }));
            ['taxEnabled', 'serviceEnabled'].forEach((id) => $(id).addEventListener('change', () => { dirty = true; recalc(); }));

            payment.addEventListener('input', () => {
                paymentTouched = true;
                dirty = true;
                recalc();
            });

            form.addEventListener('input', () => { dirty = true; });

            /* ======================================================
               SIMPAN: validasi ringan + cegah klik ganda
            ====================================================== */
            form.addEventListener('submit', (e) => {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    form.reportValidity();
                    return;
                }

                dirty = false;
                form.querySelectorAll('button[type="submit"]').forEach((b) => {
                    b.disabled = true;
                    b.classList.add('is-loading');
                });
                window.showLoading?.();
            });

            // peringatan kalau keluar halaman sebelum menyimpan
            window.addEventListener('beforeunload', (e) => {
                if (!dirty) return;
                e.preventDefault();
                e.returnValue = '';
            });

            list.querySelectorAll('.item-row').forEach((r) => syncUnit(r));
            reindex();
            recalc();
        })();
    </script>
@endpush
