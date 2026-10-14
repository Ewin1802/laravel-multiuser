@extends('layouts.app')

@section('title', 'Buka Kemasan')

@section('content')
    @php
        $rp = fn($n) => "Rp\u{00A0}" . number_format((float) $n, 0, ',', '.');
        $qty = function ($v) {
            $v = (float) $v;
            return fmod($v, 1.0) == 0.0
                ? number_format($v, 0, ',', '.')
                : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
        };
        $productData = $products
            ->map(
                fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'unit' => $p->base_unit ?: 'PCS',
                    'stock' => (float) $p->stock,
                    'cost' => $p->cost_price !== null ? (float) $p->cost_price : null,
                    'active' => (bool) $p->status,
                ],
            )
            ->values();
        $old = [
            'source' => old('source_product_id', request('source')),
            'target' => old('target_product_id'),
            'qty' => old('source_qty', 1),
            'ratio' => old('ratio'),
            'note' => old('note'),
        ];
    @endphp

    <div class="crud-page">

        {{-- ===================== HEADER ===================== --}}
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Stok &amp; Pembelian</span>
                <h1>Buka Kemasan</h1>
                <p>Pecah stok kemasan (karung, jeriken, rol) menjadi stok eceran (kg, liter, meter) — stok kemasan berkurang,
                    stok eceran bertambah, harga beli per eceran dihitung otomatis.</p>
            </div>
            <div class="p-header-actions">
                <button type="button" class="s-guide-trigger" data-guide-open="conversionGuide">
                    <span class="s-guide-trigger-icon"><i data-lucide="sparkles"></i></span>
                    <span>Panduan</span>
                </button>
            </div>
        </div>

        <div class="p-notice is-info s-cv-hint" role="note">
            <i data-lucide="lightbulb"></i>
            <div>
                <strong>Rokok, air mineral, mi instan?</strong>
                <span>Barang dengan isi kemasan tetap lebih mudah diatur sebagai <b>satuan jual</b> di produknya
                    (mis. SLOP / BUNGKUS / BATANG) — tidak perlu dibuka di sini.
                    <button type="button" class="s-guide-link" data-guide-open="conversionGuide" data-guide-tab="cara">Bandingkan caranya</button></span>
            </div>
        </div>

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <div>
                    <strong>Belum tersimpan.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        {{-- ===================== RINGKASAN ===================== --}}
        <section class="p-stats" aria-label="Ringkasan buka kemasan">
            <div class="p-stat is-half">
                <span class="p-stat-label"><i data-lucide="package-open"></i> Dibuka bulan ini</span>
                <strong class="p-stat-value">{{ number_format($summary->month_count, 0, ',', '.') }}×</strong>
                <span class="p-stat-sub">nilai modal dipindah {{ $rp($summary->month_value) }}</span>
            </div>
            <div class="p-stat is-half">
                <span class="p-stat-label"><i data-lucide="link-2"></i> Pasangan tersimpan</span>
                <strong class="p-stat-value">{{ number_format($summary->pairs, 0, ',', '.') }}</strong>
                <span class="p-stat-sub">kemasan → eceran terisi otomatis berikutnya</span>
            </div>
        </section>

        {{-- ===================== FORM ===================== --}}
        <form method="POST" action="{{ route('stock-conversions.store') }}" id="convForm" autocomplete="off">
            @csrf
            <div class="p-form-layout">
                <div class="p-form-main">
                    <section class="p-card p-section">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="package"></i></span>
                            <div>
                                <h3>1. Kemasan yang dibuka</h3>
                                <p>Produk yang stoknya berkurang, mis. Beras Karung 25 kg.</p>
                            </div>
                        </div>

                        <div class="p-grid">
                            <div class="p-field p-span-2">
                                <label for="source_product_id">Produk kemasan <span class="req">*</span></label>
                                <input type="search" class="cv-filter" data-for="source_product_id"
                                    placeholder="Cari nama / kode produk..." aria-label="Cari produk kemasan">
                                <select id="source_product_id" name="source_product_id" required
                                    class="@error('source_product_id') is-invalid @enderror">
                                    <option value="">— pilih produk —</option>
                                </select>
                                <small class="p-hint" id="sourceInfo"></small>
                            </div>

                            <div class="p-field">
                                <label for="source_qty">Jumlah dibuka <span class="req">*</span></label>
                                <div class="cv-unit-input">
                                    <input type="number" id="source_qty" name="source_qty" min="0.01" step="any"
                                        value="{{ $old['qty'] }}" required
                                        class="@error('source_qty') is-invalid @enderror">
                                    <span id="sourceUnit">PCS</span>
                                </div>
                            </div>

                            <div class="p-field">
                                <label for="ratio">Isi per kemasan <span class="req">*</span></label>
                                <div class="cv-unit-input">
                                    <input type="number" id="ratio" name="ratio" min="0.001" step="any"
                                        value="{{ $old['ratio'] }}" placeholder="mis. 20" required
                                        class="@error('ratio') is-invalid @enderror">
                                    <span id="ratioUnit">eceran</span>
                                </div>
                                <small class="p-hint">Berapa satuan eceran dalam 1 kemasan.</small>
                            </div>
                        </div>
                    </section>

                    <section class="p-card p-section">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="droplet"></i></span>
                            <div>
                                <h3>2. Produk eceran</h3>
                                <p>Produk yang stoknya bertambah, satuannya KG / LITER / METER.</p>
                            </div>
                        </div>

                        <div class="p-grid">
                            <div class="p-field p-span-2">
                                <label for="target_product_id">Produk eceran <span class="req">*</span></label>
                                <input type="search" class="cv-filter" data-for="target_product_id"
                                    placeholder="Cari nama / kode produk..." aria-label="Cari produk eceran">
                                <select id="target_product_id" name="target_product_id" required
                                    class="@error('target_product_id') is-invalid @enderror">
                                    <option value="">— pilih produk —</option>
                                </select>
                                <small class="p-hint" id="targetInfo">
                                    Belum ada produk ecerannya? <a href="{{ route('products.create') }}"
                                        class="p-link">Tambah produk</a>
                                    dengan satuan LITER / KG / METER dan stok awal 0.
                                </small>
                            </div>

                            <div class="p-field p-span-2">
                                <label for="note">Catatan</label>
                                <input type="text" id="note" name="note" maxlength="255"
                                    value="{{ $old['note'] }}"
                                    placeholder="Opsional, mis. galon dibuka untuk eceran bengkel">
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="p-form-side">
                    <section class="p-card p-section">
                        <div class="p-section-head">
                            <span class="p-section-icon"><i data-lucide="calculator"></i></span>
                            <div>
                                <h3>Hasil</h3>
                                <p>Dihitung otomatis.</p>
                            </div>
                        </div>

                        <dl class="p-sim" aria-live="polite">
                            <div>
                                <dt>Stok kemasan</dt>
                                <dd id="simSource">–</dd>
                            </div>
                            <div>
                                <dt>Stok eceran</dt>
                                <dd id="simTarget">–</dd>
                            </div>
                            <div>
                                <dt>Harga beli eceran</dt>
                                <dd id="simCost">–</dd>
                            </div>
                            <div class="is-total">
                                <dt>Nilai modal dipindah</dt>
                                <dd id="simValue">Rp 0</dd>
                            </div>
                        </dl>

                        <p class="p-send-note is-on" id="simNote">Pilih produk kemasan dan eceran.</p>

                        <div class="p-form-actions">
                            <button type="submit" class="p-btn p-btn-primary" id="convSubmit">
                                <i data-lucide="package-open"></i>
                                Buka Kemasan
                            </button>
                        </div>
                    </section>
                </aside>
            </div>
        </form>

        {{-- ===================== RIWAYAT ===================== --}}
        <section class="p-card" style="margin-top: 18px">
            <div class="p-section-head" style="padding: 18px 20px 0">
                <span class="p-section-icon"><i data-lucide="history"></i></span>
                <div>
                    <h3>Riwayat</h3>
                    <p>Batalkan jika salah pilih — stok dikembalikan selama eceran belum terjual.</p>
                </div>
            </div>
            <div class="p-table-wrap">
                <table class="p-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Kemasan dibuka</th>
                            <th>Menjadi eceran</th>
                            <th class="text-end">Harga beli / eceran</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($conversions as $c)
                            <tr @class(['cv-reverted' => $c->isReverted()])>
                                <td data-label="Waktu">
                                    {{ $c->created_at->translatedFormat('d M Y H:i') }}
                                    <small class="p-sub">{{ $c->user->name ?? '-' }}</small>
                                </td>
                                <td data-label="Kemasan">
                                    <strong>{{ $qty($c->source_qty) }} {{ $c->source->base_unit ?? 'PCS' }}</strong>
                                    <small class="p-sub">{{ $c->source->name ?? 'Produk dihapus' }}</small>
                                </td>
                                <td data-label="Eceran">
                                    <strong>+{{ $qty($c->target_qty) }} {{ $c->target->base_unit ?? '' }}</strong>
                                    <small class="p-sub">{{ $c->target->name ?? 'Produk dihapus' }} · isi
                                        {{ $qty($c->ratio) }}</small>
                                    @if ($c->note)
                                        <small class="p-sub">{{ $c->note }}</small>
                                    @endif
                                </td>
                                <td class="text-end" data-label="Harga beli">
                                    {{ $c->unit_cost !== null ? $rp($c->unit_cost) : '–' }}
                                </td>
                                <td class="cell-actions">
                                    @if ($c->isReverted())
                                        <span class="p-stock is-mid">Dibatalkan</span>
                                    @else
                                        <form method="POST" action="{{ route('stock-conversions.destroy', $c->id) }}"
                                            class="js-cv-revert"
                                            data-time="{{ $c->created_at->translatedFormat('d M Y H:i') }}"
                                            data-user="{{ $c->user->name ?? '-' }}"
                                            data-source-id="{{ $c->source_product_id }}"
                                            data-source-name="{{ $c->source->name ?? 'Produk dihapus' }}"
                                            data-source-unit="{{ $c->source->base_unit ?? 'PCS' }}"
                                            data-source-qty="{{ (float) $c->source_qty }}"
                                            data-target-id="{{ $c->target_product_id }}"
                                            data-target-name="{{ $c->target->name ?? 'Produk dihapus' }}"
                                            data-target-unit="{{ $c->target->base_unit ?? '' }}"
                                            data-target-qty="{{ (float) $c->target_qty }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-icon-btn is-danger" title="Batalkan">
                                                <i data-lucide="undo-2"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="p-empty-row">
                                <td colspan="5">
                                    <div class="p-empty">
                                        <span class="p-empty-icon"><i data-lucide="package-open"></i></span>
                                        <h4>Belum ada kemasan yang dibuka</h4>
                                        <p>Contoh: 1 GALON oli isi 20 → 20 LITER oli eceran.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($conversions->hasPages())
                <div style="padding: 0 20px 18px">{{ $conversions->links() }}</div>
            @endif
        </section>
    </div>

    {{-- ================= MODAL BATAL BUKA KEMASAN ================= --}}
    <div class="cvx" id="cvRevertModal" hidden>
        <div class="cvx-backdrop" data-cvx-close></div>
        <div class="cvx-dialog" role="dialog" aria-modal="true" aria-labelledby="cvxTitle">
            <div class="cvx-head">
                <span class="cvx-icon"><i data-lucide="undo-2"></i></span>
                <div class="cvx-head-text">
                    <span class="cvx-eyebrow">Riwayat Buka Kemasan</span>
                    <h3 id="cvxTitle">Batalkan buka kemasan?</h3>
                    <p id="cvxMeta">–</p>
                </div>
                <button type="button" class="cvx-x" data-cvx-close aria-label="Tutup"><i data-lucide="x"></i></button>
            </div>

            <div class="cvx-body">
                <p class="cvx-lead">Stok kedua produk dikembalikan seperti sebelum kemasan dibuka.</p>

                <div class="cvx-flow">
                    <div class="cvx-card is-up">
                        <span class="cvx-tag"><i data-lucide="package"></i> Kemasan</span>
                        <strong id="cvxSrcName">–</strong>
                        <div class="cvx-move">
                            <span id="cvxSrcFrom">–</span>
                            <i data-lucide="arrow-right"></i>
                            <b id="cvxSrcTo">–</b>
                        </div>
                        <em id="cvxSrcDelta">–</em>
                    </div>
                    <div class="cvx-card is-down">
                        <span class="cvx-tag"><i data-lucide="droplet"></i> Eceran</span>
                        <strong id="cvxTgtName">–</strong>
                        <div class="cvx-move">
                            <span id="cvxTgtFrom">–</span>
                            <i data-lucide="arrow-right"></i>
                            <b id="cvxTgtTo">–</b>
                        </div>
                        <em id="cvxTgtDelta">–</em>
                    </div>
                </div>

                <div class="cvx-note" id="cvxNote">
                    <i data-lucide="info"></i>
                    <span>Catatan tetap tersimpan di riwayat dengan label <b>Dibatalkan</b>. Pembatalan tidak bisa
                        diulang.</span>
                </div>
                <div class="cvx-note is-block" id="cvxBlock" hidden>
                    <i data-lucide="octagon-alert"></i>
                    <span id="cvxBlockText">–</span>
                </div>
            </div>

            <div class="cvx-foot">
                <button type="button" class="cvx-btn is-ghost" data-cvx-close>Kembali</button>
                <button type="button" class="cvx-btn is-danger" id="cvxConfirm">
                    <i data-lucide="undo-2"></i> Ya, batalkan
                </button>
            </div>
        </div>
    </div>

    <style>
        .cv-filter {
            margin-bottom: 8px;
        }

        .cv-unit-input {
            display: flex;
            align-items: stretch;
        }

        .cv-unit-input input {
            flex: 1;
            min-width: 0;
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }

        .cv-unit-input span {
            display: inline-flex;
            align-items: center;
            padding: 0 12px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid var(--p-stroke, #e5e7eb);
            border-left: 0;
            border-radius: 0 12px 12px 0;
            background: var(--p-soft, #f4f5f7);
            color: #4b5563;
            white-space: nowrap;
        }

        tr.cv-reverted td {
            opacity: .5;
        }

        tr.cv-reverted td.cell-actions {
            opacity: 1;
        }

        #simNote.is-warn {
            color: #b45309;
        }

        /* ---------- modal batal (premium) ---------- */
        .cvx {
            position: fixed;
            inset: 0;
            z-index: 3000;
            display: grid;
            place-items: center;
            padding: 20px;
        }

        .cvx[hidden] {
            display: none;
        }

        .cvx-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(8, 10, 13, .62);
            backdrop-filter: blur(6px);
            animation: cvxFade .2s ease;
        }

        .cvx-dialog {
            position: relative;
            width: 100%;
            max-width: 540px;
            background: #fff;
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 40px 90px rgba(0, 0, 0, .35), 0 0 0 1px rgba(255, 255, 255, .04);
            animation: cvxPop .26s cubic-bezier(.2, .9, .3, 1.15);
        }

        .cvx-head {
            position: relative;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 24px 24px 22px;
            background: radial-gradient(120% 140% at 100% 0%, rgba(255, 106, 26, .28), transparent 55%), linear-gradient(135deg, #0c0e11, #1b1f26);
            color: #fff;
        }

        .cvx-icon {
            flex: none;
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #ff6a1a, #ffae1f);
            box-shadow: 0 10px 24px rgba(255, 106, 26, .45);
        }

        .cvx-icon svg {
            width: 24px;
            height: 24px;
            color: #fff;
        }

        .cvx-head-text {
            flex: 1;
            min-width: 0;
        }

        .cvx-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #ffae1f;
        }

        .cvx-head h3 {
            margin: 4px 0 4px;
            font-size: 21px;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }

        .cvx-head p {
            margin: 0;
            font-size: 13px;
            color: #9aa3ae;
        }

        .cvx-x {
            flex: none;
            width: 36px;
            height: 36px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .06);
            color: #fff;
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: .15s;
        }

        .cvx-x:hover {
            background: rgba(255, 255, 255, .14);
        }

        .cvx-x svg {
            width: 18px;
            height: 18px;
        }

        .cvx-body {
            padding: 22px 24px 6px;
        }

        .cvx-lead {
            margin: 0 0 16px;
            color: #4b5563;
            font-size: 14px;
        }

        .cvx-flow {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .cvx-card {
            border-radius: 18px;
            padding: 14px 16px;
            border: 1px solid #e5e7eb;
            background: #f8f9fb;
            min-width: 0;
        }

        .cvx-card.is-up {
            background: linear-gradient(180deg, #ecfdf3, #fff);
            border-color: #a7f0c1;
        }

        .cvx-card.is-down {
            background: linear-gradient(180deg, #fff4ec, #fff);
            border-color: #ffc9a6;
        }

        .cvx-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6b7380;
        }

        .cvx-tag svg {
            width: 14px;
            height: 14px;
        }

        .cvx-card strong {
            display: block;
            margin: 6px 0 10px;
            font-size: 14px;
            color: #0c0e11;
            line-height: 1.35;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .cvx-move {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #6b7380;
            flex-wrap: wrap;
        }

        .cvx-move svg {
            width: 15px;
            height: 15px;
            color: #9aa3ae;
        }

        .cvx-move b {
            font-size: 17px;
            font-weight: 800;
            color: #0c0e11;
        }

        .cvx-card em {
            display: inline-block;
            margin-top: 8px;
            font-style: normal;
            font-size: 12px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 999px;
        }

        .cvx-card.is-up em {
            background: #dcfce7;
            color: #15803d;
        }

        .cvx-card.is-down em {
            background: #ffe4d2;
            color: #c2410c;
        }

        .cvx-note {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin: 16px 0 0;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f4f5f7;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.5;
        }

        .cvx-note svg {
            flex: none;
            width: 17px;
            height: 17px;
            margin-top: 1px;
            color: #6b7380;
        }

        .cvx-note[hidden] {
            display: none;
        }

        .cvx-note.is-block {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .cvx-note.is-block svg {
            color: #dc2626;
        }

        .cvx-foot {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 18px 24px 22px;
        }

        .cvx-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 46px;
            padding: 0 20px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: .15s;
        }

        .cvx-btn svg {
            width: 17px;
            height: 17px;
        }

        .cvx-btn.is-ghost {
            background: #fff;
            border-color: #e5e7eb;
            color: #0c0e11;
        }

        .cvx-btn.is-ghost:hover {
            background: #f4f5f7;
        }

        .cvx-btn.is-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
            box-shadow: 0 10px 22px rgba(220, 38, 38, .32);
        }

        .cvx-btn.is-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(220, 38, 38, .38);
        }

        .cvx-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @keyframes cvxFade {
            from {
                opacity: 0;
            }
        }

        @keyframes cvxPop {
            from {
                opacity: 0;
                transform: translateY(18px) scale(.96);
            }
        }

        @media (max-width: 560px) {
            .cvx {
                align-items: end;
                padding: 0;
            }

            .cvx-dialog {
                border-radius: 24px 24px 0 0;
                max-width: none;
            }

            .cvx-flow {
                grid-template-columns: 1fr;
            }

            .cvx-foot {
                flex-direction: column-reverse;
            }

            .cvx-btn {
                width: 100%;
            }
        }
    </style>

    @include('pages.stock-conversions._guide')
@endsection

@push('scripts')
    <script>
        (() => {
            const PRODUCTS = @json($productData);
            const PAIRS = @json($lastPairs);
            const OLD = @json($old);
            const byId = new Map(PRODUCTS.map((p) => [String(p.id), p]));

            const $ = (id) => document.getElementById(id);
            const src = $('source_product_id'),
                tgt = $('target_product_id');
            const qty = $('source_qty'),
                ratio = $('ratio');

            const MEASURE = ['LITER', 'LTR', 'L', 'ML', 'METER', 'M', 'CM', 'KG', 'KILO', 'GR', 'GRAM', 'ONS'];
            const num = (n) => (Number(n) || 0).toLocaleString('id-ID', {
                maximumFractionDigits: 2
            });
            const rp = (n) => 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');

            const fill = (select, filter = '', keep = select.value, preferMeasure = false) => {
                const f = filter.trim().toLowerCase();
                const list = PRODUCTS.filter((p) => !f || p.name.toLowerCase().includes(f) || (p.code || '')
                    .toLowerCase().includes(f));
                // produk eceran: satuan ukuran ditaruh di atas
                if (preferMeasure) list.sort((a, b) => (MEASURE.includes(b.unit) - MEASURE.includes(a.unit)) || a
                    .name.localeCompare(b.name));
                select.innerHTML = '<option value="">— pilih produk —</option>' + list.map((p) =>
                    `<option value="${p.id}">${p.name.replace(/</g, '&lt;')} · ${num(p.stock)} ${p.unit}${p.active ? '' : ' (nonaktif)'}</option>`
                ).join('');
                if (keep && list.some((p) => String(p.id) === String(keep))) select.value = keep;
            };

            document.querySelectorAll('.cv-filter').forEach((input) => {
                input.addEventListener('input', () => {
                    const sel = $(input.dataset.for);
                    fill(sel, input.value, sel.value, sel === tgt);
                    update();
                });
            });

            const update = () => {
                const s = byId.get(src.value),
                    t = byId.get(tgt.value);
                const q = Number(qty.value) || 0,
                    r = Number(ratio.value) || 0;
                $('sourceUnit').textContent = s ? s.unit : 'PCS';
                $('ratioUnit').textContent = t ? t.unit : 'eceran';
                $('sourceInfo').textContent = s ?
                    `Stok ${num(s.stock)} ${s.unit} · harga beli ${s.cost ? rp(s.cost) : 'belum ada'}` :
                    '';

                const note = $('simNote');
                note.classList.remove('is-warn');
                if (!s || !t) {
                    ['simSource', 'simTarget', 'simCost'].forEach((id) => $(id).textContent = '–');
                    $('simValue').textContent = 'Rp 0';
                    note.textContent = 'Pilih produk kemasan dan eceran.';
                    return;
                }
                const add = q * r;
                const unitCost = s.cost && r > 0 ? s.cost / r : null;
                $('simSource').textContent = `${num(s.stock)} → ${num(s.stock - q)} ${s.unit}`;
                $('simTarget').textContent = `${num(t.stock)} → ${num(t.stock + add)} ${t.unit}`;
                $('simCost').textContent = unitCost ? `${rp(unitCost)} / ${t.unit}` : 'tidak berubah';
                $('simValue').textContent = rp((s.cost || 0) * q);

                if (s.id === t.id) {
                    note.textContent = 'Produk eceran harus berbeda dari produk kemasan.';
                    note.classList.add('is-warn');
                } else if (q > s.stock) {
                    note.textContent = `Stok ${s.name} hanya ${num(s.stock)} ${s.unit}.`;
                    note.classList.add('is-warn');
                } else if (!MEASURE.includes(t.unit)) {
                    note.textContent = `Perhatian: satuan produk eceran ${t.unit}. Biasanya LITER / KG / METER.`;
                    note.classList.add('is-warn');
                } else if (!s.cost) {
                    note.textContent = 'Produk kemasan belum punya harga beli — harga beli eceran tidak diubah.';
                    note.classList.add('is-warn');
                } else {
                    note.textContent = `${num(q)} ${s.unit} dibuka → +${num(add)} ${t.unit} ${t.name}.`;
                }
            };

            src.addEventListener('change', () => {
                const pair = PAIRS[src.value];
                if (pair) {
                    if (!tgt.value) {
                        fill(tgt, '', String(pair.target), true);
                        tgt.value = String(pair.target);
                    }
                    if (!ratio.value) ratio.value = pair.ratio;
                }
                update();
            });
            [tgt, qty, ratio].forEach((el) => el.addEventListener('input', update));
            tgt.addEventListener('change', update);

            fill(src, '', OLD.source ? String(OLD.source) : '');
            fill(tgt, '', OLD.target ? String(OLD.target) : '', true);
            if (src.value && !OLD.ratio) src.dispatchEvent(new Event('change'));
            update();

            /* ---------- modal batal buka kemasan ---------- */
            const modal = $('cvRevertModal');
            const okBtn = $('cvxConfirm');
            let pending = null;

            const closeModal = () => {
                modal.hidden = true;
                document.body.style.overflow = '';
                pending = null;
            };
            modal.querySelectorAll('[data-cvx-close]').forEach((el) => el.addEventListener('click', closeModal));
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.hidden) closeModal();
            });

            document.querySelectorAll('form.js-cv-revert').forEach((form) => {
                form.addEventListener('submit', (e) => {
                    if (form.dataset.ok === '1') return; // sudah dikonfirmasi
                    e.preventDefault();
                    const d = form.dataset;
                    const sq = Number(d.sourceQty) || 0,
                        tq = Number(d.targetQty) || 0;
                    const s = byId.get(String(d.sourceId)),
                        t = byId.get(String(d.targetId));
                    const sNow = s ? Number(s.stock) : null,
                        tNow = t ? Number(t.stock) : null;

                    $('cvxMeta').textContent = `${d.time} · oleh ${d.user}`;
                    $('cvxSrcName').textContent = d.sourceName;
                    $('cvxTgtName').textContent = d.targetName;
                    $('cvxSrcFrom').textContent = sNow === null ? '–' : `${num(sNow)} ${d.sourceUnit}`;
                    $('cvxSrcTo').textContent = sNow === null ? '–' :
                        `${num(sNow + sq)} ${d.sourceUnit}`;
                    $('cvxTgtFrom').textContent = tNow === null ? '–' : `${num(tNow)} ${d.targetUnit}`;
                    $('cvxTgtTo').textContent = tNow === null ? '–' :
                        `${num(Math.max(0, tNow - tq))} ${d.targetUnit}`;
                    $('cvxSrcDelta').textContent = `+${num(sq)} ${d.sourceUnit} kembali`;
                    $('cvxTgtDelta').textContent = `−${num(tq)} ${d.targetUnit} ditarik`;

                    const blocked = tNow !== null && tNow + 0.0001 < tq;
                    $('cvxBlock').hidden = !blocked;
                    $('cvxNote').hidden = blocked;
                    $('cvxBlockText').textContent = blocked ?
                        `Tidak bisa dibatalkan: stok ${d.targetName} tinggal ${num(tNow)} ${d.targetUnit}, sebagian dari ${num(tq)} ${d.targetUnit} sudah terjual. Koreksi stok lewat Edit Produk bila perlu.` :
                        '';
                    okBtn.disabled = blocked;

                    pending = form;
                    modal.hidden = false;
                    document.body.style.overflow = 'hidden';
                    window.refreshIcons?.();
                    (blocked ? modal.querySelector('.cvx-btn.is-ghost') : okBtn).focus();
                });
            });

            okBtn.addEventListener('click', () => {
                if (!pending) return;
                const form = pending;
                form.dataset.ok = '1';
                okBtn.disabled = true;
                okBtn.innerHTML = 'Membatalkan…';
                window.showLoading?.();
                form.submit();
            });
        })();
    </script>
@endpush
