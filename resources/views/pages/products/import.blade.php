@extends('layouts.app')

@section('title', 'Import Produk')

@section('content')
    @php
        $rp = fn($n) => $n === null ? '—' : "Rp\u{00A0}" . number_format((float) $n, 0, ',', '.');
        $qty = fn($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
        $hasResult = ! empty($result);
        $counts = $hasResult ? $result['counts'] : ['new' => 0, 'update' => 0, 'error' => 0];
        $valid = $counts['new'] + $counts['update'];
        $has = fn($col) => $hasResult && in_array($col, $result['columns'], true);
        $newCategories = $hasResult ? ($result['categories'] ?? []) : [];
        $similarCount = count(array_filter($newCategories, fn($c) => $c['similar']));
    @endphp

    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('products.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke produk
                </a>
                <h1>Import Produk</h1>
                <p>Tambah atau perbarui banyak produk sekaligus dari file Excel.</p>
            </div>

            <a href="{{ asset('templates/template-import-produk.xlsx') }}" class="p-btn p-btn-ghost" download>
                <i data-lucide="download"></i>
                Unduh Template
            </a>
        </div>

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <div>
                    <strong>File belum bisa diproses.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        @unless ($hasResult)
            {{-- ===================== LANGKAH 1: UNGGAH ===================== --}}
            <div class="p-form-layout">
                <div class="p-form-main">
                    <form action="{{ route('products.import.preview') }}" method="POST" enctype="multipart/form-data"
                        class="p-card p-section" id="importForm">
                        @csrf

                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="file-spreadsheet"></i></span>
                            <div>
                                <h3>Unggah File</h3>
                                <p>Format .xlsx atau .csv, maksimal 5 MB dan 5.000 baris.</p>
                            </div>
                        </div>

                        <label class="p-dropzone" id="dropzone">
                            <input type="file" name="file" id="file" required
                                accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                            <span class="p-dropzone-icon"><i data-lucide="upload-cloud"></i></span>
                            <strong id="dropTitle">Pilih file atau seret ke sini</strong>
                            <small id="dropHint">Belum ada file dipilih</small>
                        </label>

                        <div class="p-import-actions">
                            <button type="submit" class="p-btn p-btn-primary" id="previewBtn" disabled>
                                <i data-lucide="scan-search"></i>
                                Periksa File
                            </button>
                            <span class="p-hint">File diperiksa dulu. Belum ada yang disimpan di langkah ini.</span>
                        </div>
                    </form>
                </div>

                <aside class="p-form-side">
                    <section class="p-card p-section">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="list-checks"></i></span>
                            <div>
                                <h3>Kolom di File</h3>
                                <p>Baris pertama = judul kolom.</p>
                            </div>
                        </div>

                        <ul class="p-cols">
                            <li><b>Kode Produk</b><small>Kode part, opsional, tidak boleh kembar</small></li>
                            <li><b>Barcode</b><small>Dari kemasan, opsional. Kosong = kode toko otomatis</small></li>
                            <li><b>Nama Produk</b> <span class="req">wajib</span></li>
                            <li><b>Kategori</b> <span class="req">wajib</span><small>Dibuat otomatis kalau belum ada</small></li>
                            <li><b>Harga Jual</b> <span class="req">wajib</span></li>
                            <li><b>Harga Beli</b></li>
                            <li><b>Stok</b><small>Kosong = 0</small></li>
                            <li><b>Satuan</b><small>PCS, SET, BOTOL, … kosong = PCS</small></li>
                            <li><b>Deskripsi</b></li>
                            <li><b>Status</b><small>Aktif / Nonaktif</small></li>
                            <li><b>Terlaris</b><small>Ya / Tidak</small></li>
                        </ul>

                        <p class="p-send-note is-off">
                            Kode, barcode, atau nama yang sudah ada di aplikasi akan <b>diperbarui</b>, bukan dibuat dobel.
                        </p>
                    </section>
                </aside>
            </div>
        @else
            {{-- ===================== LANGKAH 2: PRATINJAU ===================== --}}
            <section class="p-stats is-3" aria-label="Hasil pemeriksaan">
                <div class="p-stat tone-green">
                    <span class="p-stat-icon"><i data-lucide="plus"></i></span>
                    <span class="p-stat-label">Produk baru</span>
                    <strong class="p-stat-value">{{ $counts['new'] }}</strong>
                    <span class="p-stat-sub">Akan ditambahkan</span>
                </div>
                <div class="p-stat tone-blue">
                    <span class="p-stat-icon"><i data-lucide="refresh-cw"></i></span>
                    <span class="p-stat-label">Diperbarui</span>
                    <strong class="p-stat-value">{{ $counts['update'] }}</strong>
                    <span class="p-stat-sub">Kode, barcode, atau nama sudah ada</span>
                </div>
                <div class="p-stat {{ $counts['error'] ? 'tone-red' : 'tone-gray' }}">
                    <span class="p-stat-icon"><i data-lucide="triangle-alert"></i></span>
                    <span class="p-stat-label">Bermasalah</span>
                    <strong class="p-stat-value">{{ $counts['error'] }}</strong>
                    <span class="p-stat-sub">{{ $counts['error'] ? 'Akan dilewati, tidak disimpan' : 'Tidak ada masalah' }}</span>
                </div>
            </section>

            <form action="{{ route('products.import.store') }}" method="POST" id="confirmForm" data-guard>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                {{-- ===================== KATEGORI BARU ===================== --}}
                @if ($newCategories)
                    <section class="p-card p-section p-newcat {{ $similarCount ? 'has-warning' : '' }}"
                        aria-labelledby="newcat-title">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="{{ $similarCount ? 'triangle-alert' : 'layers-3' }}"></i></span>
                            <div>
                                <h3 id="newcat-title">{{ count($newCategories) }} kategori baru akan dibuat</h3>
                                <p>
                                    @if ($similarCount)
                                        {{ $similarCount }} di antaranya mirip dengan kategori yang sudah ada — mungkin salah ketik.
                                    @else
                                        Nama-nama ini belum ada di menu Kategori.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <ul class="p-newcat-list">
                            @foreach ($newCategories as $category)
                                <li class="{{ $category['similar'] ? 'is-similar' : '' }}">
                                    <div class="p-newcat-name">
                                        <strong>{{ $category['name'] }}</strong>
                                        <small>{{ $category['count'] }} produk</small>
                                    </div>

                                    @if ($category['similar'])
                                        <label class="p-check">
                                            <input type="checkbox" name="merge[]" value="{{ $category['key'] }}">
                                            <span>
                                                <b>Mirip dengan "{{ $category['similar']['name'] }}"</b>
                                                <small>Centang untuk memasukkan produknya ke kategori lama itu. Kalau tidak dicentang, kategori baru tetap dibuat.</small>
                                            </span>
                                        </label>
                                    @else
                                        <span class="p-status is-on">Baru</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <div class="p-card p-import-confirm">

                <div class="p-import-file">
                    <span class="p-section-icon"><i data-lucide="file-spreadsheet"></i></span>
                    <div>
                        <strong>{{ $filename }}</strong>
                        <small>{{ count($result['items']) }} baris data · belum ada yang disimpan</small>
                    </div>
                </div>

                @if ($counts['update'] > 0 && $has('stock'))
                    <label class="p-check">
                        <input type="checkbox" name="overwrite_stock" value="1">
                        <span>
                            <b>Timpa stok produk yang sudah ada</b>
                            <small>Biarkan kosong kalau hanya ingin memperbarui harga. Barang datang sebaiknya lewat Stok Masuk.</small>
                        </span>
                    </label>
                @endif

                <div class="p-import-buttons">
                    @if ($counts['error'] > 0)
                        <a href="{{ route('products.import.errors', ['token' => $token]) }}" class="p-btn p-btn-ghost is-danger"
                            title="Unduh baris yang salah beserta alasannya, perbaiki, lalu unggah lagi">
                            <i data-lucide="download"></i> Unduh {{ $counts['error'] }} Baris Bermasalah
                        </a>
                    @endif

                    <a href="{{ route('products.import') }}" class="p-btn p-btn-ghost">
                        <i data-lucide="rotate-ccw"></i> Ganti File
                    </a>
                    <button type="submit" class="p-btn p-btn-primary" @disabled($valid === 0)>
                        <i data-lucide="check"></i>
                        Import {{ $valid }} Produk
                    </button>
                </div>
                </div>
            </form>

            <div class="p-card">
                <div class="p-toolbar">
                    <label class="p-search">
                        <i data-lucide="search"></i>
                        <input type="search" placeholder="Cari di pratinjau..." autocomplete="off"
                            aria-label="Cari baris" data-table-filter="#importTable">
                    </label>

                    <nav class="p-chips" aria-label="Saring baris">
                        <button type="button" class="p-chip is-active" data-state-filter="">Semua</button>
                        <button type="button" class="p-chip" data-state-filter="new">Baru</button>
                        <button type="button" class="p-chip" data-state-filter="update">Diperbarui</button>
                        @if ($counts['error'])
                            <button type="button" class="p-chip" data-state-filter="error">Bermasalah</button>
                        @endif
                    </nav>
                </div>

                <div class="p-table-wrap">
                    <table class="p-table p-table-import" id="importTable">
                        <thead>
                            <tr>
                                <th>Baris</th>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th class="text-end">Harga jual</th>
                                <th class="text-end cell-hide-sm">Harga beli</th>
                                <th class="text-end cell-hide-sm">Stok</th>
                                <th>Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['items'] as $item)
                                @php $d = $item['data']; @endphp
                                <tr data-filter-row data-state="{{ $item['state'] }}"
                                    class="{{ $item['state'] === 'error' ? 'is-error' : '' }}">
                                    <td class="cell-line">{{ $item['line'] }}</td>

                                    <td class="cell-main">
                                        <div class="p-name">{{ $d['name'] !== '' ? $d['name'] : '(tanpa nama)' }}</div>
                                        @if (! empty($d['code']) || ! empty($d['barcode']))
                                            <small class="p-desc p-import-codes">
                                                @if (! empty($d['code']))
                                                    <b>{{ $d['code'] }}</b>
                                                @endif
                                                @if (! empty($d['barcode']))
                                                    <span title="Barcode"><i data-lucide="barcode"></i>{{ $d['barcode'] }}</span>
                                                @endif
                                                @if ($item['state'] === 'update' && ($item['matched_by'] ?? null) !== 'name')
                                                    <em>· cocok lewat {{ $item['matched_by'] === 'code' ? 'kode' : 'barcode' }}</em>
                                                @endif
                                            </small>
                                        @endif
                                        @if ($item['errors'])
                                            <ul class="p-row-errors">
                                                @foreach ($item['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        @elseif ($d['description'])
                                            <small class="p-desc">{{ $d['description'] }}</small>
                                        @endif
                                    </td>

                                    <td class="cell-category">
                                        <span class="p-category">{{ $d['category'] ?: '—' }}</span>
                                    </td>

                                    <td class="cell-price text-end"><span class="p-price">{{ $rp($d['price']) }}</span></td>
                                    <td class="text-end cell-hide-sm">{{ $rp($d['cost_price']) }}</td>
                                    <td class="text-end cell-hide-sm">{{ $qty($d['stock']) }} {{ $d['stock'] !== null ? ($d['base_unit'] ?? '') : '' }}</td>

                                    <td class="cell-status">
                                        @if ($item['state'] === 'new')
                                            <span class="p-status is-on">Baru</span>
                                        @elseif ($item['state'] === 'update')
                                            <span class="p-role is-staff">Diperbarui</span>
                                        @else
                                            <span class="p-stock is-empty">Dilewati</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="p-empty-row" data-filter-empty hidden>
                                <td colspan="7">
                                    <div class="p-empty">
                                        <span class="p-empty-icon"><i data-lucide="search-x"></i></span>
                                        <h4>Tidak ada baris yang cocok</h4>
                                        <p>Ubah kata kunci atau saringannya.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endunless

    </div>

    <style>
        .crud-page .p-import-codes { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 10px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .crud-page .p-import-codes span { display: inline-flex; align-items: center; gap: 3px; }
        .crud-page .p-import-codes svg { width: 12px; height: 12px; }
        .crud-page .p-import-codes em { font-family: inherit; font-style: normal; color: var(--pm, #667080); }
    </style>
@endsection

@push('scripts')
    <script>
        (() => {
            /* ---------- langkah 1: pilih / seret file ---------- */
            const input = document.getElementById('file');
            const zone = document.getElementById('dropzone');

            if (input && zone) {
                const button = document.getElementById('previewBtn');
                const form = document.getElementById('importForm');

                const show = () => {
                    const file = input.files[0];
                    const ok = file && /\.(xlsx|csv)$/i.test(file.name) && file.size <= 5 * 1024 * 1024;

                    zone.classList.toggle('has-file', !!ok);
                    button.disabled = !ok;

                    if (!file) return;

                    document.getElementById('dropTitle').textContent = file.name;
                    document.getElementById('dropHint').textContent = !/\.(xlsx|csv)$/i.test(file.name)
                        ? 'Format harus .xlsx atau .csv'
                        : file.size > 5 * 1024 * 1024
                            ? 'Ukuran lebih dari 5 MB'
                            : Math.max(1, Math.round(file.size / 1024)) + ' KB · siap diperiksa';
                };

                input.addEventListener('change', show);

                ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => {
                    e.preventDefault();
                    zone.classList.add('is-drag');
                }));
                ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => {
                    e.preventDefault();
                    zone.classList.remove('is-drag');
                }));
                zone.addEventListener('drop', (e) => {
                    if (!e.dataTransfer.files.length) return;
                    input.files = e.dataTransfer.files;
                    show();
                });

                form.addEventListener('submit', () => {
                    button.disabled = true;
                    button.classList.add('is-loading');
                    window.showLoading?.();
                });
            }

            /* ---------- langkah 2: saring baris menurut hasil ---------- */
            const table = document.getElementById('importTable');
            if (!table) return;

            const chips = document.querySelectorAll('[data-state-filter]');
            chips.forEach((chip) => chip.addEventListener('click', () => {
                chips.forEach((c) => c.classList.toggle('is-active', c === chip));
                table.dataset.state = chip.dataset.stateFilter;
            }));
        })();
    </script>
@endpush
