{{-- =========================================================
     PENGINGAT HUTANG SUPPLIER
     Hanya tampil kalau ada nota tempo yang lewat / segera jatuh tempo.
========================================================= --}}
@php
    $pay = $payables ?? \App\Support\PayableAlerts::summary();
    $hasOverdue = $pay->overdue_count > 0;
    $listUrl = Route::has('stock-ins.index')
        ? route('stock-ins.index', ['payment' => 'unpaid', 'start_date' => '2000-01-01', 'end_date' => now()->toDateString()])
        : '#';
@endphp

@if ($pay->count > 0)
    <section class="payable-alert {{ $hasOverdue ? 'is-danger' : 'is-warning' }}" aria-labelledby="payable-title">
        <div class="payable-head">
            <span class="payable-icon"><i data-lucide="{{ $hasOverdue ? 'siren' : 'bell-ring' }}"></i></span>

            <div class="payable-title">
                <h2 id="payable-title">
                    @if ($hasOverdue)
                        {{ $pay->overdue_count }} nota supplier lewat jatuh tempo
                    @else
                        {{ $pay->soon_count }} nota supplier segera jatuh tempo
                    @endif
                </h2>
                <p>
                    @if ($hasOverdue)
                        Belum dibayar {{ $rp($pay->overdue_total) }}.
                        @if ($pay->soon_count > 0)
                            Ditambah {{ $pay->soon_count }} nota ({{ $rp($pay->soon_total) }}) jatuh tempo dalam
                            {{ \App\Support\PayableAlerts::SOON_DAYS }} hari.
                        @endif
                    @else
                        Siapkan {{ $rp($pay->soon_total) }} dalam {{ \App\Support\PayableAlerts::SOON_DAYS }} hari ke depan.
                    @endif
                </p>
            </div>

            <a href="{{ $listUrl }}" class="payable-link">
                Lihat & tandai lunas <i data-lucide="arrow-right"></i>
            </a>
        </div>

        <ul class="payable-list">
            @foreach ($pay->items->take(4) as $item)
                <li>
                    <div class="payable-supplier">
                        <strong>{{ $item->supplier_name }}</strong>
                        <small>{{ $item->invoice_number ?: 'Nota #' . $item->id }} · jatuh tempo {{ $item->due->translatedFormat('d M Y') }}</small>
                    </div>

                    <span class="payable-due {{ $item->is_overdue ? 'is-late' : '' }}">
                        @if ($item->is_overdue)
                            Telat {{ abs($item->days_left) }} hari
                        @elseif ($item->days_left === 0)
                            Hari ini
                        @else
                            {{ $item->days_left }} hari lagi
                        @endif
                    </span>

                    <strong class="payable-amount">{{ $rp($item->total) }}</strong>
                </li>
            @endforeach
        </ul>

        @if ($pay->count > 4)
            <a href="{{ $listUrl }}" class="payable-more">+ {{ $pay->count - 4 }} nota lainnya</a>
        @endif
    </section>
@endif
