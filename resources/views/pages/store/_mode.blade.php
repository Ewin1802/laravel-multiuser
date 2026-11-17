{{--
    MODE KASIR: Toko Umum / Mode Cafe (kolom stores.feature_draft_order).

    Dipakai di:
      - Profil Toko  → bagian dari form profil ($standalone = false)
      - Dashboard    → form sendiri, tersimpan begitu pilihan diubah ($standalone = true)
--}}
@php
    $modeStore = $modeStore ?? auth()->user()?->store;
    $standalone = $standalone ?? false;
    $isCafe = (bool) old('feature_draft_order', $modeStore?->feature_draft_order);
    $modes = [
        0 => [
            'icon' => 'store',
            'title' => 'Toko Umum',
            'desc' => 'Pembeli langsung bayar di kasir. Cocok untuk toko kelontong, minimarket, dan toko bangunan.',
            'points' => ['Scan barang, langsung bayar', 'Tanpa meja dan antrean pesanan'],
        ],
        1 => [
            'icon' => 'coffee',
            'title' => 'Mode Cafe',
            'desc' => 'Pesanan disimpan per meja dan dibayar saat tamu selesai. Cocok untuk cafe, warung makan, dan resto.',
            'points' => ['Makan di tempat / bawa pulang', 'Simpan pesanan per meja (draft)', 'Cetak checker dapur', 'Produk baru otomatis jenis Menu racikan'],
        ],
    ];
@endphp

@if ($standalone)
    <form method="POST" action="{{ route('store.mode.update') }}" class="km-form" data-km-auto>
        @csrf
        @method('PUT')
@endif

<section class="p-card km" data-km>
    <div class="km-head">
        <span class="p-section-icon"><i data-lucide="settings-2"></i></span>
        <div class="km-title">
            <h3>Mode Kasir</h3>
            <p>Menentukan cara aplikasi kasir melayani pembeli.</p>
        </div>
        <span class="km-status" data-km-status>
            <i class="km-dot"></i>
            Aktif saat ini: <b data-km-active>{{ $isCafe ? 'Mode Cafe' : 'Toko Umum' }}</b>
        </span>
    </div>

    <div class="km-options" role="radiogroup" aria-label="Mode kasir">
        @foreach ($modes as $value => $m)
            <label class="km-opt">
                <input type="radio" name="feature_draft_order" value="{{ $value }}"
                    data-km-label="{{ $m['title'] }}" @checked($isCafe === (bool) $value)>
                <span class="km-card">
                    <span class="km-card-top">
                        <span class="km-ic"><i data-lucide="{{ $m['icon'] }}"></i></span>
                        <strong>{{ $m['title'] }}</strong>
                        <span class="km-check"><i data-lucide="check"></i></span>
                    </span>
                    <small>{{ $m['desc'] }}</small>
                    <ul>
                        @foreach ($m['points'] as $pt)
                            <li>{{ $pt }}</li>
                        @endforeach
                    </ul>
                </span>
            </label>
        @endforeach
    </div>

    <p class="km-note">
        <i data-lucide="info"></i>
        <span>
            @if ($standalone)
                Pilihan langsung tersimpan.
            @else
                Klik <b>Simpan</b> untuk menerapkan.
            @endif
            Aplikasi kasir memakai mode baru setelah dibuka ulang atau disinkronkan. Transaksi lama tidak berubah.
        </span>
        <span class="km-pending" data-km-pending hidden>Belum disimpan</span>
    </p>
</section>

@if ($standalone)
    </form>
@endif

@once
    <style>
        .km { padding: 20px; }
        .km-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .km-title { flex: 1; min-width: 200px; }
        .km-title h3 { margin: 0; font-size: 16px; }
        .km-title p { margin: 2px 0 0; color: var(--p-muted, #6b7280); font-size: 13px; }
        .km-status {
            display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px;
            background: #e3f5ee; color: #0b7a66; font-size: 13px; font-weight: 600; white-space: nowrap;
        }
        .km-status b { font-weight: 800; }
        .km-dot { width: 8px; height: 8px; border-radius: 50%; background: #0b8f6a; box-shadow: 0 0 0 4px rgba(11, 143, 106, .18); }
        .km-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .km-opt { position: relative; display: flex; margin: 0; cursor: pointer; font-weight: 400; }
        .km-opt input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
        .km-card {
            flex: 1; display: flex; flex-direction: column; gap: 6px; padding: 16px; border-radius: 16px;
            border: 1.5px solid var(--p-line, #e5e7eb); background: var(--p-surface, #fff);
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .km-card-top { display: flex; align-items: center; gap: 10px; }
        .km-card-top strong { flex: 1; font-size: 15px; }
        .km-ic { width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; background: #f3f4f6; color: #4b5563; }
        .km-ic svg { width: 19px; height: 19px; }
        .km-check {
            width: 22px; height: 22px; border-radius: 50%; display: grid; place-items: center;
            border: 1.5px solid var(--p-line, #d1d5db); color: transparent;
        }
        .km-check svg { width: 13px; height: 13px; }
        .km-card small { color: var(--p-muted, #6b7280); font-size: 13px; line-height: 1.5; }
        .km-card ul { margin: 4px 0 0; padding-left: 18px; font-size: 13px; color: var(--p-ink2, #374151); line-height: 1.6; }
        .km-opt:hover .km-card { border-color: #fdba8c; }
        .km-opt input:checked + .km-card { border-color: #ff6a1a; background: #fff8f2; box-shadow: 0 0 0 3px rgba(255, 106, 26, .13); }
        .km-opt input:checked + .km-card .km-ic { background: linear-gradient(135deg, #ff8a3d, #ff6a1a); color: #fff; }
        .km-opt input:checked + .km-card .km-check { background: #ff6a1a; border-color: #ff6a1a; color: #fff; }
        .km-opt input:focus-visible + .km-card { outline: 3px solid #ff6a1a; outline-offset: 2px; }
        .km-note { display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap; margin: 14px 0 0; color: var(--p-muted, #6b7280); font-size: 12.5px; line-height: 1.5; }
        .km-note > svg { width: 15px; height: 15px; flex: none; margin-top: 2px; }
        .km-note > span:first-of-type { flex: 1; min-width: 200px; }
        .km-pending { padding: 2px 9px; border-radius: 999px; background: #fff4de; color: #a2630c; font-weight: 700; }
        .km-pending[hidden] { display: none; }
        .km-form { margin: 0 0 20px; }
        .km-form.is-saving { opacity: .6; pointer-events: none; }
        @media (max-width: 640px) {
            .km-options { grid-template-columns: 1fr; }
            .km-status { white-space: normal; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-km]').forEach((card) => {
                const radios = card.querySelectorAll('input[name="feature_draft_order"]');
                const pending = card.querySelector('[data-km-pending]');
                const saved = card.querySelector('input[name="feature_draft_order"]:checked')?.value;
                const form = card.closest('form[data-km-auto]');

                radios.forEach((r) => r.addEventListener('change', async () => {
                    const changed = r.value !== saved;
                    if (!form) {
                        // profil: tandai belum disimpan, tersimpan lewat tombol Simpan
                        if (pending) pending.hidden = !changed;
                        return;
                    }
                    if (!changed) return;
                    // dashboard: minta konfirmasi lalu simpan langsung
                    const ok = window.appConfirm
                        ? await window.appConfirm({
                            message: `Ganti ke ${r.dataset.kmLabel}? Aplikasi kasir memakai mode baru setelah dibuka ulang atau disinkronkan.`,
                            okText: 'Ya, ganti mode',
                            tone: 'primary',
                        })
                        : window.confirm(`Ganti ke ${r.dataset.kmLabel}?`);
                    if (!ok) {
                        card.querySelector(`input[value="${saved}"]`).checked = true;
                        return;
                    }
                    form.classList.add('is-saving');
                    window.showLoading?.();
                    form.submit();
                }));
            });
        });
    </script>
@endonce
