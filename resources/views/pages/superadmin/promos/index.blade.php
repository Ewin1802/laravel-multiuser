@extends('layouts.app')

@section('title', 'Kode Promo')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) ($n ?? 0), 0, ',', '.');
        $p = $edit;
        $type = old('discount_type', $p->discount_type ?? 'percent');
        $planIds = array_map('intval', old('plan_ids', $p->plan_ids ?? []));
        $statusTone = ['aktif' => 'tone-green', 'terjadwal' => 'tone-blue', 'berakhir' => 'tone-gray', 'habis' => 'tone-amber', 'nonaktif' => 'tone-red'];
        $planNames = $plans->pluck('name', 'id');
    @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Superadmin</span>
                <h1>Kode Promo</h1>
                <p>Buat kode potongan harga paket dengan masa berlaku. Toko memasukkan kode saat memperpanjang langganan, di web maupun aplikasi.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert"><i data-lucide="circle-alert"></i><div><span>{{ $errors->first() }}</span></div></div>
        @endif

        <div class="p-form-layout">
            {{-- ===================== DAFTAR ===================== --}}
            <div class="p-form-main">
                <section class="p-card">
                    @forelse ($promos as $promo)
                        @php
                            $status = $promo->status();
                            $plansText = empty($promo->plan_ids)
                                ? 'Semua paket'
                                : collect($promo->plan_ids)->map(fn($id) => $planNames[$id] ?? '#' . $id)->join(', ');
                            $share = "Pakai kode {$promo->code} untuk {$promo->label()} paket langganan"
                                . (empty($promo->plan_ids) ? '' : " {$plansText}")
                                . ", berlaku s/d {$promo->ends_at->translatedFormat('d M Y')}.";
                        @endphp
                        <article class="pr-row {{ $p && $p->id === $promo->id ? 'is-editing' : '' }}">
                            <div class="pr-ticket">
                                <span class="pr-code">{{ $promo->code }}</span>
                                <span class="pr-off">{{ $promo->discount_type === 'percent' ? $promo->discount_value . '%' : $rp($promo->discount_value) }}</span>
                            </div>

                            <div class="pr-info">
                                <div class="pr-title">
                                    <strong>{{ $promo->label() }}</strong>
                                    <span class="s-badge {{ $statusTone[$status] ?? 'tone-gray' }}">{{ ucfirst($status) }}</span>
                                </div>
                                <small>
                                    {{ $promo->starts_at->translatedFormat('d M Y') }} – {{ $promo->ends_at->translatedFormat('d M Y') }}
                                    &nbsp;|&nbsp; {{ $plansText }}
                                    @if ($promo->first_payment_only) &nbsp;|&nbsp; pembayaran pertama saja @endif
                                </small>
                                @if ($promo->description)<small class="pr-desc">{{ $promo->description }}</small>@endif

                                <div class="pr-usage">
                                    @php $pct = $promo->max_uses ? min(100, round($promo->used_count / $promo->max_uses * 100)) : null; @endphp
                                    <span>Dipakai <b>{{ $promo->used_count }}</b>{{ $promo->max_uses ? ' / ' . $promo->max_uses : '' }} kali</span>
                                    @if ($pct !== null)
                                        <span class="pr-bar"><i style="width: {{ $pct }}%"></i></span>
                                    @endif
                                    <span>Total potongan <b>{{ $rp($promo->discount_total) }}</b></span>
                                </div>
                            </div>

                            <div class="pr-actions">
                                <button type="button" class="p-icon-btn" title="Salin teks untuk dibagikan"
                                    aria-label="Salin teks promo {{ $promo->code }}" data-copy="{{ $share }}">
                                    <i data-lucide="share-2"></i>
                                </button>
                                <a href="{{ route('superadmin.promos.index', ['edit' => $promo->id]) }}" class="p-icon-btn"
                                    title="Ubah" aria-label="Ubah {{ $promo->code }}"><i data-lucide="pencil"></i></a>
                                <form method="POST" action="{{ route('superadmin.promos.destroy', $promo) }}"
                                    data-confirm="{{ $promo->used_count > 0 ? 'Kode ' . $promo->code . ' sudah dipakai, jadi akan dinonaktifkan. Lanjutkan?' : 'Hapus kode ' . $promo->code . '?' }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-icon-btn is-danger" title="Hapus"
                                        aria-label="Hapus {{ $promo->code }}"><i data-lucide="trash-2"></i></button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="p-empty">
                            <span class="p-empty-icon"><i data-lucide="ticket-percent"></i></span>
                            <h4>Belum ada kode promo</h4>
                            <p>Buat kode pertama di samping, lalu bagikan ke toko lewat WhatsApp atau brosur.</p>
                        </div>
                    @endforelse
                </section>
            </div>

            {{-- ===================== FORM ===================== --}}
            <aside class="p-form-side">
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="{{ $p ? 'pencil' : 'ticket-plus' }}"></i></span>
                        <div>
                            <h3>{{ $p ? 'Ubah ' . $p->code : 'Kode baru' }}</h3>
                            <p>{{ $p ? 'Perubahan tidak mengubah pembayaran yang sudah tercatat.' : 'Huruf besar otomatis, tanpa spasi.' }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ $p ? route('superadmin.promos.update', $p) : route('superadmin.promos.store') }}" id="promoForm">
                        @csrf
                        @if ($p) @method('PUT') @endif

                        <div class="p-field">
                            <label for="code">Kode <span class="req">*</span></label>
                            <input type="text" id="code" name="code" required maxlength="30" autocomplete="off"
                                value="{{ old('code', $p->code ?? '') }}" placeholder="HEMAT20" class="pr-input-code">
                        </div>

                        <fieldset class="p-field p-fieldset">
                            <legend class="p-field-label">Jenis potongan <span class="req">*</span></legend>
                            <div class="p-segmented">
                                <label><input type="radio" name="discount_type" value="percent" @checked($type === 'percent')><span><i data-lucide="percent"></i> Persen</span></label>
                                <label><input type="radio" name="discount_type" value="amount" @checked($type === 'amount')><span><i data-lucide="banknote"></i> Rupiah</span></label>
                            </div>
                        </fieldset>

                        <div class="p-grid">
                            <div class="p-field">
                                <label for="discount_value" id="valueLabel">{{ $type === 'percent' ? 'Diskon (%)' : 'Potongan (Rp)' }} <span class="req">*</span></label>
                                <input type="number" id="discount_value" name="discount_value" required min="1"
                                    max="{{ $type === 'percent' ? 100 : '' }}" value="{{ old('discount_value', $p->discount_value ?? '') }}"
                                    placeholder="{{ $type === 'percent' ? '20' : '25000' }}">
                            </div>
                            <div class="p-field" id="maxField" @if ($type !== 'percent') hidden @endif>
                                <label for="max_discount">Maks. potongan (Rp)</label>
                                <input type="number" id="max_discount" name="max_discount" min="1"
                                    value="{{ old('max_discount', $p->max_discount ?? '') }}" placeholder="Opsional">
                            </div>
                        </div>

                        <div class="p-grid">
                            <div class="p-field">
                                <label for="starts_at">Berlaku mulai <span class="req">*</span></label>
                                <input type="date" id="starts_at" name="starts_at" required
                                    value="{{ old('starts_at', $p?->starts_at?->toDateString() ?? now()->toDateString()) }}">
                            </div>
                            <div class="p-field">
                                <label for="ends_at">Sampai <span class="req">*</span></label>
                                <input type="date" id="ends_at" name="ends_at" required
                                    value="{{ old('ends_at', $p?->ends_at?->toDateString() ?? now()->endOfMonth()->toDateString()) }}">
                            </div>
                        </div>
                        <div class="p-chips p-chips-inline" aria-label="Isi cepat masa berlaku">
                            @foreach ([7 => '1 minggu', 14 => '2 minggu', 30 => '1 bulan'] as $d => $label)
                                <button type="button" class="p-chip" data-days="{{ $d }}">{{ $label }}</button>
                            @endforeach
                        </div>

                        <fieldset class="p-field p-fieldset">
                            <legend class="p-field-label">Berlaku untuk paket</legend>
                            <div class="pr-plans">
                                @foreach ($plans as $plan)
                                    <label class="pr-check">
                                        <input type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" @checked(in_array($plan->id, $planIds, true))>
                                        <span>{{ $plan->name }} <small>{{ $rp($plan->price) }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                            <small class="p-hint">Tidak dicentang sama sekali = semua paket.</small>
                        </fieldset>

                        <div class="p-grid">
                            <div class="p-field">
                                <label for="max_uses">Kuota total</label>
                                <input type="number" id="max_uses" name="max_uses" min="1"
                                    value="{{ old('max_uses', $p->max_uses ?? '') }}" placeholder="Tanpa batas">
                            </div>
                            <div class="p-field">
                                <label for="per_store_limit">Maks. per toko <span class="req">*</span></label>
                                <input type="number" id="per_store_limit" name="per_store_limit" required min="1" max="100"
                                    value="{{ old('per_store_limit', $p->per_store_limit ?? 1) }}">
                            </div>
                        </div>

                        <div class="p-field">
                            <label for="description">Catatan</label>
                            <input type="text" id="description" name="description" maxlength="255"
                                value="{{ old('description', $p->description ?? '') }}" placeholder="mis. Promo HUT RI">
                        </div>

                        <div class="p-toggle-row">
                            <div><strong>Hanya pembayaran pertama</strong><small>Untuk toko yang belum pernah bayar (selesai trial).</small></div>
                            <label class="p-switch">
                                <input type="checkbox" name="first_payment_only" value="1" @checked(old('first_payment_only', $p->first_payment_only ?? false))>
                                <span class="p-switch-track"></span><span class="sr-only">Hanya pembayaran pertama</span>
                            </label>
                        </div>
                        <div class="p-toggle-row">
                            <div><strong>Aktif</strong><small>Matikan untuk menghentikan promo lebih cepat.</small></div>
                            <label class="p-switch">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $p->is_active ?? true))>
                                <span class="p-switch-track"></span><span class="sr-only">Aktif</span>
                            </label>
                        </div>

                        <div class="pr-preview" id="preview" aria-live="polite"></div>

                        <div class="p-form-actions">
                            @if ($p)
                                <a href="{{ route('superadmin.promos.index') }}" class="p-btn p-btn-ghost">Batal</a>
                            @endif
                            <button type="submit" class="p-btn p-btn-primary"><i data-lucide="save"></i> {{ $p ? 'Simpan' : 'Buat kode' }}</button>
                        </div>
                    </form>
                </section>
            </aside>
        </div>
    </div>

    <style>
        .pr-row {
            display: grid;
            grid-template-columns: 132px minmax(0, 1fr) auto;
            gap: 16px;
            align-items: center;
            padding: 16px 18px;
            border-bottom: 1px solid var(--pl, #eef0f3);
        }
        .pr-row:last-child { border-bottom: 0; }
        .pr-row.is-editing { background: #fff7f1; }
        .pr-ticket {
            position: relative;
            display: grid;
            gap: 2px;
            padding: 12px 12px 12px 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, #14171c, #2a2f37);
            color: #fff;
            text-align: left;
            -webkit-mask: radial-gradient(circle 7px at 0 50%, #0000 98%, #000) left / 51% 100% no-repeat,
                radial-gradient(circle 7px at 100% 50%, #0000 98%, #000) right / 51% 100% no-repeat;
            mask: radial-gradient(circle 7px at 0 50%, #0000 98%, #000) left / 51% 100% no-repeat,
                radial-gradient(circle 7px at 100% 50%, #0000 98%, #000) right / 51% 100% no-repeat;
        }
        .pr-code { font: 700 14px/1.2 ui-monospace, Menlo, Consolas, monospace; letter-spacing: .06em; overflow-wrap: anywhere; }
        .pr-off { color: #ffae1f; font-size: 20px; font-weight: 800; line-height: 1.1; }
        .pr-info { min-width: 0; display: grid; gap: 3px; }
        .pr-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .pr-title strong { font-size: 14.5px; }
        .pr-info small { color: var(--pm, #667080); font-size: 12.5px; }
        .pr-desc { font-style: italic; }
        .pr-usage { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 6px; font-size: 12.5px; color: var(--pm, #667080); }
        .pr-usage b { color: var(--pk, #14171c); }
        .pr-bar { display: inline-block; width: 90px; height: 6px; border-radius: 4px; background: #eef0f3; overflow: hidden; }
        .pr-bar i { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg, #ff6a1a, #ffae1f); }
        .pr-actions { display: flex; gap: 6px; }
        .pr-input-code { font-family: ui-monospace, Menlo, Consolas, monospace; letter-spacing: .08em; text-transform: uppercase; }
        .pr-plans { display: grid; gap: 6px; }
        .pr-check { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border: 1px solid var(--pb, #e5e7eb); border-radius: 11px; cursor: pointer; font-size: 14px; }
        .pr-check input { accent-color: #ff6a1a; width: 16px; height: 16px; }
        .pr-check small { color: var(--pm, #667080); margin-left: 4px; }
        .pr-preview:empty { display: none; }
        .pr-preview { margin: 6px 0 12px; padding: 12px 14px; border-radius: 12px; background: #ecfdf3; color: #14532d; font-size: 13px; line-height: 1.6; }
        .pr-preview b { color: #14171c; }
        #maxField[hidden] { display: none; }
        @media (max-width: 720px) {
            .pr-row { grid-template-columns: 1fr; gap: 10px; }
            .pr-ticket { justify-self: start; min-width: 150px; }
            .pr-actions { justify-content: flex-end; }
        }
    </style>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('promoForm');
            if (!form) return;
            const PLANS = @json($plans->map(fn($pl) => ['id' => $pl->id, 'name' => $pl->name, 'price' => (int) $pl->price])->values());
            const rp = (n) => 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
            const $ = (s) => form.querySelector(s);
            const value = $('#discount_value');
            const max = $('#max_discount');

            const type = () => form.querySelector('input[name="discount_type"]:checked')?.value || 'percent';

            const preview = () => {
                const t = type();
                const v = Number(value.value) || 0;
                const m = Number(max.value) || 0;
                const picked = [...form.querySelectorAll('input[name="plan_ids[]"]:checked')].map((c) => Number(c.value));
                const plans = PLANS.filter((p) => !picked.length || picked.includes(p.id));
                if (!v || !plans.length) { $('#preview').innerHTML = ''; return; }

                $('#preview').innerHTML = '<b>Harga setelah promo</b><br>' + plans.map((p) => {
                    let d = t === 'percent' ? Math.floor(p.price * Math.min(100, v) / 100) : v;
                    if (t === 'percent' && m) d = Math.min(d, m);
                    d = Math.max(0, Math.min(d, p.price));
                    return `${p.name}: <s>${rp(p.price)}</s> → <b>${rp(p.price - d)}</b> (hemat ${rp(d)})`;
                }).join('<br>');
            };

            const syncType = () => {
                const t = type();
                $('#maxField').hidden = t !== 'percent';
                $('#valueLabel').firstChild.textContent = t === 'percent' ? 'Diskon (%) ' : 'Potongan (Rp) ';
                value.max = t === 'percent' ? 100 : '';
                value.placeholder = t === 'percent' ? '20' : '25000';
                preview();
            };

            form.addEventListener('change', (e) => {
                if (e.target.name === 'discount_type') syncType(); else preview();
            });
            form.addEventListener('input', preview);

            // isi cepat masa berlaku dari tanggal mulai
            form.querySelectorAll('[data-days]').forEach((b) => b.addEventListener('click', () => {
                const start = $('#starts_at').value;
                if (!start) return;
                const d = new Date(start + 'T00:00:00');
                d.setDate(d.getDate() + Number(b.dataset.days) - 1);
                $('#ends_at').value = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
            }));

            // kode: huruf besar, tanpa spasi
            $('#code').addEventListener('input', (e) => {
                const pos = e.target.selectionStart;
                e.target.value = e.target.value.toUpperCase().replace(/\s+/g, '');
                e.target.setSelectionRange(pos, pos);
            });

            syncType();
        })();

        // salin teks promo untuk dibagikan
        document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(b.dataset.copy);
                window.showToast?.('success', 'Tersalin', 'Teks promo siap ditempel di WhatsApp.');
            } catch (e) {
                window.prompt('Salin teks ini:', b.dataset.copy);
            }
        }));
    </script>
@endpush
