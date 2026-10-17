@extends('layouts.app')

@section('title', $store->name)

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $fmt = fn($d) => $d ? \Carbon\Carbon::parse($d)->translatedFormat('d M Y, H:i') : '-';
        $state = $sub->state();
        $kinds = \App\Models\ClockTamperLog::KINDS;
        $payStatus = ['pending' => ['Menunggu', 'tone-amber'], 'confirmed' => ['Dikonfirmasi', 'tone-green'], 'rejected' => ['Ditolak', 'tone-red']];
    @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <a href="{{ route('superadmin.stores.index') }}" class="p-back"><i data-lucide="arrow-left"></i> Semua toko</a>
                <h1>{{ $store->name }} @include('pages.superadmin._state', ['state' => $state])
                    @if ($store->feature_draft_order)
                        <span class="s-badge tone-cafe" title="Draft order (cafe) diaktifkan pemilik toko"><i data-lucide="coffee"></i> Cafe · draft order</span>
                    @endif
                </h1>
                <p>{{ $store->owner->name ?? '-' }} · {{ $store->owner->email ?? '' }} · {{ $store->owner->phone_number ?? '' }}</p>
            </div>
            <div class="s-actions-row">
                @if ($store->status === 'pending')
                    <form method="POST" action="{{ route('superadmin.stores.approve', $store) }}"
                        data-confirm="Setujui {{ $store->name }}? Trial langsung dimulai.">
                        @csrf
                        <button class="p-btn p-btn-primary" type="submit"><i data-lucide="check"></i> Setujui</button>
                    </form>
                @elseif ($store->status === 'suspended')
                    <form method="POST" action="{{ route('superadmin.stores.unsuspend', $store) }}">
                        @csrf
                        <button class="p-btn p-btn-primary" type="submit"><i data-lucide="unlock"></i> Buka blokir</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('superadmin.stores.suspend', $store) }}"
                        data-confirm="Blokir {{ $store->name }}? Toko menjadi hanya-lihat.">
                        @csrf
                        <input type="hidden" name="reason" value="">
                        <button class="p-btn p-btn-ghost" type="submit"><i data-lucide="ban"></i> Blokir</button>
                    </form>
                @endif
            </div>
        </div>

        @if (!empty($sharedDevices))
            <div class="p-notice is-warning" role="alert">
                <i data-lucide="copy"></i>
                <div>
                    <strong>HP yang sama dipakai toko lain.</strong>
                    <span>
                        @foreach ($sharedDevices as $deviceId => $others)
                            <span class="s-mono">{{ \Illuminate\Support\Str::limit($deviceId, 16) }}</span> →
                            @foreach ($others as $o)
                                <a class="p-link" href="{{ route('superadmin.stores.show', $o->id) }}">{{ $o->name }}</a>{{ $o->trial_started_at ? ' (pernah trial)' : '' }}@if (!$loop->last), @endif
                            @endforeach
                            <br>
                        @endforeach
                        Kemungkinan pendaftaran ulang untuk mendapat trial lagi.
                    </span>
                </div>
            </div>
        @endif

        <div class="p-form-layout">
            <div class="p-form-main">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="shield-check"></i></span>
                        <div><h3>Masa aktif</h3></div>
                    </div>
                    <dl class="s-kv">
                        <dt>Daftar</dt><dd>{{ $fmt($store->created_at) }}</dd>
                        <dt>Disetujui</dt><dd>{{ $fmt($store->approved_at) }}</dd>
                        <dt>Trial</dt><dd>{{ $fmt($store->trial_started_at) }} → {{ $fmt($store->trial_ends_at) }}</dd>
                        <dt>Berbayar sampai</dt><dd>{{ $fmt($store->paid_until) }} {{ $store->plan ? '(' . $store->plan->name . ')' : '' }}</dd>
                        <dt>Tenggang sampai</dt><dd>{{ $fmt($sub->graceEndsAt()) }}</dd>
                        <dt>Transaksi boleh s/d</dt><dd>{{ $fmt($sub->transactUntil()) }}</dd>
                        <dt>Transaksi</dt><dd>{{ number_format($stats->orders, 0, ',', '.') }} order · {{ $stats->flagged }} ditandai · terakhir masuk {{ $fmt($stats->last_order) }}</dd>
                        @if ($store->suspend_reason)<dt>Alasan blokir</dt><dd>{{ $store->suspend_reason }}</dd>@endif
                        <dt>Alamat</dt><dd>{{ $store->address ?: '-' }}</dd>
                    </dl>
                </section>

                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="smartphone"></i></span>
                        <div>
                            <h3>Perangkat</h3>
                            <p>Lepas kasir aktif bila HP hilang/rusak — HP berikutnya yang login otomatis menjadi kasir.</p>
                        </div>
                        @if ($store->active_device_id)
                            <form method="POST" action="{{ route('superadmin.stores.release-device', $store) }}" style="margin-left:auto"
                                data-confirm="Lepas perangkat kasir aktif toko ini?">
                                @csrf
                                <button class="p-btn p-btn-ghost" type="submit"><i data-lucide="smartphone-nfc"></i> Lepas kasir</button>
                            </form>
                        @endif
                    </div>
                    <div class="p-table-wrap">
                        <table class="p-table p-table-plain">
                            <thead><tr><th>HP</th><th>Status</th><th>Terakhir online</th><th>Terakhir sinkron</th></tr></thead>
                            <tbody>
                                @forelse ($devices as $d)
                                    <tr>
                                        <td><strong>{{ $d->device_name ?: 'Tanpa nama' }}</strong> <small>{{ $d->platform }} {{ $d->app_version ? 'v' . $d->app_version : '' }}</small><br>
                                            <small class="s-mono">{{ $d->device_id }}</small></td>
                                        <td>
                                            @if ((int) $store->active_device_id === $d->id)
                                                <span class="s-badge tone-green">Kasir aktif</span>
                                            @else
                                                <span class="s-badge tone-gray">Lihat</span>
                                            @endif
                                        </td>
                                        <td>{{ $fmt($d->last_seen_at) }}</td>
                                        <td>{{ $fmt($d->last_sync_at) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><span class="p-hint">Belum ada HP.</span></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($switches->isNotEmpty())
                        <h4 style="margin:14px 0 6px">Riwayat pindah kasir</h4>
                        @foreach ($switches as $s)
                            <div class="p-hint">{{ $fmt($s->created_at) }} — {{ $s->fromDevice->device_name ?? '—' }} → {{ $s->toDevice->device_name ?? 'dilepas' }} ({{ $s->source }}{{ $s->ip_address ? ', ' . $s->ip_address : '' }})</div>
                        @endforeach
                    @endif
                </section>

                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="clock-alert"></i></span>
                        <div><h3>Log jam HP <span class="s-badge {{ $tamper->isEmpty() ? 'tone-green' : 'tone-red' }}">{{ $tamper->count() }}</span></h3>
                            <p>Dikirim aplikasi saat sinkron.</p></div>
                    </div>
                    <div class="p-table-wrap">
                        <table class="p-table p-table-plain">
                            <thead><tr><th>Diterima</th><th>Jenis</th><th>Jam HP</th><th>Perkiraan jam asli</th><th class="text-end">Selisih</th></tr></thead>
                            <tbody>
                                @forelse ($tamper as $t)
                                    <tr>
                                        <td>{{ $fmt($t->created_at) }}</td>
                                        <td>{{ $kinds[$t->kind] ?? $t->kind }}</td>
                                        <td class="s-mono">{{ $t->device_time ?: '-' }}</td>
                                        <td class="s-mono">{{ $t->trusted_time ?: '-' }}</td>
                                        <td class="text-end">{{ $t->drift_seconds !== null ? round($t->drift_seconds / 60) . ' mnt' : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><span class="p-hint">Tidak ada kejadian.</span></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="p-form-side">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="banknote"></i></span>
                        <div><h3>Catat pembayaran</h3><p>Untuk pembayaran di luar aplikasi. Langsung dikonfirmasi.</p></div>
                    </div>
                    <form method="POST" action="{{ route('superadmin.stores.record-payment', $store) }}"
                        data-confirm="Catat pembayaran dan perpanjang masa aktif {{ $store->name }}?">
                        @csrf
                        <div class="p-field">
                            <label for="plan_id">Paket</label>
                            <select id="plan_id" name="plan_id" required>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} — {{ $rp($plan->price) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p-field">
                            <label for="amount">Nominal diterima</label>
                            <input type="number" id="amount" name="amount" min="0" placeholder="Kosong = harga paket">
                        </div>
                        <div class="p-field">
                            <label for="pay_note">Catatan</label>
                            <input type="text" id="pay_note" name="note" maxlength="500">
                        </div>
                        <div class="p-form-actions">
                            <button class="p-btn p-btn-primary" type="submit" @disabled($store->status !== 'active')><i data-lucide="save"></i> Catat</button>
                        </div>
                    </form>
                </section>

                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="history"></i></span>
                        <div><h3>Pembayaran</h3></div>
                    </div>
                    @forelse ($payments as $p)
                        <div class="p-toggle-row">
                            <div>
                                <strong>{{ $p->plan->name ?? '' }} · {{ $rp($p->amount) }}</strong>
                                <small>{{ $p->sender_name }} · {{ $p->transferred_at?->format('d/m/Y') }}
                                    @if ($p->period_end) · s/d {{ $p->period_end->format('d/m/Y') }}@endif
                                    @if ($p->proof_url) · <a class="p-link" href="{{ $p->proof_url }}" target="_blank" rel="noopener">bukti</a>@endif
                                </small>
                            </div>
                            <span class="s-badge {{ $payStatus[$p->status][1] ?? '' }}">{{ $payStatus[$p->status][0] ?? $p->status }}</span>
                        </div>
                    @empty
                        <p class="p-hint">Belum ada.</p>
                    @endforelse
                </section>

                <section class="p-card p-section">
                    <form method="POST" action="{{ route('superadmin.stores.notes', $store) }}">
                        @csrf
                        <div class="p-field">
                            <label for="admin_notes">Catatan superadmin</label>
                            <textarea id="admin_notes" name="admin_notes" rows="4" maxlength="2000">{{ $store->admin_notes }}</textarea>
                        </div>
                        <button class="p-btn p-btn-ghost" type="submit">Simpan catatan</button>
                    </form>
                </section>
            </aside>
        </div>
    </div>
@endsection
