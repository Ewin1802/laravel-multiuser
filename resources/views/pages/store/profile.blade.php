@extends('layouts.app')

@section('title', 'Profil Toko')

@section('content')
    @php
        $fmt = fn($d) => $d ? $d->translatedFormat('d M Y, H:i') : '-';
        $activeId = (int) $store->active_device_id;
    @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Toko</span>
                <h1>Profil Toko</h1>
                <p>Data ini tampil di struk dan aplikasi kasir.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <div><span>{{ $errors->first() }}</span></div>
            </div>
        @endif

        <form method="POST" action="{{ route('store.profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="p-form-layout">
                <div class="p-form-main">
                    <section class="p-card p-section">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="store"></i></span>
                            <div><h3>Informasi Toko</h3></div>
                        </div>

                        <div class="p-grid">
                            <div class="p-field p-span-2">
                                <label for="name">Nama toko <span class="req">*</span></label>
                                <input type="text" id="name" name="name" required maxlength="255"
                                    value="{{ old('name', $store->name) }}">
                            </div>
                            <div class="p-field">
                                <label for="phone">Telepon</label>
                                <input type="text" id="phone" name="phone" maxlength="30"
                                    value="{{ old('phone', $store->phone) }}">
                            </div>
                            <div class="p-field">
                                <label for="logo">Logo</label>
                                <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
                                @if ($store->logo)
                                    <small class="p-hint">Logo sekarang: <img src="{{ $store->logo_url }}" alt="" height="24" style="vertical-align:middle"></small>
                                @endif
                            </div>
                            <div class="p-field p-span-2">
                                <label for="address">Alamat</label>
                                <textarea id="address" name="address" rows="2" maxlength="500">{{ old('address', $store->address) }}</textarea>
                            </div>
                            <div class="p-field">
                                <label for="bank_name">Bank</label>
                                <input type="text" id="bank_name" name="bank_name" maxlength="50"
                                    value="{{ old('bank_name', $store->bank_name) }}" placeholder="BRI / BCA ...">
                            </div>
                            <div class="p-field">
                                <label for="bank_account">No. rekening</label>
                                <input type="text" id="bank_account" name="bank_account" maxlength="50"
                                    value="{{ old('bank_account', $store->bank_account) }}">
                            </div>
                            <div class="p-field p-span-2">
                                <label for="bank_holder">Atas nama rekening</label>
                                <input type="text" id="bank_holder" name="bank_holder" maxlength="100"
                                    value="{{ old('bank_holder', $store->bank_holder) }}">
                                <small class="p-hint">Dicetak di faktur A4 dari aplikasi kasir.</small>
                            </div>
                            <div class="p-field p-span-2">
                                <label for="receipt_footer">Catatan bawah struk</label>
                                <input type="text" id="receipt_footer" name="receipt_footer" maxlength="255"
                                    value="{{ old('receipt_footer', $store->receipt_footer) }}"
                                    placeholder="Terima kasih, barang yang sudah dibeli tidak dapat ditukar.">
                            </div>
                        </div>
                    </section>

                    <section class="p-card p-section">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="smartphone"></i></span>
                            <div>
                                <h3>Perangkat Kasir</h3>
                                <p>Hanya satu HP yang bisa membuat transaksi. Untuk pindah HP, login di HP baru lalu pilih
                                    <b>Pindahkan kasir ke HP ini</b>.</p>
                            </div>
                        </div>

                        <div class="p-table-wrap">
                            <table class="p-table p-table-plain">
                                <thead>
                                    <tr>
                                        <th>HP</th>
                                        <th>Status</th>
                                        <th class="cell-hide-sm">Terakhir online</th>
                                        <th class="cell-hide-sm">Terakhir sinkron</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($devices as $d)
                                        <tr>
                                            <td>
                                                <strong>{{ $d->device_name ?: 'HP tanpa nama' }}</strong><br>
                                                <small class="s-mono">{{ \Illuminate\Support\Str::limit($d->device_id, 18) }}</small>
                                                @if ($d->app_version) <small>· v{{ $d->app_version }}</small> @endif
                                            </td>
                                            <td>
                                                @if ($d->id === $activeId)
                                                    <span class="s-badge tone-green">Kasir aktif</span>
                                                @else
                                                    <span class="s-badge tone-gray">Mode lihat</span>
                                                @endif
                                            </td>
                                            <td class="cell-hide-sm">{{ $fmt($d->last_seen_at) }}</td>
                                            <td class="cell-hide-sm">{{ $fmt($d->last_sync_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4"><span class="p-hint">Belum ada HP yang login.</span></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($switches->isNotEmpty())
                            <h4 style="margin:14px 0 6px">Riwayat pindah kasir</h4>
                            @foreach ($switches as $s)
                                <div class="p-hint">
                                    {{ $fmt($s->created_at) }} —
                                    {{ $s->fromDevice->device_name ?? 'tidak ada' }} → {{ $s->toDevice->device_name ?? 'dilepas' }}
                                    ({{ $s->source === 'superadmin' ? 'oleh admin aplikasi' : 'dari aplikasi' }})
                                </div>
                            @endforeach
                        @endif
                    </section>
                </div>

                <aside class="p-form-side">
                    <section class="p-card p-section">
                        <div class="p-toggle-row">
                            <div>
                                <strong>Draft Order (Cafe)</strong>
                                <small>Pilih meja, simpan pesanan, bayar nanti.</small>
                            </div>
                            <label class="p-switch">
                                <input type="checkbox" name="feature_draft_order" value="1"
                                    @checked(old('feature_draft_order', $store->feature_draft_order))>
                                <span class="p-switch-track"></span>
                                <span class="sr-only">Aktifkan draft order</span>
                            </label>
                        </div>

                        <div class="p-form-actions">
                            <button type="submit" class="p-btn p-btn-primary">
                                <i data-lucide="save"></i> Simpan
                            </button>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
    </div>
@endsection
