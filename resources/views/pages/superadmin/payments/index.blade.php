@extends('layouts.app')

@section('title', 'Pembayaran Langganan')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $tabs = ['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'rejected' => 'Ditolak', 'all' => 'Semua'];
    @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Superadmin</span>
                <h1>Pembayaran Langganan</h1>
                <p>Cocokkan dengan mutasi rekening sebelum mengonfirmasi.</p>
            </div>
        </div>

        <div class="p-chips" style="margin-bottom:12px">
            @foreach ($tabs as $value => $label)
                <a href="{{ route('superadmin.payments.index', ['status' => $value]) }}"
                    class="p-chip {{ $status === $value ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert"><i data-lucide="circle-alert"></i><div><span>{{ $errors->first() }}</span></div></div>
        @endif

        @forelse ($payments as $p)
            <section class="p-card p-section">
                <div class="p-form-layout">
                    <div class="p-form-main">
                        <h3 style="margin:0 0 8px">
                            <a class="p-link" href="{{ route('superadmin.stores.show', $p->store_id) }}">{{ $storeNames[$p->store_id] ?? 'Toko #' . $p->store_id }}</a>
                            · {{ $p->plan->name ?? '' }} · {{ $rp($p->amount) }}
                        </h3>
                        <dl class="s-kv">
                            @if ($p->promo_code)
                                <dt>Kode promo</dt><dd><span class="s-badge tone-green">{{ $p->promo_code }}</span> harga {{ $rp($p->original_amount) }} − {{ $rp($p->discount_amount) }} = <b>{{ $rp($p->amount) }}</b></dd>
                            @endif
                            <dt>Pengirim</dt><dd>{{ $p->sender_name }} {{ $p->bank_name ? '(' . $p->bank_name . ')' : '' }}</dd>
                            <dt>Tanggal transfer</dt><dd>{{ $p->transferred_at?->translatedFormat('d M Y') }}</dd>
                            <dt>Dikirim</dt><dd>{{ $p->created_at->translatedFormat('d M Y, H:i') }}</dd>
                            @if ($p->note)<dt>Catatan toko</dt><dd>{{ $p->note }}</dd>@endif
                            @if ($p->status !== 'pending')
                                <dt>Diproses</dt><dd>{{ $p->reviewer->name ?? '-' }}, {{ $p->reviewed_at?->translatedFormat('d M Y H:i') }} {{ $p->review_note ? '— ' . $p->review_note : '' }}</dd>
                            @endif
                            @if ($p->period_end)<dt>Periode</dt><dd>{{ $p->period_start->translatedFormat('d M Y') }} → {{ $p->period_end->translatedFormat('d M Y') }}</dd>@endif
                        </dl>

                        @if ($p->status === 'pending')
                            <div class="s-actions-row" style="margin-top:12px">
                                <form method="POST" action="{{ route('superadmin.payments.confirm', $p) }}"
                                    data-confirm="Konfirmasi pembayaran {{ $rp($p->amount) }}? Masa aktif toko langsung diperpanjang.">
                                    @csrf
                                    <button class="p-btn p-btn-primary" type="submit"><i data-lucide="check"></i> Konfirmasi</button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.payments.reject', $p) }}" class="s-actions-row">
                                    @csrf
                                    <input type="text" name="note" required maxlength="500" placeholder="Alasan ditolak">
                                    <button class="p-btn p-btn-ghost" type="submit"><i data-lucide="x"></i> Tolak</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <aside class="p-form-side">
                        @if ($p->proof_url)
                            <a href="{{ $p->proof_url }}" target="_blank" rel="noopener">
                                <img class="s-proof" src="{{ $p->proof_url }}" alt="Bukti transfer" loading="lazy">
                            </a>
                        @else
                            <p class="p-hint">Tanpa bukti (dicatat superadmin).</p>
                        @endif
                    </aside>
                </div>
            </section>
        @empty
            <div class="p-card"><div class="p-empty"><span class="p-empty-icon"><i data-lucide="inbox"></i></span><h4>Tidak ada pembayaran</h4></div></div>
        @endforelse

        {{ $payments->links('vendor.pagination.custom') }}
    </div>
@endsection
