@extends('layouts.app')

@section('title', 'Ringkasan Platform')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $fmt = fn($d) => $d ? $d->translatedFormat('d M Y, H:i') : '-';
    @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Superadmin</span>
                <h1>Ringkasan Platform</h1>
                <p>{{ $total }} toko terdaftar.</p>
            </div>
        </div>

        <section class="p-stats" aria-label="Toko per status">
            @foreach (['pending', 'trial', 'active', 'grace', 'expired', 'suspended'] as $st)
                <a class="p-stat" href="{{ route('superadmin.stores.index', ['state' => $st]) }}" style="text-decoration:none">
                    <div class="p-stat-main">
                        <span class="p-stat-label">{{ $labels[$st] }}</span>
                        <strong class="p-stat-value">{{ $byState[$st] ?? 0 }}</strong>
                    </div>
                </a>
            @endforeach
        </section>

        <div class="p-form-layout">
            <div class="p-form-main">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="user-check"></i></span>
                        <div><h3>Menunggu persetujuan</h3><p>Trial 3 hari dimulai saat Anda menyetujui.</p></div>
                    </div>
                    @forelse ($pendingStores as $s)
                        <div class="p-toggle-row">
                            <div>
                                <strong><a href="{{ route('superadmin.stores.show', $s) }}" class="p-link">{{ $s->name }}</a></strong>
                                <small>{{ $s->owner->name ?? '-' }} · {{ $s->owner->email ?? '' }} · {{ $s->owner->phone_number ?? '' }} · daftar {{ $fmt($s->created_at) }}</small>
                            </div>
                            <form method="POST" action="{{ route('superadmin.stores.approve', $s) }}"
                                data-confirm="Setujui {{ $s->name }}? Trial langsung dimulai.">
                                @csrf
                                <button class="p-btn p-btn-primary" type="submit"><i data-lucide="check"></i> Setujui</button>
                            </form>
                        </div>
                    @empty
                        <p class="p-hint">Tidak ada pendaftaran baru.</p>
                    @endforelse
                </section>

                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="calendar-clock"></i></span>
                        <div><h3>Berakhir dalam 7 hari</h3><p>Ingatkan toko untuk memperpanjang.</p></div>
                    </div>
                    @forelse ($endingSoon as $s)
                        <div class="p-toggle-row">
                            <div>
                                <strong><a href="{{ route('superadmin.stores.show', $s) }}" class="p-link">{{ $s->name }}</a></strong>
                                <small>{{ $s->owner->phone_number ?? '' }} · berakhir {{ $fmt($s->subscription()->endsAt()) }}</small>
                            </div>
                            @include('pages.superadmin._state', ['state' => $s->subscription()->state()])
                        </div>
                    @empty
                        <p class="p-hint">Tidak ada.</p>
                    @endforelse
                </section>
            </div>

            <aside class="p-form-side">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="banknote"></i></span>
                        <div><h3>Pembayaran menunggu</h3></div>
                    </div>
                    @forelse ($pendingPayments as $p)
                        <div class="p-toggle-row">
                            <div>
                                <strong>{{ $storeNames[$p->store_id] ?? '-' }}</strong>
                                <small>{{ $p->plan->name ?? '' }} · {{ $rp($p->amount) }} · {{ $p->sender_name }}</small>
                            </div>
                            <a href="{{ route('superadmin.payments.index') }}" class="p-btn p-btn-ghost">Periksa</a>
                        </div>
                    @empty
                        <p class="p-hint">Tidak ada.</p>
                    @endforelse
                </section>

                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="clock-alert"></i></span>
                        <div><h3>Jam HP janggal terbaru</h3></div>
                    </div>
                    @forelse ($recentTamper as $t)
                        <div class="p-hint">
                            <a href="{{ route('superadmin.stores.show', $t->store_id) }}" class="p-link">{{ $storeNames[$t->store_id] ?? '#' . $t->store_id }}</a>
                            — {{ \App\Models\ClockTamperLog::KINDS[$t->kind] ?? $t->kind }}
                            · {{ $fmt($t->created_at) }}
                        </div>
                    @empty
                        <p class="p-hint">Belum ada.</p>
                    @endforelse
                    <a href="{{ route('superadmin.tamper.index') }}" class="p-link">Lihat semua</a>
                </section>
            </aside>
        </div>
    </div>
@endsection
