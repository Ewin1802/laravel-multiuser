@php
    $isEdit = isset($stockIn);
    $si = $stockIn ?? null;

    // baris awal: isian lama (validasi gagal) → isi nota (edit) → produk dari ?product_id= (tambah)
    $initialItems = old('items');
    if ($initialItems === null) {
        if ($isEdit) {
            $initialItems = $si->items->map(fn($i) => [
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'quantity' => $i->quantity,
                'cost_price' => $i->cost_price,
                'expiry_date' => $i->expiry_date?->format('Y-m-d'),
                'batch_code' => $i->batch_code,
            ])->all();
        } elseif (request()->filled('product_id')) {
            $initialItems = [['product_id' => (int) request('product_id'), 'quantity' => '', 'cost_price' => '']];
        } else {
            $initialItems = [];
        }
    }

    $selectedSupplier = old('supplier_id', $si->supplier_id ?? request('supplier_id'));
    $receivedAt = old('received_at', $isEdit ? $si->received_at->format('Y-m-d') : now()->toDateString());
    $paymentStatus = old('payment_status', $si->payment_status ?? 'paid');
    $dueDate = old('due_date', $isEdit && $si->due_date ? $si->due_date->format('Y-m-d') : '');
@endphp

@if ($errors->any())
    <div class="p-notice is-danger" role="alert">
        <i data-lucide="circle-alert"></i>
        <div>
            <strong>Stok masuk belum tersimpan.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    </div>
@endif

@if ($suppliers->isEmpty())
    <div class="p-notice is-warning" role="status">
        <i data-lucide="truck"></i>
        <div>
            <strong>Belum ada supplier.</strong>
            <span>Tambahkan dulu di menu <a href="{{ route('suppliers.create') }}" class="p-link">Supplier</a>.</span>
        </div>
    </div>
@endif

<div class="p-form-layout">

    {{-- ===================== KOLOM UTAMA ===================== --}}
    <div class="p-form-main">

        {{-- NOTA --}}
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="file-text"></i></span>
                <div>
                    <h3>Nota Pembelian</h3>
                    <p>Dari siapa dan kapan barang diterima.</p>
                </div>
            </div>

            <div class="p-grid">
                <div class="p-field p-span-2">
                    <label for="supplier_id">Supplier <span class="req">*</span></label>
                    <select id="supplier_id" name="supplier_id" required
                        class="@error('supplier_id') is-invalid @enderror">
                        <option value="">Pilih supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected($selectedSupplier == $supplier->id)>
                                {{ $supplier->name }}{{ $supplier->is_active ? '' : ' (nonaktif)' }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field">
                    <label for="received_at">Tanggal Terima <span class="req">*</span></label>
                    <input type="date" id="received_at" name="received_at" required value="{{ $receivedAt }}"
                        max="{{ now()->toDateString() }}" class="@error('received_at') is-invalid @enderror">
                    @error('received_at')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field">
                    <label for="invoice_number">No. Nota / Faktur</label>
                    <input type="text" id="invoice_number" name="invoice_number" maxlength="100" autocomplete="off"
                        value="{{ old('invoice_number', $si->invoice_number ?? '') }}" placeholder="Opsional"
                        class="p-mono @error('invoice_number') is-invalid @enderror">
                    @error('invoice_number')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                {{-- PEMBAYARAN --}}
                <fieldset class="p-field p-fieldset p-span-2">
                    <legend class="p-field-label">Pembayaran <span class="req">*</span></legend>
                    <div class="p-segmented is-tones">
                        <label class="tone-green">
                            <input type="radio" name="payment_status" value="paid" required
                                @checked($paymentStatus === 'paid')>
                            <span><i data-lucide="circle-check"></i> Lunas</span>
                        </label>
                        <label class="tone-amber">
                            <input type="radio" name="payment_status" value="unpaid"
                                @checked($paymentStatus === 'unpaid')>
                            <span><i data-lucide="clock"></i> Tempo (bayar nanti)</span>
                        </label>
                    </div>
                    <small class="p-hint" id="paymentHint"></small>
                    @error('payment_status')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </fieldset>

                <div class="p-field p-span-2" id="dueDateField" @if ($paymentStatus !== 'unpaid') hidden @endif>
                    <label for="due_date">Jatuh Tempo</label>
                    <input type="date" id="due_date" name="due_date" value="{{ $dueDate }}" min="{{ $receivedAt }}"
                        class="@error('due_date') is-invalid @enderror">
                    <div class="p-chips p-chips-inline" aria-label="Isi cepat jatuh tempo">
                        @foreach ([7, 14, 30] as $days)
                            <button type="button" class="p-chip" data-due-days="{{ $days }}">{{ $days }} hari</button>
                        @endforeach
                    </div>
                    @error('due_date')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>
            </div>
        </section>

        {{-- BARANG --}}
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="package-plus"></i></span>
                <div>
                    <h3>Barang Masuk</h3>
                    <p>Cari produk, pilih satuan (mis. SLOP / BUNGKUS), lalu isi jumlah dan harga beli per satuan itu. Beli campuran (mis. 3 SLOP + 3 BUNGKUS)? Cari produknya sekali lagi → baris kedua otomatis memakai satuan lain. Produk ber-expire wajib diisi tanggal expire.
                        <button type="button" class="s-guide-link" data-guide-open="stockInGuide" data-guide-tab="satuan">Cara isi satuan</button>
                        ·
                        <button type="button" class="s-guide-link" data-guide-open="stockInGuide" data-guide-tab="expire">Cara isi expire</button></p>
                </div>
            </div>

            {{-- pencarian produk --}}
            <div class="p-picker">
                <label class="p-search">
                    <i data-lucide="search"></i>
                    <input type="search" id="productSearch" placeholder="Ketik nama produk, lalu Enter..."
                        autocomplete="off" role="combobox" aria-expanded="false" aria-controls="productResults"
                        aria-label="Cari produk untuk ditambahkan">
                </label>
                <ul class="p-picker-list" id="productResults" role="listbox" hidden></ul>
            </div>

            @error('items')
                <small class="p-error">{{ $message }}</small>
            @enderror

            <div class="p-lines" id="lines"></div>

            <div class="p-empty p-lines-empty" id="linesEmpty">
                <span class="p-empty-icon"><i data-lucide="package-search"></i></span>
                <h4>Belum ada barang</h4>
                <p>Cari produk di kotak di atas untuk menambahkannya ke nota ini.</p>
            </div>

            <p class="p-hint p-lines-note">
                Produk belum terdaftar?
                <a href="{{ route('products.create') }}" class="p-link" target="_blank" rel="noopener">Tambah produk baru</a>
                dulu, lalu muat ulang halaman ini.
            </p>
        </section>

        <section class="p-card p-section">
            <div class="p-field">
                <label for="notes">Catatan</label>
                <textarea id="notes" name="notes" rows="2" maxlength="1000"
                    placeholder="Opsional, mis. bayar tempo 14 hari, 1 dus penyok."
                    class="@error('notes') is-invalid @enderror">{{ old('notes', $si->notes ?? '') }}</textarea>
                @error('notes')
                    <small class="p-error">{{ $message }}</small>
                @enderror
            </div>
        </section>
    </div>

    {{-- ===================== KOLOM SAMPING ===================== --}}
    <aside class="p-form-side">
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="calculator"></i></span>
                <div>
                    <h3>Ringkasan</h3>
                    <p>Dihitung otomatis.</p>
                </div>
            </div>

            <dl class="p-sim" aria-live="polite">
                <div><dt>Jenis barang</dt><dd id="sumLines">0</dd></div>
                <div><dt>Total jumlah</dt><dd id="sumQty">0</dd></div>
                <div class="is-total"><dt>Total pembelian</dt><dd id="sumTotal">Rp 0</dd></div>
            </dl>

            @if ($isEdit)
                <p class="p-send-note is-off">Saat disimpan, stok lama dikembalikan dulu lalu dihitung ulang dari isi nota ini.</p>
            @else
                <p class="p-send-note is-on">Saat disimpan, stok tiap produk langsung bertambah.</p>
            @endif

            <div class="p-form-actions">
                <a href="{{ route('stock-ins.index') }}" class="p-btn p-btn-ghost">Batal</a>
                <button type="submit" class="p-btn p-btn-primary">
                    <i data-lucide="save"></i>
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Stok Masuk' }}
                </button>
            </div>
        </section>
    </aside>
</div>

<div class="p-savebar">
    <span class="p-savebar-total">
        <small>Total</small>
        <strong id="sumTotalMobile">Rp 0</strong>
    </span>
    <button type="submit" class="p-btn p-btn-primary">
        <i data-lucide="save"></i>
        Simpan
    </button>
</div>

@include('pages.stock-ins._guide')

@once
    @push('scripts')
        <script>
            (() => {
                const PRODUCTS = @json($products);
                const PRICES = @json($priceMap); // { productId: [{id, name, price}] } urut termurah dulu
                const INITIAL = @json(array_values($initialItems));
                const EDIT_URL = @json(route('products.edit', ['product' => '__ID__']));

                const $ = (id) => document.getElementById(id);
                const lines = $('lines');
                const search = $('productSearch');
                const results = $('productResults');
                const supplier = $('supplier_id');

                const byId = new Map(PRODUCTS.map((p) => [Number(p.id), p]));
                PRODUCTS.forEach((p) => { p._text = p.name.toLowerCase(); });

                const rp = (n) => 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
                const num = (n) => (Number(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
                const digits = (s) => String(s).replace(/\D/g, '').replace(/^0+(?=\d)/, '');
                const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                const supplierPrice = (pid) => (PRICES[pid] || []).find((s) => Number(s.id) === Number(supplier.value));

                /* ---------- baris barang ----------
                 * Satu baris = satu produk + satu satuan (+ satu tanggal expire).
                 * Harga beli diisi per satuan yang dipilih; riwayat harga supplier
                 * disimpan per satuan terkecil lalu dikalikan isi satuannya.
                 */
                let seq = 0;
                const unitsOf = (p) => (p.units && p.units.length)
                    ? p.units
                    : [{ id: '', name: p.base_unit || 'PCS', conversion: 1 }];
                const convOf = (row) => Number(row.querySelector('[data-unit]').selectedOptions[0]?.dataset.conv) || 1;

                const addLine = (pid, qty = '', cost = null, unitId = '', expiry = '', batchCode = '') => {
                    pid = Number(pid);
                    const p = byId.get(pid);
                    if (!p) return;

                    const units = unitsOf(p);

                    // Produk yang sama boleh beberapa baris bila SATUANNYA berbeda
                    // (mis. 3 SLOP + 3 BUNGKUS dalam satu nota) atau bila ber-expire
                    // (tanggal expire berbeda). Baris baru otomatis memakai satuan
                    // yang belum dipakai. Kalau semua satuan sudah ada → sorot baris lama.
                    const existingRows = [...lines.querySelectorAll(`[data-pid="${pid}"]`)];
                    if (existingRows.length && cost === null && !p.track_expiry) {
                        const used = new Set(existingRows.map((r) => r.querySelector('[data-unit]').value));
                        const free = units.find((u) => !used.has(String(u.id ?? '')));
                        if (!free) {
                            const last = existingRows[existingRows.length - 1];
                            last.querySelector('[data-qty]').focus();
                            last.classList.add('is-flash');
                            setTimeout(() => last.classList.remove('is-flash'), 700);
                            return;
                        }
                        unitId = free.id ?? '';
                    }

                    const key = seq++;
                    const selUnit = units.find((u) => String(u.id) === String(unitId)) || units[0];

                    const auto = cost === null || cost === '';
                    if (auto) {
                        const perBase = supplierPrice(pid)?.price ?? p.cost_price ?? '';
                        cost = perBase === '' ? '' : Number(perBase) * Number(selUnit.conversion || 1);
                    }
                    const raw = cost === '' || cost === null ? '' : String(Math.round(Number(cost)));

                    const base = esc(p.base_unit || 'PCS');
                    const unitOptions = units.map((u) => `<option value="${u.id ?? ''}" data-conv="${u.conversion}" ${u === selUnit ? 'selected' : ''}>${esc(u.name)}${Number(u.conversion) > 1 ? ` (isi ${num(u.conversion)} ${base})` : ''}</option>`).join('');

                    const row = document.createElement('div');
                    row.className = 'p-line';
                    row.dataset.line = key;
                    row.dataset.pid = pid;
                    row.innerHTML = `
                        <input type="hidden" name="items[${key}][product_id]" value="${pid}">
                        <div class="p-line-main">
                            <strong>${esc(p.name)}${p.track_expiry ? ' <span class="s-badge tone-amber">Expire</span>' : ''}</strong>
                            <small>Stok sekarang ${esc(p.stock_text || (num(p.stock) + ' ' + base))} · Harga jual ${rp(p.price)} / ${base}</small>
                            <small class="p-line-hint" data-hint></small>
                        </div>
                        <label class="p-line-field">
                            <span>Satuan</span>
                            <select name="items[${key}][product_unit_id]" data-unit>${unitOptions}</select>
                            ${units.length <= 1 ? `<small class="p-hint p-unit-missing">Hanya ${base}. Beli per SLOP / DUS?
                                <a href="${EDIT_URL.replace('__ID__', pid)}" target="_blank" rel="noopener" class="p-link">Tambah satuan di produk</a>,
                                lalu muat ulang halaman ini.</small>` : ''}
                        </label>
                        <label class="p-line-field">
                            <span>Jumlah</span>
                            <input type="number" name="items[${key}][quantity]" data-qty min="0.001" step="0.001"
                                inputmode="decimal" required value="${esc(qty)}" placeholder="0">
                            <small class="p-hint" data-base></small>
                        </label>
                        <label class="p-line-field">
                            <span>Harga beli / <em data-unit-name>${esc(selUnit.name)}</em></span>
                            <span class="p-affix">
                                <span class="is-prefix">Rp</span>
                                <input type="text" class="has-prefix" data-cost inputmode="numeric"
                                    autocomplete="off" required placeholder="0"
                                    value="${raw ? Number(raw).toLocaleString('id-ID') : ''}">
                            </span>
                            <input type="hidden" name="items[${key}][cost_price]" data-cost-raw value="${raw}">
                        </label>
                        ${p.track_expiry ? `
                        <label class="p-line-field">
                            <span>Tanggal expire <span class="req">*</span></span>
                            <input type="date" name="items[${key}][expiry_date]" required value="${esc(expiry || '')}">
                        </label>
                        <label class="p-line-field">
                            <span>No. batch</span>
                            <input type="text" name="items[${key}][batch_code]" maxlength="50" placeholder="Opsional" value="${esc(batchCode || '')}">
                        </label>` : ''}
                        <div class="p-line-total">
                            <span>Subtotal</span>
                            <strong data-sub>Rp 0</strong>
                        </div>
                        <button type="button" class="p-icon-btn is-danger" data-remove title="Hapus baris"
                            aria-label="Hapus ${esc(p.name)}">
                            <i data-lucide="x"></i>
                        </button>`;

                    row.dataset.auto = auto ? '1' : '0';
                    lines.appendChild(row);
                    window.refreshIcons?.();
                    updateRow(row);
                    return row;
                };

                const updateRow = (row) => {
                    const pid = Number(row.dataset.pid);
                    const p = byId.get(pid);
                    const conv = convOf(row);
                    const unitName = row.querySelector('[data-unit]').selectedOptions[0]?.textContent.split(' (')[0] || '';
                    const qty = Number(row.querySelector('[data-qty]').value) || 0;
                    const rawCost = row.querySelector('[data-cost-raw]').value;
                    const cost = Number(rawCost) || 0;
                    const perBase = conv > 0 ? cost / conv : cost;

                    row.querySelector('[data-unit-name]').textContent = unitName;
                    row.querySelector('[data-sub]').textContent = rp(qty * cost);
                    row.querySelector('[data-base]').textContent = conv > 1 && qty
                        ? `= ${num(qty * conv)} ${p.base_unit || 'PCS'}` : '';

                    // petunjuk harga: bandingkan per satuan terkecil dengan supplier lain
                    const hint = row.querySelector('[data-hint]');
                    const list = PRICES[pid] || [];
                    const cheapest = list[0];
                    const base = p.base_unit || 'PCS';
                    let cls = '';
                    let text = 'Belum ada riwayat harga beli.';

                    if (cheapest) {
                        const other = Number(cheapest.id) !== Number(supplier.value);
                        text = `Termurah: ${cheapest.name} · ${rp(cheapest.price * conv)} / ${unitName}`;

                        if (rawCost !== '' && other && perBase > cheapest.price + 0.0001) {
                            cls = 'is-warn';
                            text = `Lebih mahal ${rp((perBase - cheapest.price) * conv)} / ${unitName} dari ${cheapest.name}`;
                        } else if (rawCost !== '' && perBase <= cheapest.price + 0.0001) {
                            cls = 'is-ok';
                            text = perBase < cheapest.price - 0.0001
                                ? `Lebih murah ${rp((cheapest.price - perBase) * conv)} / ${unitName} dari harga termurah sebelumnya`
                                : 'Sama dengan harga termurah';
                        }
                    }

                    if (rawCost !== '' && perBase >= Number(p.price)) {
                        cls = 'is-warn';
                        text = `Modal ${rp(perBase)} / ${base} sama / di atas harga jual (${rp(p.price)} / ${base})`;
                    }

                    hint.className = 'p-line-hint ' + cls;
                    hint.textContent = text;
                };

                const updateTotals = () => {
                    const rows = [...lines.children];
                    let qty = 0;
                    let total = 0;

                    rows.forEach((row) => {
                        const q = Number(row.querySelector('[data-qty]').value) || 0;
                        const c = Number(row.querySelector('[data-cost-raw]').value) || 0;
                        qty += q;
                        total += q * c;
                    });

                    $('sumLines').textContent = rows.length;
                    $('sumQty').textContent = num(qty);
                    $('sumTotal').textContent = rp(total);
                    $('sumTotalMobile').textContent = rp(total);
                    $('linesEmpty').hidden = rows.length > 0;

                    // dipakai form guard (app.js): nota tanpa barang tidak bisa disimpan
                    search.setCustomValidity(rows.length ? '' : 'Tambahkan minimal satu barang.');
                };

                lines.addEventListener('input', (e) => {
                    const row = e.target.closest('.p-line');
                    if (!row) return;

                    if (e.target.matches('[data-cost]')) {
                        const input = e.target;
                        const fromEnd = input.value.length - input.selectionStart;
                        const raw = digits(input.value);
                        row.querySelector('[data-cost-raw]').value = raw;
                        input.value = raw ? Number(raw).toLocaleString('id-ID') : '';
                        const pos = Math.max(0, input.value.length - fromEnd);
                        input.setSelectionRange(pos, pos);
                        input.setCustomValidity('');
                        row.dataset.auto = '0';
                    }

                    updateRow(row);
                    updateTotals();
                });

                // ganti satuan → harga otomatis ikut isi satuan baru
                lines.addEventListener('change', (e) => {
                    if (!e.target.matches('[data-unit]')) return;
                    const row = e.target.closest('.p-line');
                    const pid = Number(row.dataset.pid);
                    const p = byId.get(pid);
                    const perBase = supplierPrice(pid)?.price ?? p.cost_price;
                    if (row.dataset.auto === '1' && perBase) {
                        const raw = String(Math.round(Number(perBase) * convOf(row)));
                        row.querySelector('[data-cost-raw]').value = raw;
                        row.querySelector('[data-cost]').value = Number(raw).toLocaleString('id-ID');
                    }
                    updateRow(row);
                    updateTotals();
                });

                lines.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-remove]');
                    if (!btn) return;
                    btn.closest('.p-line').remove();
                    updateTotals();
                    search.focus();
                });

                // ganti supplier → harga yang belum diubah manual ikut harga terakhir supplier itu
                supplier.addEventListener('change', () => {
                    [...lines.children].forEach((row) => {
                        const pid = Number(row.dataset.pid);
                        const mine = supplierPrice(pid);

                        if (row.dataset.auto === '1' && mine) {
                            const raw = String(Math.round(mine.price * convOf(row)));
                            row.querySelector('[data-cost-raw]').value = raw;
                            row.querySelector('[data-cost]').value = Number(raw).toLocaleString('id-ID');
                        }
                        updateRow(row);
                    });
                    updateTotals();
                });

                /* ---------- pencarian produk ---------- */
                let active = -1;
                let found = [];

                const closeResults = () => {
                    results.hidden = true;
                    search.setAttribute('aria-expanded', 'false');
                    active = -1;
                };

                const renderResults = () => {
                    const terms = search.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
                    if (!terms.length) return closeResults();

                    found = PRODUCTS.filter((p) => terms.every((t) => p._text.includes(t))).slice(0, 8);
                    active = found.length ? 0 : -1;

                    results.innerHTML = found.length
                        ? found.map((p, i) => {
                            const added = lines.querySelector(`[data-pid="${p.id}"]`);
                            const cheapest = (PRICES[p.id] || [])[0];
                            return `<li role="option" data-pick="${p.id}" class="${i === 0 ? 'is-active' : ''}" aria-selected="${i === 0}">
                                <span><strong>${esc(p.name)}</strong>
                                <small>Stok ${esc(p.stock_text || num(p.stock))}${cheapest ? ` · termurah ${rp(cheapest.price)} / ${esc(p.base_unit || 'PCS')} (${esc(cheapest.name)})` : ''}</small></span>
                                <em>${added && !p.track_expiry ? 'Sudah ada' : 'Tambah'}</em></li>`;
                        }).join('')
                        : '<li class="is-none">Produk tidak ditemukan.</li>';

                    results.hidden = false;
                    search.setAttribute('aria-expanded', 'true');
                };

                const pick = (pid) => {
                    const row = addLine(pid);
                    search.value = '';
                    closeResults();
                    updateTotals();
                    row?.querySelector('[data-qty]').focus();
                };

                search.addEventListener('input', renderResults);
                search.addEventListener('focus', renderResults);

                search.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault(); // jangan submit form
                        if (found[active]) pick(found[active].id);
                        return;
                    }
                    if (e.key === 'Escape') return closeResults();
                    if (results.hidden || !found.length) return;

                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + found.length) % found.length;
                        [...results.children].forEach((li, i) => {
                            li.classList.toggle('is-active', i === active);
                            li.setAttribute('aria-selected', i === active);
                        });
                    }
                });

                results.addEventListener('mousedown', (e) => {
                    const li = e.target.closest('[data-pick]');
                    if (!li) return;
                    e.preventDefault(); // fokus tidak lepas dari kotak cari
                    pick(li.dataset.pick);
                });

                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.p-picker')) closeResults();
                });

                /* ---------- pembayaran: lunas / tempo ---------- */
                const dueField = $('dueDateField');
                const due = $('due_date');
                const received = $('received_at');

                const addDays = (dateStr, days) => {
                    const d = new Date(dateStr + 'T00:00:00');
                    d.setDate(d.getDate() + days);
                    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
                };

                const syncPayment = () => {
                    const unpaid = document.querySelector('input[name="payment_status"]:checked')?.value === 'unpaid';
                    dueField.hidden = !unpaid;
                    due.min = received.value;

                    if (unpaid && !due.value && received.value) due.value = addDays(received.value, 14);
                    if (!unpaid) due.value = '';

                    $('paymentHint').textContent = unpaid
                        ? 'Dicatat sebagai hutang ke supplier. Uang baru dihitung keluar saat nota ditandai lunas.'
                        : 'Uang dihitung keluar pada tanggal terima.';
                };

                document.querySelectorAll('input[name="payment_status"]').forEach((r) => r.addEventListener('change', syncPayment));
                received.addEventListener('change', () => { due.min = received.value; });

                dueField.addEventListener('click', (e) => {
                    const chip = e.target.closest('[data-due-days]');
                    if (!chip || !received.value) return;
                    due.value = addDays(received.value, Number(chip.dataset.dueDays));
                    due.dispatchEvent(new Event('input', { bubbles: true }));
                });

                syncPayment();

                /* ---------- mulai ---------- */
                INITIAL.forEach((item) => addLine(item.product_id, item.quantity ?? '', item.cost_price ?? '',
                    item.product_unit_id ?? '', item.expiry_date ?? '', item.batch_code ?? ''));
                updateTotals();
            })();
        </script>
    @endpush
@endonce
