@extends('layouts.app')

@section('title', 'Langganan')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $state = $sub->state();
        $tone = [
            'trial' => 'tone-blue', 'active' => 'tone-green', 'grace' => 'tone-amber',
            'expired' => 'tone-red', 'suspended' => 'tone-red', 'pending' => 'tone-gray',
        ][$state] ?? 'tone-gray';
        $fmt = fn($d) => $d ? $d->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') : '-';
        $statusLabel = ['pending' => 'Menunggu konfirmasi', 'confirmed' => 'Dikonfirmasi', 'rejected' => 'Ditolak'];
        $statusTone = ['pending' => 'tone-amber', 'confirmed' => 'tone-green', 'rejected' => 'tone-red'];
    @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Toko</span>
                <h1>Langganan</h1>
                <p>Masa aktif dihitung dengan jam server. Perpanjang dengan transfer, lalu kirim buktinya di sini.</p>
            </div>
        </div>

        <div class="p-form-layout">
            <div class="p-form-main">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="shield-check"></i></span>
                        <div>
                            <h3>Status <span class="s-badge {{ $tone }}">{{ $sub->label() }}</span></h3>
                            <p>
                                @switch($state)
                                    @case('trial') Masa uji coba berakhir {{ $fmt($store->trial_ends_at) }}. @break
                                    @case('active') Aktif sampai {{ $fmt($store->paid_until) }}. @break
                                    @case('grace') Langganan berakhir {{ $fmt($store->paid_until) }}. Kasir masih bisa transaksi sampai {{ $fmt($sub->graceEndsAt()) }}. @break
                                    @case('expired') Masa aktif habis. Data hanya bisa dilihat; kasir tidak bisa membuat transaksi baru. @break
                                    @case('suspended') Toko diblokir admin{{ $store->suspend_reason ? ': ' . $store->suspend_reason : '' }}. @break
                                    @default Menunggu persetujuan admin.
                                @endswitch
                            </p>
                        </div>
                    </div>

                    <dl class="s-kv">
                        <dt>Disetujui</dt><dd>{{ $fmt($store->approved_at) }}</dd>
                        <dt>Trial berakhir</dt><dd>{{ $fmt($store->trial_ends_at) }}</dd>
                        <dt>Berbayar sampai</dt><dd>{{ $fmt($store->paid_until) }}</dd>
                        @if ($sub->daysLeft() !== null)
                            <dt>Sisa</dt><dd>{{ $sub->daysLeft() }} hari</dd>
                        @endif
                    </dl>
                </section>

                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="send"></i></span>
                        <div>
                            <h3>Perpanjang</h3>
                            <p>Transfer sesuai harga paket, lalu kirim bukti. Bila masih trial atau aktif, periode baru disambung dari tanggal berakhir — hari Anda tidak hangus.</p>
                        </div>
                    </div>

                    @if ($instructions)
                        <div class="p-notice is-warning" role="note">
                            <i data-lucide="landmark"></i>
                            <div style="white-space:pre-line">{{ $instructions }}</div>
                        </div>
                    @endif

                    @if ($hasPending)
                        <div class="p-notice is-warning" role="status">
                            <i data-lucide="clock"></i>
                            <div><strong>Pembayaran sedang diperiksa.</strong> <span>Anda bisa mengirim lagi setelah yang ini diproses.</span></div>
                        </div>
                    @elseif ($plans->isEmpty())
                        <p class="p-hint">Belum ada paket aktif. Hubungi admin.</p>
                    @else
                        @if ($errors->any())
                            <div class="p-notice is-danger" role="alert">
                                <i data-lucide="circle-alert"></i>
                                <div><span>{{ $errors->first() }}</span></div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('billing.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="s-plans" role="radiogroup" aria-label="Pilih paket">
                                @foreach ($plans as $plan)
                                    <label class="s-plan">
                                        <input type="radio" name="plan_id" value="{{ $plan->id }}" required
                                            @checked(old('plan_id', $plans->first()->id) == $plan->id)>
                                        <strong>{{ $plan->name }}</strong>
                                        <span class="s-price">{{ $rp($plan->price) }}</span>
                                        <small>{{ $plan->duration_months }} bulan</small>
                                    </label>
                                @endforeach
                            </div>

                            <div class="p-grid" style="margin-top:14px">
                                <div class="p-field">
                                    <label for="sender_name">Nama pengirim <span class="req">*</span></label>
                                    <input type="text" id="sender_name" name="sender_name" required maxlength="100"
                                        value="{{ old('sender_name') }}" placeholder="Sesuai rekening pengirim">
                                </div>
                                <div class="p-field">
                                    <label for="bank_name">Bank pengirim</label>
                                    <input type="text" id="bank_name" name="bank_name" maxlength="50"
                                        value="{{ old('bank_name') }}" placeholder="BCA / BRI / DANA ...">
                                </div>
                                <div class="p-field">
                                    <label for="transferred_at">Tanggal transfer <span class="req">*</span></label>
                                    <input type="date" id="transferred_at" name="transferred_at" required
                                        max="{{ now()->toDateString() }}" value="{{ old('transferred_at', now()->toDateString()) }}">
                                </div>
                                <div class="p-field">
                                    <label for="proof">Foto bukti transfer <span class="req">*</span></label>
                                    <input type="file" id="proof" name="proof" required accept="image/jpeg,image/png">
                                </div>
                                <div class="p-field p-span-2">
                                    <label for="note">Catatan</label>
                                    <input type="text" id="note" name="note" maxlength="500" value="{{ old('note') }}"
                                        placeholder="Opsional">
                                </div>
                            </div>

                            <div class="p-form-actions">
                                <button type="submit" class="p-btn p-btn-primary">
                                    <i data-lucide="send"></i> Kirim Bukti Transfer
                                </button>
                            </div>
                        </form>
                    @endif
                </section>
            </div>

            <aside class="p-form-side">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="history"></i></span>
                        <div><h3>Riwayat Pembayaran</h3></div>
                    </div>

                    @forelse ($payments as $p)
                        <div class="p-toggle-row">
                            <div>
                                <strong>{{ $p->plan->name ?? 'Paket' }} · {{ $rp($p->amount) }}</strong>
                                <small>
                                    Transfer {{ $p->transferred_at?->translatedFormat('d M Y') ?? '-' }}
                                    @if ($p->period_end) · aktif s/d {{ $p->period_end->translatedFormat('d M Y') }} @endif
                                    @if ($p->review_note) · {{ $p->review_note }} @endif
                                </small>
                            </div>
                            <span class="s-badge {{ $statusTone[$p->status] ?? 'tone-gray' }}">{{ $statusLabel[$p->status] ?? $p->status }}</span>
                        </div>
                    @empty
                        <p class="p-hint">Belum ada pembayaran.</p>
                    @endforelse

                    {{ $payments->links('vendor.pagination.custom') }}
                </section>
            </aside>
        </div>
    </div>
@endsection
