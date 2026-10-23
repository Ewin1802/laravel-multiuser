@extends('layouts.app')

@section('title', 'Pengumuman')

@section('content')
    @php
        $target = old('target', 'all');
        $appName = 'My Cashier';
    @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Superadmin</span>
                <h1>Pengumuman</h1>
                <p>Kirim push notification ke HP kasir: semua pengguna sekaligus, atau satu toko saja.</p>
            </div>
        </div>

        @if (! $fcmReady)
            <div class="p-notice is-warning" role="alert">
                <i data-lucide="plug-zap"></i>
                <div>
                    <strong>Push notification belum aktif.</strong>
                    <span>Pasang file service account Firebase di server (lihat <code>FIREBASE_CREDENTIALS</code> di
                        <code>.env</code>), lalu jalankan <code>php artisan config:cache</code>.</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert"><i data-lucide="circle-alert"></i><div><span>{{ $errors->first() }}</span></div></div>
        @endif

        <div class="an-stats">
            <div class="an-stat p-card">
                <span class="an-stat-ic"><i data-lucide="smartphone"></i></span>
                <div><b>{{ number_format($deviceCount, 0, ',', '.') }}</b><small>HP terdaftar notifikasi</small></div>
            </div>
            <div class="an-stat p-card">
                <span class="an-stat-ic"><i data-lucide="store"></i></span>
                <div><b>{{ number_format($storeReach, 0, ',', '.') }}</b><small>toko terjangkau</small></div>
            </div>
            <div class="an-stat p-card">
                <span class="an-stat-ic {{ $fcmReady ? 'is-ok' : 'is-off' }}"><i data-lucide="{{ $fcmReady ? 'bell-ring' : 'bell-off' }}"></i></span>
                <div><b>{{ $fcmReady ? 'Aktif' : 'Belum aktif' }}</b><small>Firebase Cloud Messaging</small></div>
            </div>
        </div>

        <div class="p-form-layout">
            {{-- ===================== FORM ===================== --}}
            <div class="p-form-main">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="megaphone"></i></span>
                        <div>
                            <h3>Pengumuman baru</h3>
                            <p>Muncul sebagai notifikasi di HP kasir, termasuk saat aplikasi ditutup.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('superadmin.announcements.store') }}" id="anForm"
                        data-confirm="Kirim pengumuman ini sekarang? Notifikasi yang sudah terkirim tidak bisa ditarik kembali."
                        data-confirm-ok="Kirim sekarang" data-confirm-tone="primary">
                        @csrf

                        <fieldset class="p-field p-fieldset">
                            <legend class="p-field-label">Kirim ke <span class="req">*</span></legend>
                            <div class="p-segmented">
                                <label><input type="radio" name="target" value="all" @checked($target === 'all')><span><i data-lucide="users"></i> Semua pengguna</span></label>
                                <label><input type="radio" name="target" value="store" @checked($target === 'store')><span><i data-lucide="store"></i> Satu toko</span></label>
                            </div>
                        </fieldset>

                        <div class="p-field" id="storeField" @if ($target !== 'store') hidden @endif>
                            <label for="store_id">Toko <span class="req">*</span></label>
                            <select id="store_id" name="store_id">
                                <option value="">Pilih toko aktif…</option>
                                @foreach ($stores as $s)
                                    <option value="{{ $s->id }}" @selected((string) old('store_id') === (string) $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="p-field">
                            <label for="title">Judul <span class="req">*</span></label>
                            <input type="text" id="title" name="title" required maxlength="65" autocomplete="off"
                                value="{{ old('title') }}" placeholder="mis. Pemeliharaan server malam ini">
                            <small class="p-hint an-count" data-for="title"></small>
                        </div>

                        <div class="p-field">
                            <label for="body">Isi pesan <span class="req">*</span></label>
                            <textarea id="body" name="body" rows="4" required maxlength="240"
                                placeholder="mis. Pukul 23.00–23.30 WITA aplikasi tetap bisa transaksi offline. Data terkirim otomatis setelah selesai.">{{ old('body') }}</textarea>
                            <small class="p-hint an-count" data-for="body"></small>
                        </div>

                        <div class="p-chips p-chips-inline" aria-label="Contoh pengumuman">
                            <button type="button" class="p-chip" data-fill-title="Pemeliharaan server"
                                data-fill-body="Malam ini pukul 23.00–23.30 WITA server dalam pemeliharaan. Kasir tetap bisa transaksi offline, data terkirim otomatis setelah selesai.">Pemeliharaan</button>
                            <button type="button" class="p-chip" data-fill-title="Fitur baru di {{ $appName }}"
                                data-fill-body="Perbarui aplikasi dari Play Store untuk mencoba fitur terbaru.">Fitur baru</button>
                            <button type="button" class="p-chip" data-fill-title="Promo perpanjangan langganan"
                                data-fill-body="Pakai kode promo saat memperpanjang langganan. Berlaku sampai akhir bulan.">Promo</button>
                        </div>

                        <div class="an-actions">
                            <button type="submit" class="p-btn p-btn-primary" @disabled(! $fcmReady)>
                                <i data-lucide="send"></i> Kirim pengumuman
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            {{-- ===================== PRATINJAU ===================== --}}
            <aside class="p-form-side">
                <section class="p-card p-section an-preview-card">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="smartphone"></i></span>
                        <div>
                            <h3>Pratinjau di HP</h3>
                            <p id="anReach">{{ $target === 'store' ? 'Hanya HP kasir toko yang dipilih.' : 'Semua HP kasir yang sudah login.' }}</p>
                        </div>
                    </div>
                    <div class="an-phone">
                        <div class="an-phone-bar"><span>{{ now()->format('H:i') }}</span><span>●●● 4G</span></div>
                        <div class="an-notif">
                            <div class="an-notif-head">
                                <span class="an-notif-ic"><i data-lucide="store"></i></span>
                                <span>{{ $appName }}</span><span>· sekarang</span>
                            </div>
                            <strong id="pvTitle">Judul pengumuman</strong>
                            <p id="pvBody">Isi pesan tampil di sini.</p>
                        </div>
                    </div>
                    <small class="p-hint">HP kasir harus login minimal sekali dengan aplikasi versi terbaru dan mengizinkan notifikasi.</small>
                </section>
            </aside>
        </div>

        {{-- ===================== RIWAYAT ===================== --}}
        <section class="p-card an-history">
            <div class="an-history-head">
                <h3>Riwayat</h3>
                <small>{{ $announcements->total() }} pengumuman</small>
            </div>
            @forelse ($announcements as $a)
                <article class="an-row">
                    <span class="an-row-ic {{ $a->status === 'sent' ? 'is-ok' : 'is-fail' }}">
                        <i data-lucide="{{ $a->status === 'sent' ? 'check' : 'x' }}"></i>
                    </span>
                    <div class="an-row-main">
                        <div class="an-row-title">
                            <strong>{{ $a->title }}</strong>
                            <span class="s-badge {{ $a->target === 'store' ? 'tone-blue' : 'tone-green' }}">
                                {{ $a->target === 'store' ? ($a->store?->name ?? 'Toko #' . $a->store_id) : 'Semua pengguna' }}
                            </span>
                            @if ($a->status !== 'sent')
                                <span class="s-badge tone-red">Gagal</span>
                            @endif
                        </div>
                        <p>{{ $a->body }}</p>
                        @if ($a->error)
                            <small class="an-err">{{ $a->error }}</small>
                        @endif
                    </div>
                    <small class="an-row-when">
                        {{ $a->created_at->translatedFormat('d M Y, H:i') }}
                        @if ($a->sender)<br>oleh {{ $a->sender->name }}@endif
                    </small>
                </article>
            @empty
                <div class="p-empty">
                    <span class="p-empty-icon"><i data-lucide="megaphone"></i></span>
                    <h4>Belum ada pengumuman</h4>
                    <p>Pengumuman yang dikirim akan tercatat di sini.</p>
                </div>
            @endforelse
            @if ($announcements->hasPages())
                <div class="an-pages">{{ $announcements->links() }}</div>
            @endif
        </section>
    </div>

    <style>
        .an-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .an-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; margin: 0; }
        .an-stat b { display: block; font-size: 18px; line-height: 1.2; }
        .an-stat small { color: var(--pm, #667080); font-size: 12.5px; }
        .an-stat-ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: #fff1e8; color: #d9500a; flex: none; }
        .an-stat-ic.is-ok { background: #e2f4ed; color: #0b7a66; }
        .an-stat-ic.is-off { background: #fdecec; color: #c0362c; }
        .an-stat-ic svg { width: 19px; height: 19px; }

        .an-preview-card { position: sticky; top: 16px; }
        .an-phone { margin: 6px 0 12px; padding: 12px; border-radius: 26px; background: linear-gradient(160deg, #063f30, #0b8f6a); box-shadow: inset 0 0 0 6px #11161b; }
        .an-phone-bar { display: flex; justify-content: space-between; padding: 6px 12px 12px; color: rgba(255,255,255,.85); font-size: 11.5px; font-weight: 600; }
        .an-notif { margin: 0 4px 70px; padding: 12px 14px; border-radius: 18px; background: rgba(255,255,255,.94); color: #14201b; box-shadow: 0 10px 24px rgba(0,0,0,.25); }
        .an-notif-head { display: flex; align-items: center; gap: 6px; font-size: 11.5px; color: #5d6b65; margin-bottom: 6px; }
        .an-notif-head span:nth-child(2) { font-weight: 700; color: #14201b; }
        .an-notif-ic { width: 20px; height: 20px; border-radius: 6px; display: grid; place-items: center; background: linear-gradient(135deg, #ff8a3d, #ff6a1a); color: #fff; }
        .an-notif-ic svg { width: 12px; height: 12px; }
        .an-notif strong { display: block; font-size: 14px; line-height: 1.35; overflow-wrap: anywhere; }
        .an-notif p { margin: 3px 0 0; font-size: 13px; line-height: 1.45; color: #2c3a34; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }

        .an-actions { margin-top: 16px; }
        .an-actions .p-btn { width: 100%; justify-content: center; }
        .an-count { text-align: right; }
        .an-count.is-near { color: #d9500a; }

        .an-history { margin-top: 18px; }
        .an-history-head { display: flex; align-items: baseline; justify-content: space-between; padding: 16px 18px; border-bottom: 1px solid var(--pl, #eef0f3); }
        .an-history-head h3 { margin: 0; font-size: 16px; }
        .an-history-head small { color: var(--pm, #667080); }
        .an-row { display: grid; grid-template-columns: 34px minmax(0, 1fr) auto; gap: 14px; align-items: start; padding: 14px 18px; border-bottom: 1px solid var(--pl, #eef0f3); }
        .an-row:last-of-type { border-bottom: 0; }
        .an-row-ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; }
        .an-row-ic.is-ok { background: #e2f4ed; color: #0b7a66; }
        .an-row-ic.is-fail { background: #fdecec; color: #c0362c; }
        .an-row-ic svg { width: 16px; height: 16px; }
        .an-row-main { min-width: 0; }
        .an-row-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .an-row-title strong { font-size: 14.5px; }
        .an-row-main p { margin: 4px 0 0; color: var(--pk2, #3a4350); font-size: 13.5px; line-height: 1.5; overflow-wrap: anywhere; }
        .an-err { display: block; margin-top: 4px; color: #c0362c; font-size: 12px; overflow-wrap: anywhere; }
        .an-row-when { color: var(--pm, #667080); font-size: 12px; text-align: right; white-space: nowrap; }
        .an-pages { padding: 12px 18px; }
        @media (max-width: 640px) {
            .an-row { grid-template-columns: 34px minmax(0, 1fr); }
            .an-row-when { grid-column: 2; text-align: left; white-space: normal; }
            .an-preview-card { position: static; }
        }
    </style>

    <script>
        (() => {
            const form = document.getElementById('anForm');
            if (!form) return;
            const title = form.querySelector('#title');
            const body = form.querySelector('#body');
            const storeField = document.getElementById('storeField');
            const storeSelect = document.getElementById('store_id');
            const reach = document.getElementById('anReach');
            const pvTitle = document.getElementById('pvTitle');
            const pvBody = document.getElementById('pvBody');

            const sync = () => {
                pvTitle.textContent = title.value.trim() || 'Judul pengumuman';
                pvBody.textContent = body.value.trim() || 'Isi pesan tampil di sini.';
                form.querySelectorAll('.an-count').forEach((el) => {
                    const input = form.querySelector('#' + el.dataset.for);
                    const max = Number(input.getAttribute('maxlength'));
                    el.textContent = input.value.length + ' / ' + max;
                    el.classList.toggle('is-near', input.value.length > max * 0.85);
                });
            };

            const syncTarget = () => {
                const toStore = form.querySelector('input[name="target"]:checked')?.value === 'store';
                storeField.hidden = !toStore;
                storeSelect.required = toStore;
                const name = storeSelect.selectedOptions[0]?.value ? storeSelect.selectedOptions[0].textContent : 'toko yang dipilih';
                reach.textContent = toStore ? 'Hanya HP kasir ' + name + '.' : 'Semua HP kasir yang sudah login.';
                form.dataset.confirm = toStore
                    ? 'Kirim pengumuman ini ke ' + name + '? Notifikasi yang sudah terkirim tidak bisa ditarik kembali.'
                    : 'Kirim pengumuman ini ke SEMUA pengguna? Notifikasi yang sudah terkirim tidak bisa ditarik kembali.';
            };

            form.querySelectorAll('[data-fill-title]').forEach((btn) => btn.addEventListener('click', () => {
                title.value = btn.dataset.fillTitle;
                body.value = btn.dataset.fillBody;
                sync();
                body.focus();
            }));

            title.addEventListener('input', sync);
            body.addEventListener('input', sync);
            form.querySelectorAll('input[name="target"]').forEach((r) => r.addEventListener('change', syncTarget));
            storeSelect.addEventListener('change', syncTarget);
            sync();
            syncTarget();
        })();
    </script>
@endsection
