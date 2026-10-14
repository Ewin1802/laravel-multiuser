@extends('layouts.app')

@section('title', 'Piutang Pelanggan')

@section('content')
    @php
        $rp = fn ($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $invoice = fn ($id) => 'INV' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $today = now()->startOfDay();
        $chipUrl = fn ($value) => route('receivables.index', array_filter(['status' => $value, 'q' => $search]));
        $chips = ['open' => 'Belum lunas', 'overdue' => 'Lewat jatuh tempo', 'paid' => 'Lunas', 'all' => 'Semua'];
    @endphp

    <div class="crud-page">

        <div class="p-header">
            <div>
                <span class="p-eyebrow">Keuangan</span>
                <h1>Piutang Pelanggan</h1>
                <p>Nota penjualan tempo dari kasir — pantau sisa tagihan dan catat pelunasan.</p>
            </div>
        </div>

        {{-- RINGKASAN --}}
        <section class="p-stats" aria-label="Ringkasan piutang">
            <div class="p-stat is-total">
                <div class="p-stat-main">
                    <span class="p-stat-label"><i data-lucide="hand-coins"></i> Total piutang belum tertagih</span>
                    <strong class="p-stat-value">{{ $rp($summary->outstanding) }}</strong>
                    <span class="p-stat-sub">
                        <i data-lucide="users"></i>
                        {{ $summary->open_count }} nota · {{ $summary->customers }} pelanggan
                    </span>
                </div>
            </div>

            <a href="{{ $chipUrl('overdue') }}" class="p-stat is-half {{ $summary->overdue_count > 0 ? 'tone-red' : 'tone-green' }}">
                <span class="p-stat-icon"><i data-lucide="alarm-clock"></i></span>
                <span class="p-stat-label">Lewat jatuh tempo</span>
                <strong class="p-stat-value">{{ $rp($summary->overdue_amount) }}</strong>
                <span class="p-stat-sub">
                    {{ $summary->overdue_count > 0 ? $summary->overdue_count . ' nota perlu ditagih' : 'Tidak ada yang telat' }}
                </span>
            </a>

            <div class="p-stat tone-green is-half">
                <span class="p-stat-icon"><i data-lucide="banknote"></i></span>
                <span class="p-stat-label">Diterima bulan ini</span>
                <strong class="p-stat-value">{{ $rp($summary->collected_month) }}</strong>
                <span class="p-stat-sub">Pelunasan & cicilan dari kasir maupun web</span>
            </div>
        </section>

        {{-- FILTER --}}
        <section class="p-card p-filter-card" aria-label="Filter piutang">
            <form method="GET" action="{{ route('receivables.index') }}" class="p-toolbar" role="search">
                <input type="hidden" name="status" value="{{ $status }}">
                <label class="p-search">
                    <i data-lucide="search"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama / no. HP / no. nota..."
                        autocomplete="off" aria-label="Cari piutang">
                </label>
                <button type="submit" class="p-btn p-btn-dark">Cari</button>
                @if ($search)
                    <a href="{{ route('receivables.index', ['status' => $status]) }}" class="p-btn p-btn-ghost">Reset</a>
                @endif
            </form>

            <nav class="p-chips" aria-label="Status piutang">
                @foreach ($chips as $value => $label)
                    <a href="{{ $chipUrl($value) }}" class="p-chip {{ $status === $value ? 'is-active' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </section>

        <div class="rc-layout">
            {{-- DAFTAR NOTA --}}
            <div class="p-card">
                <div class="p-table-wrap">
                    <table class="p-table">
                        <thead>
                            <tr>
                                <th>Pelanggan</th>
                                <th>Jatuh tempo</th>
                                <th class="text-end">Total</th>
                                <th>Terbayar</th>
                                <th class="text-end">Sisa</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                @php
                                    $due = $order->due_date ? \Carbon\Carbon::parse($order->due_date)->startOfDay() : null;
                                    $isPaid = $order->payment_status === 'paid';
                                    $daysLeft = $due ? (int) round($today->diffInDays($due, false)) : null;
                                    $pct = $order->total > 0 ? min(100, round($order->paid_amount / $order->total * 100)) : 0;
                                    $trx = \Carbon\Carbon::parse($order->transaction_time);
                                @endphp
                                <tr>
                                    <td class="cell-main">
                                        <div class="p-item">
                                            <div class="p-thumb has-tone tone-orange"><i data-lucide="user-round"></i></div>
                                            <div class="p-item-text">
                                                <a href="{{ route('receivables.show', $order->id) }}" class="p-name p-name-link">
                                                    {{ $order->customer_name ?: 'Tanpa nama' }}
                                                </a>
                                                <small class="p-desc">
                                                    {{ $invoice($order->id) }} · {{ $trx->translatedFormat('d M Y') }}
                                                    @if ($order->customer_phone) · {{ $order->customer_phone }} @endif
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td data-label="Jatuh tempo">
                                        @if ($isPaid)
                                            <span class="p-status is-on">Lunas</span>
                                        @elseif (! $due)
                                            <span class="p-stock is-mid">Tanpa tempo</span>
                                        @elseif ($daysLeft < 0)
                                            <span class="p-stock is-empty">Telat {{ abs($daysLeft) }} hari</span>
                                        @elseif ($daysLeft === 0)
                                            <span class="p-stock is-low">Hari ini</span>
                                        @else
                                            <span class="p-stock {{ $daysLeft <= 3 ? 'is-low' : 'is-ok' }}">{{ $due->translatedFormat('d M') }}</span>
                                        @endif
                                    </td>

                                    <td class="text-end" data-label="Total">{{ $rp($order->total) }}</td>

                                    <td data-label="Terbayar">
                                        <div class="rc-progress" title="{{ $pct }}%">
                                            <span style="width: {{ $pct }}%"></span>
                                        </div>
                                        <small class="p-sub">{{ $rp($order->paid_amount) }}</small>
                                    </td>

                                    <td class="text-end" data-label="Sisa">
                                        <strong class="p-price {{ $isPaid ? '' : 'is-out' }}">{{ $rp($order->remaining_amount) }}</strong>
                                    </td>

                                    <td class="cell-actions">
                                        <div class="p-actions">
                                            @unless ($isPaid)
                                                <button type="button" class="p-icon-btn is-success" title="Terima pembayaran"
                                                    aria-label="Terima pembayaran {{ $order->customer_name }}"
                                                    data-rc-pay
                                                    data-url="{{ route('receivables.payments.store', $order->id) }}"
                                                    data-customer="{{ $order->customer_name ?: 'Tanpa nama' }}"
                                                    data-invoice="{{ $invoice($order->id) }}"
                                                    data-total="{{ $order->total }}"
                                                    data-paid="{{ $order->paid_amount }}"
                                                    data-remaining="{{ $order->remaining_amount }}">
                                                    <i data-lucide="hand-coins"></i>
                                                </button>
                                            @endunless
                                            <a href="{{ route('receivables.show', $order->id) }}" class="p-icon-btn" title="Detail">
                                                <i data-lucide="eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="p-empty-row">
                                    <td colspan="6">
                                        <div class="p-empty">
                                            <span class="p-empty-icon"><i data-lucide="hand-coins"></i></span>
                                            <h4>{{ $status === 'paid' ? 'Belum ada nota tempo yang lunas' : 'Tidak ada piutang' }}</h4>
                                            <p>Nota tempo dibuat dari aplikasi kasir dengan metode bayar <strong>Tempo</strong>.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($orders->hasPages())
                    <div class="p-footer">
                        <span class="p-page-info">
                            Menampilkan <strong>{{ $orders->firstItem() }}</strong>–<strong>{{ $orders->lastItem() }}</strong>
                            dari <strong>{{ $orders->total() }}</strong>
                        </span>
                        {{ $orders->links('vendor.pagination.custom') }}
                    </div>
                @endif
            </div>

            {{-- PELANGGAN TERATAS --}}
            <aside class="p-card rc-side">
                <div class="p-section-head">
                    <span class="p-section-icon"><i data-lucide="users"></i></span>
                    <div>
                        <h3>Piutang terbesar</h3>
                        <p>Per pelanggan, nota belum lunas.</p>
                    </div>
                </div>
                @forelse ($topCustomers as $c)
                    <a class="rc-customer" href="{{ route('receivables.index', ['status' => 'open', 'q' => $c->customer_name]) }}">
                        <span class="rc-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($c->customer_name ?: '?', 0, 1)) }}</span>
                        <span class="rc-customer-text">
                            <strong>{{ $c->customer_name ?: 'Tanpa nama' }}</strong>
                            <small>{{ $c->notes }} nota{{ $c->customer_phone ? ' · ' . $c->customer_phone : '' }}</small>
                        </span>
                        <strong class="rc-customer-amount">{{ $rp($c->remaining) }}</strong>
                    </a>
                @empty
                    <p class="p-hint">Belum ada piutang terbuka.</p>
                @endforelse
            </aside>
        </div>
    </div>

    @include('pages.receivables._pay-modal')

    <style>
        .crud-page .rc-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 18px; align-items: start; }
        .crud-page .rc-side { padding: 18px; display: grid; gap: 10px; }
        .crud-page .rc-progress { width: 110px; height: 6px; border-radius: 99px; background: var(--pl, #eef0f3); overflow: hidden; margin-bottom: 4px; }
        .crud-page .rc-progress span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #22c55e, #16a34a); }
        .crud-page .rc-customer { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 14px; border: 1px solid var(--pl, #eef0f3); color: inherit; text-decoration: none; transition: border-color .15s, background .15s; }
        .crud-page .rc-customer:hover { border-color: var(--pa, #ff6a1a); background: var(--pas, #fff1e8); }
        .crud-page .rc-avatar { flex: none; width: 34px; height: 34px; border-radius: 11px; display: grid; place-items: center; font-weight: 800; color: #fff; background: linear-gradient(135deg, #ff6a1a, #ffae1f); }
        .crud-page .rc-customer-text { flex: 1; min-width: 0; display: grid; }
        .crud-page .rc-customer-text strong { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .crud-page .rc-customer-text small { color: var(--pm, #667080); font-size: 12px; }
        .crud-page .rc-customer-amount { white-space: nowrap; font-size: 13px; color: var(--pad, #e2550a); }
        @media (max-width: 1100px) { .crud-page .rc-layout { grid-template-columns: 1fr; } }
    </style>
@endsection
