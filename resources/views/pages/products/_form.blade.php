@php
    $isEdit = isset($product);
    $p = $product ?? null;

    // Usulan nama satuan terkecil (boleh ketik sendiri).
    $unitSuggestions = [
        'PCS',
        'BATANG',
        'BUAH',
        'BIJI',
        'SACHET',
        'BUNGKUS',
        'BOTOL',
        'KALENG',
        'LEMBAR',
        'GRAM',
        'ML',
        'LITER',
        'METER',
        'KG',
        'SET',
        'PASANG',
        'ROL',
        'TABLET',
        'STRIP',
    ];
    // Usulan satuan kemasan (satuan jual lain)
    $packSuggestions = [
        'BUNGKUS',
        'SLOP',
        'PAK',
        'DUS',
        'KARTON',
        'BOX',
        'BAL',
        'RENCENG',
        'LUSIN',
        'KODI',
        'KARUNG',
        'SAK',
        'IKAT',
        'KRAT',
        'TRAY',
        'BOTOL',
        'KALENG',
        'JERIKEN',
        'GALON',
        'ROL',
        'RIM',
        'STRIP',
        'BLISTER',
    ];
    $currentUnit = old('base_unit', $p->base_unit ?? 'PCS');

    // Satuan jual lain (selain satuan terkecil)
    $extraUnits = old(
        'units',
        $p
            ? $p
                ->units()
                ->where('is_base', false)
                ->where('is_active', true)
                ->get()
                ->map(
                    fn($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'conversion' => (float) $u->conversion,
                        'price' => (int) $u->price,
                        'barcode' => $u->barcode,
                    ],
                )
                ->all()
            : [],
    );
    $trackExpiry = (bool) old('track_expiry', $p->track_expiry ?? false);
    $warnDays = old('expiry_warning_days', $p->expiry_warning_days ?? 30);
    $hasStock = $p && (float) $p->stock > 0;

    $stockValue = old('stock', $p ? rtrim(rtrim(number_format((float) $p->stock, 2, '.', ''), '0'), '.') : 0);
    $statusOn = (bool) old('status', $p->status ?? 1);
    $favoriteOn = (bool) old('is_favorite', $p->is_favorite ?? 0);
    $barcodeValue = old('barcode', $p->barcode ?? '');
    $isStoreCode = \App\Support\Barcode::isInternal($p->barcode ?? null);

    // modal (harga beli per satuan terkecil)
    $costValue = old(
        'cost_price',
        isset($p->cost_price) && $p->cost_price !== null ? (int) round((float) $p->cost_price) : '',
    );
    $lastStockIn = $p
        ? \App\Models\StockInItem::with('stockIn:id,received_at')->where('product_id', $p->id)->latest('id')->first()
        : null;

    // produk mirip dari server (validasi simpan gagal karena nama mirip)
    $similarFromServer = session('similar_products', []);
@endphp

@if ($errors->any())
    <div class="p-notice is-danger" role="alert">
        <i data-lucide="circle-alert"></i>
        <div>
            <strong>Periksa kembali isian yang ditandai.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    </div>
@endif

<div class="p-form-layout">

    {{-- ===================== KOLOM UTAMA ===================== --}}
    <div class="p-form-main">

        {{-- INFORMASI --}}
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="package"></i></span>
                <div>
                    <h3>Informasi Produk</h3>
                    <p>Nama, kategori, dan keterangan produk.</p>
                </div>
            </div>

            <div class="p-grid">
                <div class="p-field p-span-2">
                    <label for="name">Nama Produk <span class="req">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $p->name ?? '') }}"
                        placeholder="Contoh: Gula Pasir 1 kg" required maxlength="255"
                        class="@error('name') is-invalid @enderror" autocomplete="off"
                        data-similar-url="{{ route('products.similar') }}" data-similar-exclude="{{ $p->id ?? '' }}">
                    @error('name')
                        <small class="p-error">{{ $message }}</small>
                    @enderror

                    {{-- produk dengan nama mirip (cek otomatis saat mengetik) --}}
                    <div class="p-similar" id="similarBox" hidden aria-live="polite">
                        <div class="p-similar-head">
                            <span class="p-similar-icon"><i data-lucide="copy"></i></span>
                            <div>
                                <strong id="similarTitle">Produk mirip sudah ada</strong>
                                <small id="similarSub">Pastikan Anda tidak membuat produk dobel.</small>
                            </div>
                        </div>
                        <ul class="p-similar-list" id="similarList"></ul>
                        @unless ($isEdit)
                            <label class="p-similar-confirm" id="similarConfirm" hidden>
                                <input type="checkbox" name="confirm_duplicate" value="1" @checked(old('confirm_duplicate'))>
                                <span>Ini <b>produk berbeda</b>, tetap simpan sebagai produk baru.</span>
                            </label>
                        @endunless
                    </div>
                </div>

                <div class="p-field">
                    <label for="code">Kode Produk</label>
                    <input type="text" id="code" name="code" value="{{ old('code', $p->code ?? '') }}"
                        placeholder="Contoh: GLA-001" maxlength="50" autocomplete="off" data-scan-field
                        class="p-mono @error('code') is-invalid @enderror">
                    <small class="p-hint">Kode / SKU internal toko. Opsional.</small>
                    @error('code')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field">
                    <label for="barcode">Barcode</label>
                    <input type="text" id="barcode" name="barcode" value="{{ $barcodeValue }}"
                        placeholder="Scan barcode di kemasan" maxlength="64" autocomplete="off" data-scan-field
                        class="p-mono @error('barcode') is-invalid @enderror">
                    <small class="p-hint">
                        Klik kolom ini lalu scan. Kosongkan jika kemasan tidak berbarcode — kode toko dibuat otomatis.
                    </small>
                    @error('barcode')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field p-span-2">
                    <label for="category_id">Kategori <span class="req">*</span></label>
                    <select id="category_id" name="category_id" required
                        class="@error('category_id') is-invalid @enderror">
                        <option value="">Pilih kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $p->category_id ?? '') == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field p-span-2">
                    <label for="description">Deskripsi <span class="req">*</span></label>
                    <textarea id="description" name="description" rows="5" required class="@error('description') is-invalid @enderror"
                        placeholder="Merek, ukuran / isi, varian. Contoh: Gula pasir kristal putih, kemasan 1 kg.">{{ old('description', $p->description ?? '') }}</textarea>
                    <small class="p-hint">Tulis merek, ukuran, dan varian supaya mudah dicari.</small>
                    @error('description')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>
            </div>
        </section>

        {{-- HARGA & STOK --}}
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="tags"></i></span>
                <div>
                    <h3>Satuan Terkecil, Harga & Stok</h3>
                    <p>Stok selalu dihitung dalam satuan terkecil (mis. BATANG). Satuan lain (BUNGKUS, SLOP) diatur di
                        bawah.</p>
                </div>
            </div>

            <div class="p-grid">
                <div class="p-field">
                    <label for="price_display">Harga Jual per <span data-base-label>{{ $currentUnit }}</span> <span
                            class="req">*</span></label>
                    <div class="p-money">
                        <span>Rp</span>
                        <input type="text" id="price_display" inputmode="numeric" autocomplete="off"
                            data-money="#price" required
                            value="{{ old('price', isset($p->price) ? (int) $p->price : '') }}" placeholder="0"
                            class="@error('price') is-invalid @enderror" aria-describedby="price-help">
                    </div>
                    {{-- Yang dikirim ke server: angka mentah tanpa titik --}}
                    <input type="hidden" name="price" id="price"
                        value="{{ old('price', isset($p->price) ? (int) $p->price : '') }}">
                    @error('price')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field">
                    <label for="cost_display">Harga Beli (Modal) per <span
                            data-base-label>{{ $currentUnit }}</span></label>
                    <div class="p-money">
                        <span>Rp</span>
                        <input type="text" id="cost_display" inputmode="numeric" autocomplete="off"
                            data-money="#cost_price" value="{{ $costValue }}" placeholder="Opsional"
                            class="@error('cost_price') is-invalid @enderror">
                    </div>
                    <input type="hidden" name="cost_price" id="cost_price" value="{{ $costValue }}">
                    @if ($lastStockIn)
                        <small class="p-hint">Terakhir dari Stok Masuk
                            {{ $lastStockIn->stockIn?->received_at?->translatedFormat('d M Y') }}. Akan diperbarui
                            otomatis setiap ada Stok Masuk baru.</small>
                    @else
                        <small class="p-hint">Isi kalau sudah tahu. Nanti diperbarui otomatis dari Stok Masuk.</small>
                    @endif
                    @error('cost_price')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-span-2 p-margin" id="marginBox" aria-live="polite"></div>

                <div class="p-field">
                    <label for="stock">{{ $isEdit ? 'Stok' : 'Stok Awal' }} <span class="req">*</span></label>
                    <input type="number" id="stock" name="stock" value="{{ $stockValue }}"
                        @if (!$p || (float) $p->stock >= 0) min="0" @endif step="any" inputmode="decimal" required
                        class="@error('stock') is-invalid @enderror"
                        @if ($isEdit && $p->track_expiry) readonly @endif>
                    @if ($isEdit && $p->track_expiry)
                        <small class="p-hint">Produk ber-expire: stok berubah lewat <b>Stok Masuk</b> (batch baru)
                            dan <b>Barang Keluar</b>, tidak bisa diketik langsung.</small>
                    @elseif ($isEdit)
                        <small class="p-hint">Ubah hanya untuk koreksi hasil hitung fisik. Barang datang dari supplier
                            dicatat lewat <b>Stok Masuk</b>.</small>
                    @else
                        <small class="p-hint">Isi <b>0</b> untuk barang yang baru dibeli — catat lewat <b>Stok
                                Masuk</b> supaya stok tidak dobel. Isi angka hanya untuk barang lama yang sudah ada di
                            rak.</small>
                    @endif
                    @error('stock')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="p-field">
                    <label for="base_unit">Satuan Terkecil <span class="req">*</span></label>
                    <input type="text" id="base_unit" name="base_unit" required maxlength="20"
                        list="baseUnitList" value="{{ $currentUnit }}" autocomplete="off"
                        style="text-transform:uppercase" class="@error('base_unit') is-invalid @enderror">
                    <datalist id="baseUnitList">
                        @foreach ($unitSuggestions as $u)
                            <option value="{{ $u }}"></option>
                        @endforeach
                    </datalist>
                    <datalist id="packUnitList">
                        @foreach ($packSuggestions as $u)
                            <option value="{{ $u }}"></option>
                        @endforeach
                    </datalist>
                    <small class="p-hint">Satuan terkecil yang dijual, mis. BATANG untuk rokok.</small>
                    @error('base_unit')
                        <small class="p-error">{{ $message }}</small>
                    @enderror
                </div>
            </div>
        </section>

        {{-- SATUAN JUAL LAIN --}}
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="boxes"></i></span>
                <div>
                    <h3>Satuan Jual Lain</h3>
                    <p>Nama satuan boleh diketik bebas. <b>Isi</b> selalu dihitung dalam satuan terkecil — rokok:
                        BUNGKUS isi 16, SLOP isi <b>160</b> (bukan 10). Harga diisi per satuan.
                        <button type="button" class="s-guide-link" data-guide-open="multiUnitGuide">Lihat contoh
                            pengisian</button>
                    </p>
                </div>
            </div>

            @error('units')
                <small class="p-error">{{ $message }}</small>
            @enderror

            <div class="p-units" id="unitRows"></div>

            <button type="button" class="p-btn p-btn-ghost" id="addUnit">
                <i data-lucide="plus"></i> Tambah satuan
            </button>
            <small class="p-hint">Barcode per satuan opsional — scan barcode kemasan bungkus / slop supaya kasir
                langsung mendapat satuan yang benar.</small>
        </section>

        {{-- EXPIRE --}}
        <section class="p-card p-section">
            <div class="p-toggle-row">
                <div>
                    <strong>Lacak tanggal expire (FEFO)</strong>
                    <small>Stok dicatat per batch dari Stok Masuk. Kasir otomatis menjual batch yang expire-nya paling
                        dekat.</small>
                </div>
                <label class="p-switch">
                    <input type="hidden" name="track_expiry" value="0">
                    <input type="checkbox" name="track_expiry" value="1" id="track_expiry"
                        @checked($trackExpiry)>
                    <span class="p-switch-track"></span>
                    <span class="sr-only">Lacak expire</span>
                </label>
            </div>

            <div class="p-grid" id="expiryFields" @if (!$trackExpiry) hidden @endif>
                <div class="p-field">
                    <label for="expiry_warning_days">Peringatan sebelum expire (hari)</label>
                    <input type="number" id="expiry_warning_days" name="expiry_warning_days" min="1"
                        max="365" value="{{ $warnDays }}">
                </div>

                @if (!$trackExpiry && $hasStock)
                    {{-- produk lama yang baru diaktifkan: stok yang ada dijadikan satu batch --}}
                    <div class="p-field">
                        <label for="opening_expiry_date">Expire stok yang ada sekarang <span
                                class="req">*</span></label>
                        <input type="date" id="opening_expiry_date" name="opening_expiry_date"
                            value="{{ old('opening_expiry_date') }}"
                            class="@error('opening_expiry_date') is-invalid @enderror">
                        <small class="p-hint">Stok {{ $p->stockBreakdown() }} dijadikan satu batch dengan tanggal
                            ini.</small>
                        @error('opening_expiry_date')
                            <small class="p-error">{{ $message }}</small>
                        @enderror
                    </div>
                @elseif (!$isEdit)
                    <p class="p-hint p-span-2">Untuk produk ber-expire, isi <b>Stok Awal 0</b> lalu catat barangnya
                        lewat
                        <b>Stok Masuk</b> lengkap dengan tanggal expire.
                    </p>
                @endif
            </div>
        </section>

    </div>

    {{-- ===================== KOLOM SAMPING ===================== --}}
    <aside class="p-form-side">

        {{-- FOTO --}}
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="image"></i></span>
                <div>
                    <h3>Foto Produk</h3>
                    <p>{{ $isEdit ? 'Kosongkan jika tidak diganti.' : 'Opsional, disarankan.' }}</p>
                </div>
            </div>

            <label class="p-upload @error('image') is-invalid @enderror" data-upload data-max-mb="2">
                <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp">

                <img alt="Pratinjau foto produk"
                    @if ($isEdit && $p->image) src="{{ asset($p->image) }}" @else hidden @endif>

                <span class="p-upload-empty" @if ($isEdit && $p->image) hidden @endif>
                    <i data-lucide="image-plus"></i>
                    <strong>Pilih atau seret foto</strong>
                    <small>JPG, PNG, atau WebP</small>
                </span>
            </label>

            <div class="p-upload-meta" hidden>
                <span data-upload-name></span>
                <button type="button" class="p-link" data-upload-reset>Batal</button>
            </div>

            @error('image')
                <small class="p-error">{{ $message }}</small>
            @enderror
        </section>

        {{-- BARCODE (hanya saat edit) --}}
        @if ($isEdit && $p->barcode)
            <section class="p-card p-section">
                <div class="p-section-head">
                    <span class="p-section-icon"><i data-lucide="barcode"></i></span>
                    <div>
                        <h3>Barcode</h3>
                        <p>{{ $isStoreCode ? 'Kode toko (dibuat otomatis).' : 'Barcode dari kemasan.' }}</p>
                    </div>
                </div>

                <div class="p-barcode-preview">
                    {!! \App\Support\Barcode::svg($p->barcode, 30) !!}
                    <span>{{ $p->barcode }}</span>
                </div>

                @if (Route::has('products.labels'))
                    <a href="{{ route('products.labels', ['qty' => [$p->id => 1]]) }}"
                        class="p-btn p-btn-ghost p-btn-block">
                        <i data-lucide="printer"></i>
                        Cetak Label
                    </a>
                @endif
            </section>
        @endif

        {{-- PENGATURAN --}}
        <section class="p-card p-section">
            <div class="p-toggle-row">
                <div>
                    <strong>Tampilkan di toko</strong>
                    <small>Produk nonaktif tidak muncul di kasir.</small>
                </div>
                <label class="p-switch">
                    <input type="hidden" name="status" value="0">
                    <input type="checkbox" name="status" value="1" @checked($statusOn)>
                    <span class="p-switch-track"></span>
                    <span class="sr-only">Status aktif</span>
                </label>
            </div>

            <div class="p-toggle-row">
                <div>
                    <strong>Tandai terlaris</strong>
                    <small>Diberi label "Terlaris" di daftar produk.</small>
                </div>
                <label class="p-switch">
                    <input type="hidden" name="is_favorite" value="0">
                    <input type="checkbox" name="is_favorite" value="1" @checked($favoriteOn)>
                    <span class="p-switch-track"></span>
                    <span class="sr-only">Tandai terlaris</span>
                </label>
            </div>

            <div class="p-form-actions">
                <a href="{{ route('products.index') }}" class="p-btn p-btn-ghost">Batal</a>
                <button type="submit" class="p-btn p-btn-primary">
                    <i data-lucide="save"></i>
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' }}
                </button>
            </div>
        </section>

    </aside>
</div>

{{-- Bar simpan yang selalu terlihat di HP / tablet --}}
<div class="p-savebar">
    <a href="{{ route('products.index') }}" class="p-btn p-btn-ghost">Batal</a>
    <button type="submit" class="p-btn p-btn-primary">
        <i data-lucide="save"></i>
        {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' }}
    </button>
</div>


<style>
    .crud-page .p-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        letter-spacing: .3px;
    }

    .crud-page .p-barcode-preview {
        display: grid;
        justify-items: center;
        gap: 6px;
        padding: 14px;
        margin-bottom: 12px;
        border: 1px solid var(--pb, #e5e7eb);
        border-radius: 12px;
        background: #fff;
    }

    .crud-page .p-barcode-preview svg {
        width: 100%;
        max-width: 260px;
        height: 64px;
    }

    .crud-page .p-barcode-preview span {
        font-family: ui-monospace, Menlo, Consolas, monospace;
        font-size: 13px;
        letter-spacing: 1px;
    }

    .crud-page .p-unit-row {
        grid-template-columns: 1.2fr .8fr 1fr 1.2fr auto;
        align-items: end;
        margin-bottom: 8px;
    }

    @media (max-width: 720px) {
        .crud-page .p-unit-row {
            grid-template-columns: 1fr 1fr;
            padding-bottom: 10px;
            border-bottom: 1px dashed var(--pb, #e5e7eb);
        }
    }

    .crud-page .p-grid[hidden],
    .crud-page .p-similar[hidden],
    .crud-page [hidden].p-similar-confirm {
        display: none;
    }

    /* ---------- produk mirip ---------- */
    .crud-page .p-similar {
        margin-top: 10px;
        padding: 12px;
        border: 1px solid #f5d9a8;
        border-radius: 14px;
        background: #fffaf0;
    }

    .crud-page .p-similar.is-strong {
        border-color: #fbbf9a;
        background: #fff4ec;
    }

    .crud-page .p-similar-head {
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }

    .crud-page .p-similar-head strong {
        display: block;
        font-size: 14px;
        color: #14171c;
    }

    .crud-page .p-similar-head small {
        display: block;
        color: #667080;
        font-size: 12.5px;
    }

    .crud-page .p-similar-icon {
        flex: none;
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border-radius: 10px;
        color: #fff;
        background: linear-gradient(135deg, #ff6a1a, #ffae1f);
    }

    .crud-page .p-similar-icon svg {
        width: 16px;
        height: 16px;
    }

    .crud-page .p-similar-list {
        list-style: none;
        margin: 10px 0 0;
        padding: 0;
        display: grid;
        gap: 6px;
    }

    .crud-page .p-similar-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        padding: 9px 10px;
        border-radius: 10px;
        background: #fff;
        border: 1px solid #f1e4d4;
    }

    .crud-page .p-similar-main {
        min-width: 0;
    }

    .crud-page .p-similar-main strong {
        font-size: 13.5px;
    }

    .crud-page .p-similar-main small {
        display: block;
        color: #667080;
        font-size: 12px;
    }

    .crud-page .p-similar-actions {
        display: flex;
        gap: 6px;
    }

    .crud-page .p-similar-actions .p-btn {
        padding: 6px 10px;
        font-size: 12.5px;
        min-height: 0;
    }

    .crud-page .p-similar-confirm {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        margin-top: 10px;
        font-size: 13px;
        color: #14171c;
        cursor: pointer;
    }

    .crud-page .p-similar-confirm input {
        margin-top: 3px;
        accent-color: #ff6a1a;
    }

    /* ---------- margin ---------- */
    .crud-page .p-margin {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        padding: 10px 12px;
        border-radius: 12px;
        font-size: 13px;
        line-height: 1.5;
    }

    .crud-page .p-margin[hidden] {
        display: none;
    }

    .crud-page .p-margin svg {
        flex: none;
        width: 17px;
        height: 17px;
        margin-top: 1px;
    }

    .crud-page .p-margin.is-ok {
        background: #ecfdf3;
        color: #14532d;
    }

    .crud-page .p-margin.is-warn {
        background: #fef7e6;
        color: #6b4a12;
    }

    .crud-page .p-margin.is-bad {
        background: #fef2f2;
        color: #7f1d1d;
    }

    .crud-page .p-btn-block {
        width: 100%;
        justify-content: center;
    }
</style>

<script>
    (() => {
        const rowsEl = document.getElementById('unitRows');
        const baseInput = document.getElementById('base_unit');
        const INITIAL = @json(array_values($extraUnits));
        let seq = 0;
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [c]));
        const base = () => (baseInput.value || 'PCS').toUpperCase();

        const addRow = (u = {}) => {
            const k = seq++;
            const row = document.createElement('div');
            row.className = 'p-grid p-unit-row';
            row.innerHTML =
                `
                ${u.id ? `<input type="hidden" name="units[${k}][id]" value="${esc(u.id)}">` : ''}
                <div class="p-field"><label>Nama satuan</label>
                    <input type="text" name="units[${k}][name]" required maxlength="30" value="${esc(u.name)}"
                        placeholder="SLOP" style="text-transform:uppercase" list="packUnitList"></div>
                <div class="p-field"><label>Isi (<span data-base-label>${esc(base())}</span>)</label>
                    <input type="number" name="units[${k}][conversion]" required min="1.001" step="any" value="${esc(u.conversion)}"
                        placeholder="160" inputmode="decimal"></div>
                <div class="p-field"><label>Harga jual</label>
                    <input type="number" name="units[${k}][price]" required min="0" step="1" value="${esc(u.price)}"
                        placeholder="345000" inputmode="numeric"></div>
                <div class="p-field"><label>Barcode</label>
                    <input type="text" name="units[${k}][barcode]" maxlength="64" value="${esc(u.barcode)}"
                        placeholder="Opsional" class="p-mono" data-scan-field></div>
                <div class="p-field"><label>&nbsp;</label>
                    <button type="button" class="p-icon-btn is-danger" data-remove-unit aria-label="Hapus satuan"><i data-lucide="x"></i></button></div>`;
            rowsEl.appendChild(row);
            window.refreshIcons?.();
        };

        document.getElementById('addUnit').addEventListener('click', () => addRow());
        rowsEl.addEventListener('click', (e) => {
            if (e.target.closest('[data-remove-unit]')) e.target.closest('.p-unit-row').remove();
        });
        baseInput.addEventListener('input', () => {
            document.querySelectorAll('[data-base-label]').forEach((el) => {
                el.textContent = base();
            });
        });
        INITIAL.forEach(addRow);

        const expiry = document.getElementById('track_expiry');
        expiry.addEventListener('change', () => {
            document.getElementById('expiryFields').hidden = !expiry.checked;
        });

        /* ---------- modal & margin ---------- */
        const marginBox = document.getElementById('marginBox');
        const rp = (n) => 'Rp\u00A0' + Math.round(Number(n) || 0).toLocaleString('id-ID');

        const renderMargin = () => {
            // baca dari kolom yang terlihat (hidden diisi app.js sesudah event ini)
            const digits = (el) => String(el.value).replace(/\D/g, '');
            const priceRaw = digits(document.getElementById('price_display'));
            const costRaw = digits(document.getElementById('cost_display'));
            const price = Number(priceRaw) || 0;
            const cost = costRaw === '' ? null : Number(costRaw);
            const unit = base();
            let cls = '';
            let html = '';

            if (cost === null) {
                cls = 'is-warn';
                html = `<i data-lucide="triangle-alert"></i><span><b>Belum ada modal.</b> Penjualan produk ini tercatat
                    tanpa modal, jadi laba di laporan terlihat 100% sampai ada Stok Masuk atau modal diisi.</span>`;
            } else if (price > 0 && cost >= price) {
                cls = 'is-bad';
                html = `<i data-lucide="circle-alert"></i><span><b>Modal ${rp(cost)}</b> sama / di atas harga jual
                    ${rp(price)} per ${unit}. Produk ini dijual rugi.</span>`;
            } else if (price > 0) {
                const margin = price - cost;
                cls = 'is-ok';
                html = `<i data-lucide="trending-up"></i><span>Untung <b>${rp(margin)}</b> per ${unit}
                    (${Math.round(margin / price * 100)}% dari harga jual).</span>`;
            }

            marginBox.className = 'p-span-2 p-margin ' + cls;
            marginBox.innerHTML = html;
            marginBox.hidden = !html;
            window.refreshIcons?.();
        };

        ['input', 'change'].forEach((ev) => {
            document.getElementById('price_display').addEventListener(ev, renderMargin);
            document.getElementById('cost_display').addEventListener(ev, renderMargin);
        });
        baseInput.addEventListener('input', renderMargin);
        renderMargin();

        /* ---------- cek produk mirip ---------- */
        const nameInput = document.getElementById('name');
        const box = document.getElementById('similarBox');
        const list = document.getElementById('similarList');
        const confirmEl = document.getElementById('similarConfirm');
        const STRONG = {{ \App\Support\ProductMatcher::STRONG }};
        let timer = null;
        let controller = null;

        const renderSimilar = (items) => {
            if (!items.length) {
                box.hidden = true;
                if (confirmEl) confirmEl.hidden = true;
                return;
            }
            const strong = items.some((i) => i.score >= STRONG);
            box.classList.toggle('is-strong', strong);
            document.getElementById('similarTitle').textContent = strong ?
                'Produk ini sepertinya sudah ada' :
                'Ada produk dengan nama mirip';
            document.getElementById('similarSub').textContent = strong ?
                'Kalau barangnya sama, buka produk itu dan catat Stok Masuk — jangan buat baru.' :
                'Periksa dulu supaya tidak dobel.';

            list.innerHTML = items.map((i) => `
                <li>
                    <div class="p-similar-main">
                        <strong>${esc(i.name)}</strong>
                        ${i.active ? '' : '<span class="s-badge tone-red">Nonaktif</span>'}
                        <small>${[i.code ? 'Kode ' + esc(i.code) : '', 'Stok ' + esc(i.stock_text), rp(i.price),
                            i.has_cost ? '' : 'belum ada modal'].filter(Boolean).join(' · ')}</small>
                    </div>
                    <div class="p-similar-actions">
                        <a href="${esc(i.edit_url)}" class="p-btn p-btn-ghost" target="_blank" rel="noopener">
                            <i data-lucide="external-link"></i> Buka</a>
                        <a href="${esc(i.stock_in_url)}" class="p-btn p-btn-ghost" target="_blank" rel="noopener">
                            <i data-lucide="package-plus"></i> Stok Masuk</a>
                    </div>
                </li>`).join('');

            if (confirmEl) confirmEl.hidden = !strong;
            box.hidden = false;
            window.refreshIcons?.();
        };

        const check = () => {
            const name = nameInput.value.trim();
            if (name.length < 3) return renderSimilar([]);

            controller?.abort();
            controller = new AbortController();
            const url = new URL(nameInput.dataset.similarUrl, location.origin);
            url.searchParams.set('name', name);
            if (nameInput.dataset.similarExclude) url.searchParams.set('exclude', nameInput.dataset
                .similarExclude);

            fetch(url, {
                    headers: {
                        Accept: 'application/json'
                    },
                    signal: controller.signal
                })
                .then((r) => (r.ok ? r.json() : {
                    data: []
                }))
                .then((res) => renderSimilar(res.data || []))
                .catch(() => {});
        };

        nameInput.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(check, 350);
        });

        const FROM_SERVER = @json($similarFromServer);
        if (FROM_SERVER.length) renderSimilar(FROM_SERVER);
        else if (nameInput.value.trim().length >= 3 && !nameInput.dataset.similarExclude) check();
    })();

    // Scanner USB/Bluetooth mengetik kode lalu menekan Enter. Di kolom kode &
    // barcode, Enter hanya pindah ke kolom berikutnya — form tidak terkirim.
    document.querySelectorAll('[data-scan-field]').forEach((input) => {
        input.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const fields = [...input.form.querySelectorAll(
                'input:not([type=hidden]), select, textarea')];
            fields[fields.indexOf(input) + 1]?.focus();
        });
    });
</script>
