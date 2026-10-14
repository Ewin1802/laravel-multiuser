@extends('layouts.app')

@section('title', 'Detail Piutang')

@section('content')
    @php
        $rp = fn ($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $qty = fn ($n) => rtrim(rtrim(number_format((float) ($n ?? 0), 2, ',', '.'), '0'), ',');
        $inv = 'INV' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
        $trx = \Carbon\Carbon::parse($order->transaction_time);
        $due = $order->due_date ? \Carbon\Carbon::parse($order->due_date)->startOfDay() : null;
        $isPaid = $order->payment_status === 'paid';
        $daysLeft = $due ? (int) round(now()->startOfDay()->diffInDays($due, false)) : null;
        $pct = $order->total > 0 ? min(100, round($order->paid_amount / $order->total * 100)) : 0;
        $waPhone = $order->customer_phone ? preg_replace('/^0/', '62', preg_replace('/\D/', '', $order->customer_phone)) : null;
        $waText = rawurlencode(
            "Halo {$order->customer_name}, kami dari toko ingin mengingatkan tagihan nota {$inv} "
            . 'sebesar ' . 'Rp ' . number_format($order->remaining_amount, 0, ',', '.')
            . ($due ? ' yang jatuh tempo ' . $due->translatedFormat('d F Y') : '') . '. Terima kasih.'
        );
    @endphp

    <div class="crud-page">

        <div class="p-header">
            <div>
                <a href="{{ route('receivables.index') }}" class="p-back">
                    <i data-lucide="arrow-left"></i> Kembali ke piutang
                </a>
                <h1>{{ $order->customer_name ?: 'Tanpa nama' }}</h1>
                <p>{{ $inv }} · {{ $trx->translatedFormat('l, d F Y H:i') }} · kasir {{ $order->nama_kasir ?: '-' }}</p>
            </div>

            <div class="p-header-actions">
                @if ($waPhone && ! $isPaid)
                    <a href="https://wa.me/{{ $waPhone }}?text={{ $waText }}" target="_blank" rel="noopener" class="p-btn p-btn-ghost">
                        <i data-lucide="message-circle"></i> Ingatkan via WA
                    </a>
                @endif
                @unless ($isPaid)
                    <button type="button" class="p-btn p-btn-primary"
                        data-rc-pay
                        data-url="{{ route('receivables.payments.store', $order->id) }}"
                        data-customer="{{ $order->customer_name ?: 'Tanpa nama' }}"
                        data-invoice="{{ $inv }}"
                        data-total="{{ $order->total }}"
                        data-paid="{{ $order->paid_amount }}"
                        data-remaining="{{ $order->remaining_amount }}">
                        <i data-lucide="hand-coins"></i> Terima Pembayaran
                    </button>
                @endunless
            </div>
        </div>

        <div class="p-form-layout">
            <div class="p-form-main">

                {{-- RIWAYAT PEMBAYARAN --}}
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="history"></i></span>
                        <div>
                            <h3>Riwayat Pembayaran</h3>
                            <p>Uang muka, cicilan, dan pelunasan.</p>
                        </div>
                    </div>

                    @forelse ($order->payments as $payment)
                        <div class="rc-pay-row">
                            <span class="rc-pay-icon {{ $payment->payment_method === 'Transfer' ? 'is-transfer' : '' }}">
                                <i data-lucide="{{ $payment->payment_method === 'Transfer' ? 'credit-card' : 'banknote' }}"></i>
                            </span>
                            <div class="rc-pay-text">
                                <strong>{{ $rp($payment->amount) }}</strong>
                                <small>
                                    {{ $payment->paid_at?->translatedFormat('d M Y H:i') }} · {{ $payment->payment_method }}
                                    · {{ $payment->source === 'web' ? 'dicatat di web' : 'dari kasir' }}
                                    @if ($payment->nama_kasir) ({{ $payment->nama_kasir }}) @endif
                                </small>
                                @if ($payment->note)
                                    <small class="rc-pay-note">{{ $payment->note }}</small>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('receivables.payments.destroy', [$order->id, $payment->id]) }}"
                                data-confirm="Hapus pembayaran {{ $rp($payment->amount) }}? Sisa piutang akan dihitung ulang.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-icon-btn is-danger" title="Hapus pembayaran">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="p-empty">
                            <span class="p-empty-icon"><i data-lucide="wallet"></i></span>
                            <h4>Belum ada pembayaran</h4>
                            <p>Nota ini dibuat tanpa uang muka.</p>
                        </div>
                    @endforelse
                </section>

                {{-- BARANG --}}
                <section class="p-card">
                    <div class="p-table-wrap">
                        <table class="p-table p-table-simple">
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->orderItems as $item)
                                    <tr>
                                        <td class="cell-main"><strong>{{ $item->product_name }}</strong></td>
                                        <td class="text-center">{{ $qty($item->quantity) }}</td>
                                        <td class="text-end">{{ $rp($item->price) }}</td>
                                        <td class="text-end">{{ $rp($item->price * $item->quantity) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="p-form-side">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="hand-coins"></i></span>
                        <div>
                            <h3>Status Tagihan</h3>
                            <p>
                                @if ($isPaid)
                                    Lunas{{ $order->paid_off_at ? ' · ' . $order->paid_off_at->translatedFormat('d M Y') : '' }}
                                @elseif (! $due)
                                    Tanpa jatuh tempo
                                @elseif ($daysLeft < 0)
                                    Lewat jatuh tempo {{ abs($daysLeft) }} hari
                                @else
                                    Jatuh tempo {{ $due->translatedFormat('d M Y') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <dl class="p-sim">
                        <div><dt>Total nota</dt><dd>{{ $rp($order->total) }}</dd></div>
                        <div><dt>Sudah dibayar</dt><dd>{{ $rp($order->paid_amount) }}</dd></div>
                        <div class="is-total"><dt>Sisa</dt><dd>{{ $rp($order->remaining_amount) }}</dd></div>
                    </dl>

                    <div class="rc-progress-lg"><span style="width: {{ $pct }}%"></span></div>
                    <small class="p-hint">{{ $pct }}% terbayar</small>

                    @if ($order->customer_phone)
                        <p class="p-send-note is-off" style="margin-top:12px">
                            <i data-lucide="phone"></i> {{ $order->customer_phone }}
                        </p>
                    @endif
                </section>
            </aside>
        </div>
    </div>

    @include('pages.receivables._pay-modal')

    <style>
        .crud-page .rc-pay-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-top: 1px solid var(--pl, #eef0f3); }
        .crud-page .rc-pay-row:first-of-type { border-top: 0; }
        .crud-page .rc-pay-icon { flex: none; width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; background: var(--pgs, #ecfdf3); color: var(--pg, #16a34a); }
        .crud-page .rc-pay-icon.is-transfer { background: var(--pas, #fff1e8); color: var(--pad, #e2550a); }
        .crud-page .rc-pay-icon svg { width: 18px; height: 18px; }
        .crud-page .rc-pay-text { flex: 1; min-width: 0; display: grid; gap: 2px; }
        .crud-page .rc-pay-text small { color: var(--pm, #667080); font-size: 12px; }
        .crud-page .rc-pay-note { font-style: italic; }
        .crud-page .rc-progress-lg { height: 10px; border-radius: 99px; background: var(--pl, #eef0f3); overflow: hidden; margin: 14px 0 6px; }
        .crud-page .rc-progress-lg span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #22c55e, #16a34a); }
    </style>
@endsection
