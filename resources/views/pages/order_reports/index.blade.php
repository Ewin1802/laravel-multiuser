@extends('layouts.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/order.css') }}">
@endpush

@section('title', 'Transaksi')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $num = fn($n) => number_format((float) ($n ?? 0), 0, ',', '.');
        $invoice = fn($id) => 'INV' . str_pad($id, 6, '0', STR_PAD_LEFT);

        $ranges = [1 => 'Hari ini', 2 => '2 hari', 7 => '7 hari', 14 => '14 hari', 30 => '30 hari', 90 => '90 hari'];

        $cashTotal = (float) ($summary['total_cash'] ?? 0);
        $transferTotal = (float) ($summary['total_transfer'] ?? 0);
        $tempoTotal = (float) ($summary['total_tempo'] ?? 0);
        $paymentSum = $cashTotal + $transferTotal + $tempoTotal;
        // persentase penjualan yang langsung lunas (Cash + Transfer)
        $paidPct = $paymentSum > 0 ? round((($cashTotal + $transferTotal) / $paymentSum) * 100) : 0;

        $chartLabels = $chartData->pluck('trx_date')->values();
        $chartValues = $chartData->pluck('total')->map(fn($v) => (float) $v)->values();
        $chartPaid = $chartData->pluck('paid_total')->map(fn($v) => (float) $v)->values();
        $chartTempo = $chartData->pluck('tempo_total')->map(fn($v) => (float) $v)->values();
    @endphp

    <div class="order-page">

        {{-- =========================================================
             HEADER
        ========================================================== --}}
        <div class="order-header">
            <div>
                <span class="order-eyebrow">Penjualan</span>
                <h1>Transaksi</h1>
                <p>Kelola dan pantau seluruh transaksi penjualan sparepart.</p>
            </div>
        </div>

        {{-- =========================================================
             FILTER
        ========================================================== --}}
        <form method="GET" class="filter-card" id="filterForm">
            <div class="filter-grid">
                <div class="form-group">
                    <label for="range">Periode</label>
                    <select id="range" name="range">
                        @foreach ($ranges as $value => $label)
                            <option value="{{ $value }}" @selected($range == $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_date">Dari tanggal</label>
                    <input type="date" id="start_date" name="start_date" value="{{ $start_date }}">
                </div>

                <div class="form-group">
                    <label for="end_date">Sampai tanggal</label>
                    <input type="date" id="end_date" name="end_date" value="{{ $end_date }}">
                </div>

                <button type="submit" class="btn-primary filter-submit">
                    <i data-lucide="filter"></i>
                    Terapkan
                </button>
            </div>
        </form>

        {{-- =========================================================
             RINGKASAN
        ========================================================== --}}
        @php
            $periodFrom = \Carbon\Carbon::parse($start_date)->translatedFormat('d F Y');
            $periodTo = \Carbon\Carbon::parse($end_date)->translatedFormat('d F Y');
            $netProfit = $summary['gross_profit'] - (float) ($totalExpense ?? 0);
        @endphp

        <div class="period-note">
            <i data-lucide="calendar-range"></i>
            <span>
                Periode <b>{{ $periodFrom }}</b> s/d <b>{{ $periodTo }}</b>
                · dihitung dari <b>tanggal transaksi</b> (DP/cicilan tempo dari tanggal uang diterima)
            </span>
        </div>

        <div class="summary-grid">
            <div class="summary-card is-highlight">
                <span class="summary-icon orange"><i data-lucide="wallet"></i></span>
                <div class="summary-content">
                    <small>Total Penjualan</small>
                    <h3>{{ $rp($summary['total_revenue']) }}</h3>
                </div>
            </div>

            <div class="summary-card">
                <span class="summary-icon green"><i data-lucide="hand-coins"></i></span>
                <div class="summary-content">
                    <small>Uang Masuk</small>
                    <h3>{{ $rp($summary['cash_in']) }}</h3>
                    <span class="summary-sub">Cash ·
                        Transfer{{ $summary['tempo_received'] > 0 ? ' · DP/cicilan ' . $rp($summary['tempo_received']) : '' }}</span>
                </div>
            </div>

            <div class="summary-card">
                <span class="summary-icon yellow"><i data-lucide="clock"></i></span>
                <div class="summary-content">
                    <small>Tempo Belum Dibayar</small>
                    <h3>{{ $rp($summary['tempo_unpaid']) }}</h3>
                    <span class="summary-sub">Dari nota tempo periode ini</span>
                </div>
            </div>

            <div class="summary-card">
                <span class="summary-icon green"><i data-lucide="badge-dollar-sign"></i></span>
                <div class="summary-content">
                    <small>Laba Bersih</small>
                    <h3>{{ $rp($netProfit) }}</h3>
                    <span class="summary-sub">Laba kotor − pengeluaran {{ $rp($totalExpense ?? 0) }}</span>
                    <span class="summary-sub">
                        Sudah diterima <b>{{ $rp($netProfit - $summary['profit_pending']) }}</b><br>
                        Masih di piutang <b style="color: #b7791f">{{ $rp($summary['profit_pending']) }}</b>
                    </span>
                </div>
            </div>

            <div class="summary-card">
                <span class="summary-icon slate"><i data-lucide="piggy-bank"></i></span>
                <div class="summary-content">
                    <small>Laba Kotor</small>
                    <h3>{{ $rp($summary['gross_profit']) }}</h3>
                    <span class="summary-sub">
                        @if ($summary['no_cost'] > 0)
                            {{ $num($summary['no_cost']) }} item belum ada harga beli
                        @else
                            Penjualan − modal
                            {{ $rp($summary['cogs']) }}{{ $summary['total_tax'] > 0 ? ' − pajak ' . $rp($summary['total_tax']) : '' }}
                        @endif
                    </span>
                </div>
            </div>

            <div class="summary-card">
                <span class="summary-icon orange"><i data-lucide="banknote"></i></span>
                <div class="summary-content">
                    <small>Cash</small>
                    <h3>{{ $rp($cashTotal) }}</h3>
                </div>
            </div>

            <div class="summary-card">
                <span class="summary-icon slate"><i data-lucide="credit-card"></i></span>
                <div class="summary-content">
                    <small>Transfer</small>
                    <h3>{{ $rp($transferTotal) }}</h3>
                </div>
            </div>

            <div class="summary-card">
                <span class="summary-icon green"><i data-lucide="receipt"></i></span>
                <div class="summary-content">
                    <small>Jumlah Transaksi</small>
                    <h3>{{ $num($summary['total_order']) }}</h3>
                    <span class="summary-sub">{{ $num($summary['total_item']) }} part terjual</span>
                </div>
            </div>
        </div>

        {{-- =========================================================
             GRAFIK
        ========================================================== --}}
        <div class="report-grid">
            <div class="report-card">
                <div class="card-header">
                    <div>
                        <h3>Grafik Pendapatan</h3>
                        <small>{{ $ranges[$range] ?? $range . ' hari' }} terakhir</small>
                    </div>
                    <div class="chart-legend">
                        <span><i class="dot cash"></i> Cash / Transfer</span>
                        <span><i class="dot tempo"></i> Tempo</span>
                    </div>
                </div>

                @if ($chartValues->sum() > 0)
                    {{-- tinggi dikunci → halaman tidak "loncat" saat grafik dimuat --}}
                    <div class="chart-container" data-order-chart="sales">
                        <canvas id="salesChart" aria-label="Grafik pendapatan harian" role="img"></canvas>
                    </div>
                @else
                    <div class="chart-empty">
                        <i data-lucide="chart-no-axes-column"></i>
                        Belum ada penjualan pada periode ini.
                    </div>
                @endif
            </div>

            <div class="report-card payment-report">
                <div class="card-header">
                    <div>
                        <h3>Metode Pembayaran</h3>
                        <small>Cash · Transfer · Tempo</small>
                    </div>
                    <span class="card-header-icon"><i data-lucide="wallet-cards"></i></span>
                </div>

                @if ($paymentSum > 0)
                    <div class="payment-chart-container" data-order-chart="payment">
                        <canvas id="paymentChart" aria-label="Grafik metode pembayaran" role="img"></canvas>
                        <div class="donut-center">
                            <strong>{{ $paidPct }}%</strong>
                            <span>Lunas</span>
                        </div>
                    </div>
                @else
                    <div class="chart-empty">
                        <i data-lucide="wallet-cards"></i>
                        Belum ada pembayaran.
                    </div>
                @endif

                <div class="payment-legend">
                    <div><span class="dot cash"></span> Cash <strong>{{ $rp($cashTotal) }}</strong></div>
                    <div><span class="dot transfer"></span> Transfer <strong>{{ $rp($transferTotal) }}</strong></div>
                    <div><span class="dot tempo"></span> Tempo <strong>{{ $rp($tempoTotal) }}</strong></div>
                </div>
            </div>
        </div>

        {{-- =========================================================
             RIWAYAT TRANSAKSI
             Laptop/tablet: tabel · HP: otomatis jadi kartu per transaksi
        ========================================================== --}}
        <div class="table-card">
            <div class="table-header">
                <h3>Riwayat Transaksi</h3>
                <div class="transaction-count">
                    <strong>{{ $num($orders->total()) }}</strong> transaksi
                </div>
            </div>

            <div class="table-responsive">
                <table class="table order-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice</th>
                            <th>Tanggal</th>
                            <th>Kasir</th>
                            <th>Pelanggan</th>
                            <th>Pembayaran</th>
                            <th>Item</th>
                            <th>Total</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $time = \Carbon\Carbon::parse($order->transaction_time);
                                $method = strtolower($order->payment_method);
                                $isCash = $method === 'cash';
                                $isCredit = $method === 'tempo';
                                $inv = $invoice($order->id);
                            @endphp
                            <tr>
                                <td class="row-number" data-label="#">{{ $orders->firstItem() + $loop->index }}</td>

                                <td class="cell-invoice" data-label="Invoice">
                                    <span class="invoice-number">{{ $inv }}</span>
                                    @if ($order->clock_suspect || $order->stock_conflict || $order->from_inactive_device)
                                        <span class="invoice-flag" title="{{ $order->sync_notes ?: 'Perlu dicek' }}"
                                            style="color:#b45309;font-size:12px;display:inline-flex;gap:3px;align-items:center">
                                            <i data-lucide="triangle-alert" style="width:13px;height:13px"></i> cek
                                        </span>
                                    @endif
                                </td>

                                <td class="cell-date" data-label="Tanggal">
                                    <div class="date-cell">
                                        <strong>{{ $time->translatedFormat('d M Y') }}</strong>
                                        <small>{{ $time->format('H:i') }}</small>
                                    </div>
                                </td>

                                <td data-label="Kasir">{{ $order->nama_kasir ?: '-' }}</td>

                                <td data-label="Pelanggan">
                                    <span class="customer-name">{{ $order->customer_name ?: 'Umum' }}</span>
                                </td>

                                <td data-label="Pembayaran">
                                    @if ($isCredit)
                                        <a href="{{ route('receivables.show', $order->id) }}"
                                            class="badge badge-transfer"
                                            title="{{ ($order->payment_status ?? 'unpaid') === 'paid' ? 'Tempo - lunas' : 'Tempo - belum lunas' }}"
                                            style="{{ ($order->payment_status ?? 'unpaid') === 'paid' ? '' : 'background:#fef7e6;color:#b7791f;border-color:#fcd34d' }}">
                                            <i data-lucide="hand-coins"></i>
                                            Tempo{{ ($order->payment_status ?? 'unpaid') === 'paid' ? ' · lunas' : '' }}
                                        </a>
                                    @else
                                        <span class="badge {{ $isCash ? 'badge-cash' : 'badge-transfer' }}">
                                            <i data-lucide="{{ $isCash ? 'banknote' : 'credit-card' }}"></i>
                                            {{ $isCash ? 'Cash' : 'Transfer' }}
                                        </span>
                                    @endif
                                </td>

                                <td data-label="Item">
                                    <span class="item-count">{{ $order->total_item }}</span>
                                </td>

                                <td class="cell-total" data-label="Total">
                                    <strong class="table-total">{{ $rp($order->total) }}</strong>
                                </td>

                                <td class="cell-actions" data-label="Aksi">
                                    <div class="row-actions">
                                        <button type="button" class="btn-icon btn-view-order"
                                            data-id="{{ $order->id }}" title="Lihat detail"
                                            aria-label="Lihat detail {{ $inv }}">
                                            <i data-lucide="eye"></i>
                                        </button>

                                        <a href="{{ route('orders.edit', $order->id) }}" class="btn-icon"
                                            title="Edit transaksi" aria-label="Edit {{ $inv }}">
                                            <i data-lucide="pencil"></i>
                                        </a>

                                        <form method="POST" action="{{ route('orders.destroy', $order->id) }}"
                                            class="delete-order-form" data-invoice="{{ $inv }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon btn-delete-order"
                                                title="Hapus transaksi" aria-label="Hapus {{ $inv }}">
                                                <i data-lucide="trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="9">
                                    <div class="order-empty">
                                        <span class="empty-icon"><i data-lucide="receipt"></i></span>
                                        <h4>Belum ada transaksi</h4>
                                        <p>Tidak ada transaksi pada periode yang dipilih.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="table-footer">
                    <div class="pagination-info">
                        Menampilkan <strong>{{ $orders->firstItem() }}</strong>–<strong>{{ $orders->lastItem() }}</strong>
                        dari <strong>{{ $num($orders->total()) }}</strong> transaksi
                    </div>
                    <div class="pagination-wrapper">
                        {{ $orders->links('vendor.pagination.custom') }}
                    </div>
                </div>
            @endif
        </div>

    </div>

    {{-- =============================================================
         MODAL DETAIL
    ============================================================== --}}
    <div class="order-modal" id="orderDetailModal" aria-hidden="true">
        <div class="order-modal-backdrop" data-close-modal></div>

        <div class="order-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">

            <div class="order-modal-header">
                <div>
                    <span class="order-eyebrow">Detail transaksi</span>
                    <h2 id="orderModalTitle">Detail Pesanan</h2>
                    <p id="modalInvoice">-</p>
                </div>
                <button type="button" class="order-modal-close" data-close-modal aria-label="Tutup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            {{-- loading --}}
            <div class="modal-state" id="modalLoading">
                <div class="modal-spinner"></div>
                <span>Memuat detail pesanan...</span>
            </div>

            {{-- error (terpisah dari loading supaya bisa dipakai ulang) --}}
            <div class="modal-state modal-error" id="modalError" hidden>
                <span class="modal-error-icon"><i data-lucide="circle-alert"></i></span>
                <h3>Gagal memuat transaksi</h3>
                <p id="modalErrorText">-</p>
                <button type="button" class="btn-primary" data-close-modal>Tutup</button>
            </div>

            <div class="order-modal-content" id="modalContent" hidden>
                <div class="order-info-grid">
                    <div class="order-info-item">
                        <span>Pelanggan</span>
                        <strong id="modalCustomer">-</strong>
                    </div>
                    <div class="order-info-item">
                        <span>Kasir</span>
                        <strong id="modalCashier">-</strong>
                    </div>
                    <div class="order-info-item">
                        <span>Waktu</span>
                        <strong id="modalDate">-</strong>
                    </div>
                    {{-- hanya tampil kalau ada isinya --}}
                    <div class="order-info-item" id="modalTableWrap" hidden>
                        <span>Keterangan</span>
                        <strong id="modalTable">-</strong>
                    </div>
                </div>

                <div class="modal-payment-box">
                    <div>
                        <span>Metode Pembayaran</span>
                        <strong id="modalPayment">-</strong>
                    </div>
                    <div class="modal-status">
                        <span>Status</span>
                        <strong id="modalStatus">Selesai</strong>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-header">
                        <h3>Rincian Part</h3>
                        <span id="modalTotalItem">0 item</span>
                    </div>

                    <div class="modal-items">
                        <table>
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Qty</th>
                                    <th>Harga</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody id="modalItemsBody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-total-box">
                    <div class="total-row"><span>Subtotal</span><strong id="modalSubtotal">Rp 0</strong></div>
                    <div class="total-row"><span>Pajak</span><strong id="modalTax">Rp 0</strong></div>
                    <div class="total-row" id="modalServiceRow"><span>Biaya Jasa</span><strong id="modalService">Rp
                            0</strong></div>
                    <div class="total-row" id="modalDiscountRow"><span>Diskon</span><strong id="modalDiscount">Rp
                            0</strong></div>
                    <div class="total-divider"></div>
                    <div class="total-row total-final"><span>Total</span><strong id="modalTotal">Rp 0</strong></div>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const $ = (id) => document.getElementById(id);
                const rupiah = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
                const icons = () => window.lucide?.createIcons();

                /* ======================================================
                   FILTER: pilih periode → kosongkan tanggal manual,
                   isi tanggal manual → periode diabaikan server
                ====================================================== */
                $('range')?.addEventListener('change', () => {
                    $('start_date').value = '';
                    $('end_date').value = '';
                });

                /* ======================================================
                   KONFIRMASI HAPUS (event delegation, satu listener)
                ====================================================== */
                document.addEventListener('submit', async (e) => {
                    const form = e.target.closest('.delete-order-form');
                    if (!form) return;
                    if (form.dataset.confirmed === '1') {
                        delete form.dataset.confirmed;
                        window.showLoading?.();
                        return;
                    }
                    e.preventDefault();

                    const ok = await window.appConfirm({
                        message: 'Hapus ' + (form.dataset.invoice || 'transaksi ini') + '? ' +
                            'Stok produk akan dikembalikan otomatis. Tindakan ini tidak bisa dibatalkan.',
                    });
                    if (!ok) return;
                    form.dataset.confirmed = '1';
                    form.requestSubmit();
                });

                /* ======================================================
                   MODAL DETAIL
                ====================================================== */
                const modal = $('orderDetailModal');
                let controller = null; // batalkan request lama kalau klik cepat berkali-kali
                const cache = new Map(); // detail yang sudah dibuka tidak diambil ulang

                const show = (state) => {
                    $('modalLoading').hidden = state !== 'loading';
                    $('modalError').hidden = state !== 'error';
                    $('modalContent').hidden = state !== 'content';
                };

                const openModal = () => {
                    modal.classList.add('show');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('modal-open');
                };

                const closeModal = () => {
                    controller?.abort();
                    modal.classList.remove('show');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('modal-open');
                };

                window.closeOrderModal = closeModal;

                const formatDate = (value) => {
                    if (!value) return '-';
                    const d = new Date(String(value).replace(' ', 'T'));
                    return isNaN(d) ? value : d.toLocaleString('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                };

                const render = ({
                    order,
                    items = []
                }) => {
                    $('modalInvoice').textContent = order.invoice || '-';
                    $('modalCustomer').textContent = order.customer_name || 'Umum';
                    $('modalCashier').textContent = order.nama_kasir || '-';
                    $('modalDate').textContent = formatDate(order.transaction_time);
                    $('modalPayment').textContent = order.payment_method || '-';
                    $('modalStatus').textContent = order.status || 'Selesai';

                    $('modalTableWrap').hidden = !order.table_number;
                    $('modalTable').textContent = order.table_number || '-';

                    // baris item dibuat lewat DOM (textContent) → aman dari XSS
                    const body = $('modalItemsBody');
                    body.replaceChildren();

                    if (!items.length) {
                        const tr = body.insertRow();
                        const td = tr.insertCell();
                        td.colSpan = 4;
                        td.className = 'modal-empty-items';
                        td.textContent = 'Tidak ada rincian item.';
                    }

                    items.forEach((item) => {
                        const tr = body.insertRow();
                        const name = document.createElement('strong');
                        name.textContent = item.product_name ?? '-';
                        tr.insertCell().append(name);
                        tr.insertCell().textContent = (Number(item.quantity) + ' ' + (item.unit_name || '')).trim();
                        tr.insertCell().textContent = rupiah(item.price);
                        const total = tr.insertCell();
                        total.className = 'text-right';
                        total.innerHTML = '<strong></strong>';
                        total.firstChild.textContent = rupiah(item.total);
                    });

                    const qty = items.reduce((t, i) => t + Number(i.quantity || 0), 0);
                    $('modalTotalItem').textContent = qty + ' item';

                    $('modalSubtotal').textContent = rupiah(order.sub_total);
                    $('modalTax').textContent = rupiah(order.tax);
                    $('modalService').textContent = rupiah(order.service_charge);
                    $('modalDiscount').textContent = '−' + rupiah(order.discount_amount);
                    $('modalTotal').textContent = rupiah(order.total);

                    // sembunyikan baris bernilai 0 supaya ringkas
                    $('modalServiceRow').hidden = !Number(order.service_charge);
                    $('modalDiscountRow').hidden = !Number(order.discount_amount);

                    // tanda dari sinkron HP: jam janggal / stok minus / HP bukan kasir aktif
                    let flags = document.getElementById('modalSyncFlags');
                    if (!flags) {
                        flags = document.createElement('div');
                        flags.id = 'modalSyncFlags';
                        flags.className = 'notice notice-warning';
                        flags.style.marginTop = '12px';
                        body.closest('table').after(flags);
                    }
                    const tags = [];
                    if (order.clock_suspect) tags.push('Jam HP janggal');
                    if (order.stock_conflict) tags.push('Stok server kurang (minus)');
                    if (order.from_inactive_device) tags.push('Dari HP yang sudah bukan kasir aktif');
                    flags.hidden = !tags.length;
                    flags.textContent = tags.length ? '⚠ ' + tags.join(' · ') + (order.sync_notes ? ' — ' + order.sync_notes : '') : '';

                    show('content');
                };

                const loadOrder = async (id) => {
                    openModal();

                    if (cache.has(id)) {
                        render(cache.get(id));
                        return;
                    }

                    show('loading');
                    $('modalInvoice').textContent = '-';
                    controller?.abort();
                    controller = new AbortController();

                    try {
                        const res = await fetch(@json(url('/orders')) + '/' + encodeURIComponent(id), {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            signal: controller.signal
                        });

                        if (!res.ok) throw new Error('Gagal mengambil detail transaksi.');

                        const result = await res.json();
                        if (result.status !== 'success') throw new Error('Data transaksi tidak valid.');

                        cache.set(id, result);
                        render(result);
                    } catch (err) {
                        if (err.name === 'AbortError') return;
                        $('modalErrorText').textContent = err.message;
                        show('error');
                    }
                };

                document.addEventListener('click', (e) => {
                    const view = e.target.closest('.btn-view-order');
                    if (view?.dataset.id) {
                        loadOrder(view.dataset.id);
                        return;
                    }
                    if (e.target.closest('[data-close-modal]')) closeModal();
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
                });

                /* ======================================================
                   GRAFIK — Chart.js baru diunduh saat grafik hampir
                   terlihat (lazy load), animasi dimatikan.
                ====================================================== */
                const CHART_SRC = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
                const COLORS = {
                    accent: '#FF6A1A',
                    ink: '#2A2F37',
                    tempo: '#FFB020',
                    grid: 'rgba(20,23,28,.06)',
                    muted: '#8A93A0'
                };

                const shortRp = (v) => {
                    const n = Math.abs(v);
                    if (n >= 1e9) return (v / 1e9).toFixed(1).replace('.', ',') + ' M';
                    if (n >= 1e6) return (v / 1e6).toFixed(1).replace('.', ',') + ' jt';
                    if (n >= 1e3) return Math.round(v / 1e3) + ' rb';
                    return v;
                };

                const charts = {
                    sales: () => new Chart($('salesChart'), {
                        type: 'bar',
                        data: {
                            labels: @json($chartLabels),
                            datasets: [{
                                label: 'Cash / Transfer',
                                data: @json($chartPaid),
                                backgroundColor: COLORS.accent,
                                hoverBackgroundColor: '#E2550A',
                                borderRadius: 4,
                                borderSkipped: false,
                                maxBarThickness: 36,
                                stack: 'sales'
                            }, {
                                label: 'Tempo (piutang)',
                                data: @json($chartTempo),
                                backgroundColor: COLORS.tempo,
                                hoverBackgroundColor: '#F09A00',
                                borderRadius: 4,
                                borderSkipped: false,
                                maxBarThickness: 36,
                                stack: 'sales'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    filter: (c) => c.parsed.y > 0,
                                    callbacks: {
                                        label: (c) => ' ' + c.dataset.label + ': ' + rupiah(c.parsed.y),
                                        footer: (items) => 'Total: ' + rupiah(items.reduce((s, i) => s + i
                                            .parsed.y, 0))
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true,
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: COLORS.muted,
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 8
                                    }
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    grid: {
                                        color: COLORS.grid
                                    },
                                    border: {
                                        display: false
                                    },
                                    ticks: {
                                        color: COLORS.muted,
                                        callback: shortRp
                                    }
                                }
                            }
                        }
                    }),

                    payment: () => new Chart($('paymentChart'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Cash', 'Transfer', 'Tempo'],
                            datasets: [{
                                data: [{{ $cashTotal }}, {{ $transferTotal }},
                                    {{ $tempoTotal }}],
                                backgroundColor: [COLORS.accent, COLORS.ink, COLORS.tempo],
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            cutout: '74%',
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (c) => c.label + ': ' + rupiah(c.parsed)
                                    }
                                }
                            }
                        }
                    })
                };

                let chartLoader;
                const loadChartJs = () => window.Chart ?
                    Promise.resolve() :
                    (chartLoader ??= new Promise((resolve, reject) => {
                        const s = document.createElement('script');
                        s.src = CHART_SRC;
                        s.async = true;
                        s.onload = resolve;
                        s.onerror = reject;
                        document.head.appendChild(s);
                    }));

                const boxes = document.querySelectorAll('[data-order-chart]');
                const draw = (box) => loadChartJs()
                    .then(() => charts[box.dataset.orderChart]())
                    .catch(() => box.insertAdjacentHTML('beforeend', '<p class="chart-error">Grafik gagal dimuat.</p>'));

                if (boxes.length) {
                    if ('IntersectionObserver' in window) {
                        const io = new IntersectionObserver((entries) => {
                            entries.forEach((en) => {
                                if (!en.isIntersecting) return;
                                io.unobserve(en.target);
                                draw(en.target);
                            });
                        }, {
                            rootMargin: '250px 0px'
                        });
                        boxes.forEach((b) => io.observe(b));
                    } else {
                        boxes.forEach(draw);
                    }
                }
            })();
        </script>
        <style>
            .period-note {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                margin: 0 0 14px;
                padding: 12px 16px;
                border-radius: 14px;
                background: #fff7ef;
                border: 1px solid #ffd9bf;
                color: #7a3a10;
                font-size: 13.5px;
                line-height: 1.5;
            }

            .period-note svg {
                flex: none;
                width: 18px;
                height: 18px;
                margin-top: 1px;
                color: #e2550a;
            }

            .summary-sub {
                display: block;
                margin-top: 4px;
                font-size: 12px;
                color: #6b7380;
                line-height: 1.35;
            }

            .chart-legend {
                display: flex;
                gap: 14px;
                flex-wrap: wrap;
                font-size: 12.5px;
                font-weight: 600;
                color: #4b5563;
            }

            .chart-legend span {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .chart-legend .dot {
                width: 10px;
                height: 10px;
                border-radius: 3px;
                display: inline-block;
            }

            .chart-legend .dot.cash {
                background: #FF6A1A;
            }

            .dot.tempo {
                background: #FFB020 !important;
            }
        </style>
    @endpush
@endsection
