@extends('layouts.app')

@section('title', 'Pengeluaran')

@section('content')
    @php
        /*
         | Nilai (value) kategori TETAP sama dengan yang tersimpan di database.
         | Yang diganti hanya label & ikon supaya sesuai toko sparepart.
         */
        $expenseCategories = [
            'Gaji' => ['label' => 'Gaji Karyawan', 'icon' => 'users', 'tone' => 'tone-blue', 'hint' => 'Gaji mekanik & staf toko'],
            'Operasional Toko' => ['label' => 'Operasional Toko', 'icon' => 'store', 'tone' => 'tone-orange', 'hint' => 'Kebutuhan harian & perlengkapan'],
            'Listrik' => ['label' => 'Listrik', 'icon' => 'zap', 'tone' => 'tone-amber', 'hint' => 'Tagihan listrik & utilitas'],
            'Tak Terduga' => ['label' => 'Tak Terduga', 'icon' => 'triangle-alert', 'tone' => 'tone-red', 'hint' => 'Biaya darurat & lain-lain'],
        ];
        $fallbackCategory = ['label' => null, 'icon' => 'receipt', 'tone' => 'tone-gray', 'hint' => ''];

        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) $n, 0, ',', '.');

        $from = $start_date ? \Carbon\Carbon::parse($start_date) : null;
        $to = $end_date ? \Carbon\Carbon::parse($end_date) : null;
        $periodLabel = $from && $to
            ? ($from->isSameDay($to) ? $from->translatedFormat('d M Y') : $from->translatedFormat('d M Y') . ' – ' . $to->translatedFormat('d M Y'))
            : 'Semua tanggal';

        $total = (float) ($totalExpense ?? 0);
        $count = $expenses->total();
        $isFiltered = filled($category) || request()->filled('start_date') || request()->filled('end_date');

        // tautan rentang cepat (kategori yang sedang dipilih ikut dibawa)
        $today = now();
        $quickRanges = [
            'Hari ini' => [$today->toDateString(), $today->toDateString()],
            '7 hari' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
            'Bulan ini' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
            'Bulan lalu' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        ];
        $rangeUrl = fn($r) => route('expenses.index', array_filter([
            'start_date' => $r[0],
            'end_date' => $r[1],
            'category' => $category,
        ]));
        $categoryUrl = fn($c) => route('expenses.index', array_filter([
            'start_date' => $start_date,
            'end_date' => $end_date,
            'category' => $c,
        ]));

        // status modal setelah validasi gagal (form dibuka ulang otomatis)
        $oldIsEdit = old('_method') === 'PUT' && old('expense_id');
        $oldAction = $oldIsEdit ? route('expenses.update', old('expense_id')) : route('expenses.store');
    @endphp

    <div class="crud-page">

        {{-- ===================== HEADER ===================== --}}
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Keuangan</span>
                <h1>Pengeluaran</h1>
                <p>Catat dan pantau biaya operasional toko: gaji, listrik, perlengkapan, dan lainnya.</p>
            </div>

            <button type="button" class="p-btn p-btn-primary" data-expense-create>
                <i data-lucide="plus"></i>
                Tambah Pengeluaran
            </button>
        </div>

        {{-- ===================== FILTER ===================== --}}
        <section class="p-card p-filter-card" aria-label="Filter pengeluaran">
            <form method="GET" action="{{ route('expenses.index') }}" class="p-filter" id="expenseFilter">
                <div class="p-field">
                    <label for="start_date">Dari</label>
                    <input type="date" id="start_date" name="start_date" value="{{ $start_date }}">
                </div>

                <div class="p-field">
                    <label for="end_date">Sampai</label>
                    <input type="date" id="end_date" name="end_date" value="{{ $end_date }}"
                        min="{{ $start_date }}">
                </div>

                <div class="p-field">
                    <label for="category">Kategori</label>
                    <select id="category" name="category">
                        <option value="">Semua kategori</option>
                        @foreach ($expenseCategories as $value => $cat)
                            <option value="{{ $value }}" @selected($category === $value)>{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="p-filter-actions">
                    <button type="submit" class="p-btn p-btn-dark">
                        <i data-lucide="filter"></i>
                        Terapkan
                    </button>
                    <a href="{{ route('expenses.index') }}" class="p-icon-btn p-filter-reset" title="Reset filter"
                        aria-label="Reset filter">
                        <i data-lucide="rotate-ccw"></i>
                    </a>
                </div>
            </form>

            <nav class="p-chips" aria-label="Rentang cepat">
                @foreach ($quickRanges as $label => $range)
                    @php $active = $start_date === $range[0] && $end_date === $range[1]; @endphp
                    <a href="{{ $rangeUrl($range) }}" class="p-chip {{ $active ? 'is-active' : '' }}"
                        @if ($active) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </section>

        {{-- ===================== RINGKASAN ===================== --}}
        <section class="p-stats" aria-label="Ringkasan pengeluaran">

            <div class="p-stat is-total">
                <div class="p-stat-main">
                    <span class="p-stat-label">
                        <i data-lucide="wallet"></i>
                        Total pengeluaran{{ filled($category) ? ' · ' . ($expenseCategories[$category]['label'] ?? $category) : '' }}
                    </span>
                    <strong class="p-stat-value">{{ $rp($total) }}</strong>
                    <span class="p-stat-sub">
                        <i data-lucide="calendar-days"></i>
                        {{ $periodLabel }} · {{ number_format($count, 0, ',', '.') }} transaksi
                    </span>
                </div>

                {{-- komposisi per kategori --}}
                <div class="p-stackbar" role="img"
                    aria-label="Komposisi pengeluaran per kategori">
                    @foreach ($expenseCategories as $value => $cat)
                        @php $v = (float) ($expenseSummary[$value] ?? 0); @endphp
                        @if ($total > 0 && $v > 0)
                            <span class="{{ $cat['tone'] }}" style="flex-grow: {{ round($v / $total * 1000) }}"
                                title="{{ $cat['label'] }}: {{ $rp($v) }}"></span>
                        @endif
                    @endforeach
                </div>
            </div>

            @foreach ($expenseCategories as $value => $cat)
                @php
                    $v = (float) ($expenseSummary[$value] ?? 0);
                    $pct = $total > 0 ? round($v / $total * 100) : 0;
                    $isActive = $category === $value;
                @endphp
                <a href="{{ $categoryUrl($isActive ? null : $value) }}"
                    class="p-stat {{ $cat['tone'] }} {{ $isActive ? 'is-active' : '' }}"
                    title="{{ $isActive ? 'Tampilkan semua kategori' : 'Tampilkan hanya ' . $cat['label'] }}"
                    @if ($isActive) aria-current="true" @endif>
                    <span class="p-stat-icon"><i data-lucide="{{ $cat['icon'] }}"></i></span>
                    <span class="p-stat-label">{{ $cat['label'] }}</span>
                    <strong class="p-stat-value">{{ $rp($v) }}</strong>
                    <span class="p-stat-bar" aria-hidden="true"><span style="width: {{ $pct }}%"></span></span>
                    <span class="p-stat-sub">{{ $pct }}% · {{ $cat['hint'] }}</span>
                </a>
            @endforeach
        </section>

        {{-- ===================== DAFTAR ===================== --}}
        <div class="p-card">
            <div class="p-toolbar">
                <label class="p-search">
                    <i data-lucide="search"></i>
                    <input type="search" placeholder="Cari di halaman ini..." autocomplete="off"
                        aria-label="Cari pengeluaran" data-table-filter="#expenseTable">
                </label>

                <span class="p-count">
                    <strong data-filter-count>{{ $expenses->count() }}</strong> dari {{ number_format($count, 0, ',', '.') }}
                    pengeluaran
                </span>
            </div>

            <div class="p-table-wrap">
                <table class="p-table p-table-expense" id="expenseTable">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Pengeluaran</th>
                            <th>Kategori</th>
                            <th class="cell-hide-sm">Dicatat oleh</th>
                            <th class="text-end">Nominal</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($expenses as $expense)
                            @php
                                $cat = $expenseCategories[$expense->category] ?? $fallbackCategory;
                                $catLabel = $cat['label'] ?? $expense->category;
                            @endphp
                            <tr data-filter-row>
                                <td class="cell-date">
                                    <span class="p-date">
                                        <strong>{{ $expense->expense_date->translatedFormat('d M Y') }}</strong>
                                        @if ($expense->created_at)
                                            <small>dicatat {{ $expense->created_at->format('H:i') }}</small>
                                        @endif
                                    </span>
                                </td>

                                <td class="cell-main">
                                    <div class="p-item">
                                        <div class="p-thumb has-tone {{ $cat['tone'] }}">
                                            <i data-lucide="{{ $cat['icon'] }}"></i>
                                        </div>
                                        <div class="p-item-text">
                                            <div class="p-name">{{ $expense->description }}</div>
                                            @if ($expense->notes)
                                                <small class="p-desc">{{ $expense->notes }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="cell-category">
                                    <span class="p-category has-tone {{ $cat['tone'] }}">{{ $catLabel }}</span>
                                </td>

                                <td class="cell-hide-sm">
                                    @if ($expense->user)
                                        <span class="p-by">
                                            <span class="p-by-avatar">{{ strtoupper(mb_substr($expense->user->name, 0, 1)) }}</span>
                                            <span>
                                                <strong>{{ $expense->user->name }}</strong>
                                                <small>{{ ucfirst($expense->user->role ?? 'user') }}</small>
                                            </span>
                                        </span>
                                    @else
                                        <span class="p-sub">-</span>
                                    @endif
                                </td>

                                <td class="cell-price text-end">
                                    <span class="p-price is-out">{{ $rp($expense->amount) }}</span>
                                </td>

                                <td class="cell-actions">
                                    <div class="p-actions">
                                        <button type="button" class="p-icon-btn" title="Edit"
                                            aria-label="Edit {{ $expense->description }}" data-expense-edit
                                            data-id="{{ $expense->id }}"
                                            data-url="{{ route('expenses.update', $expense) }}"
                                            data-category="{{ $expense->category }}"
                                            data-description="{{ $expense->description }}"
                                            data-amount="{{ (int) round($expense->amount) }}"
                                            data-date="{{ $expense->expense_date->format('Y-m-d') }}"
                                            data-notes="{{ $expense->notes }}">
                                            <i data-lucide="pencil"></i>
                                        </button>

                                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                            data-confirm="Hapus pengeluaran &quot;{{ $expense->description }}&quot; ({{ $rp($expense->amount) }})?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-icon-btn is-danger" title="Hapus"
                                                aria-label="Hapus {{ $expense->description }}">
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
                                        <span class="p-empty-icon"><i data-lucide="wallet"></i></span>
                                        <h4>{{ $isFiltered ? 'Tidak ada pengeluaran' : 'Belum ada pengeluaran' }}</h4>
                                        <p>
                                            {{ $isFiltered
                                                ? 'Tidak ada data pada periode / kategori yang dipilih.'
                                                : 'Catat biaya pertama toko, misalnya tagihan listrik atau gaji mekanik.' }}
                                        </p>
                                        <div class="p-empty-actions">
                                            @if ($isFiltered)
                                                <a href="{{ route('expenses.index') }}" class="p-btn p-btn-ghost">
                                                    <i data-lucide="rotate-ccw"></i> Reset filter
                                                </a>
                                            @endif
                                            <button type="button" class="p-btn p-btn-primary" data-expense-create>
                                                <i data-lucide="plus"></i> Tambah Pengeluaran
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse

                        <tr class="p-empty-row" data-filter-empty hidden>
                            <td colspan="6">
                                <div class="p-empty">
                                    <span class="p-empty-icon"><i data-lucide="search-x"></i></span>
                                    <h4>Pengeluaran tidak ditemukan</h4>
                                    <p>Pencarian hanya di halaman ini. Gunakan filter di atas untuk periode lain.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($expenses->hasPages())
                <div class="p-footer">
                    <span class="p-page-info">
                        Menampilkan <strong>{{ $expenses->firstItem() }}</strong>–<strong>{{ $expenses->lastItem() }}</strong>
                        dari <strong>{{ $count }}</strong>
                    </span>
                    {{ $expenses->withQueryString()->links('vendor.pagination.custom') }}
                </div>
            @endif
        </div>

    </div>

    {{-- ===================== MODAL TAMBAH / EDIT =====================
         Satu modal untuk tambah & edit. Di HP tampil sebagai bottom sheet.
         Ditaruh di luar .crud-page supaya posisi fixed-nya tidak terkunci
         ke area konten (container query). --}}
    <div class="p-modal" id="expenseModal" data-reopen="{{ $errors->any() ? '1' : '0' }}">
        <div class="p-modal-backdrop" data-expense-close></div>

        <div class="p-modal-dialog crud-page" role="dialog" aria-modal="true" aria-labelledby="expenseModalTitle">
            <div class="p-modal-head">
                <span class="p-section-icon"><i data-lucide="receipt-text"></i></span>
                <div>
                    <h2 id="expenseModalTitle">{{ $oldIsEdit ? 'Edit Pengeluaran' : 'Tambah Pengeluaran' }}</h2>
                    <p id="expenseModalSubtitle">
                        {{ $oldIsEdit ? 'Perbarui data pengeluaran.' : 'Catat biaya operasional toko.' }}
                    </p>
                </div>
                <button type="button" class="p-icon-btn p-modal-close" data-expense-close aria-label="Tutup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <form method="POST" action="{{ $oldAction }}" id="expenseForm" novalidate>
                @csrf
                <input type="hidden" name="_method" id="expenseMethod" value="{{ $oldIsEdit ? 'PUT' : 'POST' }}">
                <input type="hidden" name="expense_id" id="expenseId" value="{{ $oldIsEdit ? old('expense_id') : '' }}">

                <div class="p-modal-body">
                    @if ($errors->any())
                        <div class="p-notice is-danger" role="alert" data-expense-error>
                            <i data-lucide="circle-alert"></i>
                            <div>
                                <strong>Periksa kembali isian yang ditandai.</strong>
                                <span>{{ $errors->first() }}</span>
                            </div>
                        </div>
                    @endif

                    <fieldset class="p-field p-fieldset">
                        <legend class="p-field-label">Kategori <span class="req">*</span></legend>
                        <div class="p-segmented is-cards is-tones">
                            @foreach ($expenseCategories as $value => $cat)
                                <label class="{{ $cat['tone'] }}">
                                    <input type="radio" name="category" value="{{ $value }}" required
                                        @checked(old('category') === $value)>
                                    <span>
                                        <i data-lucide="{{ $cat['icon'] }}"></i>
                                        {{ $cat['label'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('category')
                            <small class="p-error">{{ $message }}</small>
                        @enderror
                    </fieldset>

                    <div class="p-field">
                        <label for="expenseDescription">Keterangan <span class="req">*</span></label>
                        <input type="text" name="description" id="expenseDescription" maxlength="255" required
                            value="{{ old('description') }}" autocomplete="off"
                            placeholder="Contoh: Token listrik April, gaji mekanik"
                            class="@error('description') is-invalid @enderror">
                        @error('description')
                            <small class="p-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="p-grid">
                        <div class="p-field">
                            <label for="expenseAmount">Nominal <span class="req">*</span></label>
                            <div class="p-affix">
                                <span class="is-prefix">Rp</span>
                                <input type="text" id="expenseAmount" inputmode="numeric" autocomplete="off"
                                    placeholder="0" required data-money="#expenseAmountHidden"
                                    value="{{ old('amount') }}"
                                    class="has-prefix @error('amount') is-invalid @enderror">
                            </div>
                            {{-- yang dikirim ke server: angka mentah tanpa titik --}}
                            <input type="hidden" name="amount" id="expenseAmountHidden" value="{{ old('amount') }}">
                            @error('amount')
                                <small class="p-error">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="p-field">
                            <label for="expenseDate">Tanggal <span class="req">*</span></label>
                            <input type="date" name="expense_date" id="expenseDate" required
                                value="{{ old('expense_date', now()->toDateString()) }}"
                                max="{{ now()->toDateString() }}"
                                class="@error('expense_date') is-invalid @enderror">
                            @error('expense_date')
                                <small class="p-error">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>

                    <div class="p-field">
                        <label for="expenseNotes">Catatan</label>
                        <textarea name="notes" id="expenseNotes" rows="3" placeholder="Opsional, mis. nomor nota atau nama toko"
                            class="@error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes')
                            <small class="p-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="p-modal-foot">
                    <button type="button" class="p-btn p-btn-ghost" data-expense-close>Batal</button>
                    <button type="submit" class="p-btn p-btn-primary">
                        <i data-lucide="save"></i>
                        <span id="expenseSubmitText">{{ $oldIsEdit ? 'Simpan Perubahan' : 'Simpan Pengeluaran' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('expenseModal');
            const form = document.getElementById('expenseForm');
            if (!modal || !form) return;

            const $ = (id) => document.getElementById(id);
            const amount = $('expenseAmount');
            const amountRaw = $('expenseAmountHidden');
            const storeUrl = @json(route('expenses.store'));
            const today = () => {
                const d = new Date();
                return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
            };
            let opener = null;

            const setText = (title, subtitle, submit) => {
                $('expenseModalTitle').textContent = title;
                $('expenseModalSubtitle').textContent = subtitle;
                $('expenseSubmitText').textContent = submit;
            };

            // buang pesan error lama dari server saat form dibuka untuk data baru
            const clearErrors = () => {
                form.querySelectorAll('.p-error, [data-expense-error]').forEach((el) => el.remove());
                form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            };

            const setAmount = (value) => {
                amount.value = value || '';
                amountRaw.value = '';
                amount.dispatchEvent(new Event('input')); // diformat oleh data-money (app.js)
            };

            const open = (trigger) => {
                opener = trigger || document.activeElement;
                modal.classList.add('is-open');
                // fokus otomatis hanya di perangkat ber-mouse (di HP keyboard tidak langsung muncul)
                if (matchMedia('(pointer: fine)').matches) {
                    setTimeout(() => form.querySelector('input[name=category]:checked, #expenseDescription')?.focus(), 60);
                }
            };

            const close = () => {
                modal.classList.remove('is-open');
                opener?.focus?.();
            };

            const fill = (d = {}) => {
                clearErrors();
                form.querySelectorAll('input[name="category"]').forEach((r) => { r.checked = r.value === d.category; });
                $('expenseDescription').value = d.description || '';
                $('expenseDate').value = d.date || today();
                $('expenseNotes').value = d.notes || '';
                setAmount(d.amount);
            };

            const openCreate = (btn) => {
                fill();
                form.action = storeUrl;
                $('expenseMethod').value = 'POST';
                $('expenseId').value = '';
                setText('Tambah Pengeluaran', 'Catat biaya operasional toko.', 'Simpan Pengeluaran');
                open(btn);
            };

            const openEdit = (btn) => {
                fill(btn.dataset);
                form.action = btn.dataset.url;
                $('expenseMethod').value = 'PUT';
                $('expenseId').value = btn.dataset.id;
                setText('Edit Pengeluaran', 'Perbarui data pengeluaran.', 'Simpan Perubahan');
                open(btn);
            };

            document.addEventListener('click', (e) => {
                const create = e.target.closest('[data-expense-create]');
                if (create) return openCreate(create);

                const edit = e.target.closest('[data-expense-edit]');
                if (edit) return openEdit(edit);

                if (e.target.closest('[data-expense-close]')) close();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
            });

            // validasi + kunci tombol (anti klik ganda)
            form.addEventListener('submit', (e) => {
                amount.setCustomValidity(Number(amountRaw.value) > 0 ? '' : 'Isi nominal pengeluaran.');

                if (!form.checkValidity()) {
                    e.preventDefault();
                    form.reportValidity();
                    return;
                }

                form.querySelectorAll('button[type="submit"]').forEach((b) => {
                    b.disabled = true;
                    b.classList.add('is-loading');
                });
                window.showLoading?.();
            });

            // filter: tanggal "sampai" tidak boleh sebelum "dari"
            const start = $('start_date');
            const end = $('end_date');
            start?.addEventListener('change', () => {
                end.min = start.value;
                if (end.value && start.value && end.value < start.value) end.value = start.value;
            });

            // validasi server gagal → buka lagi modal dengan isian lama
            if (modal.dataset.reopen === '1') open();
        })();
    </script>
@endpush
