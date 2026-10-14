@extends('layouts.app')

@section('title', 'Expire & Barang Keluar')

@section('content')
    @php
        $qty = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
        $reasons = \App\Models\StockWriteOff::REASONS;
    @endphp

    <div class="crud-page">
        <div class="p-header">
            <div>
                <span class="p-eyebrow">Stok & Pembelian</span>
                <h1>Expire & Barang Keluar</h1>
                <p>Batch kedaluwarsa otomatis tidak dijual kasir. Catat sisanya sebagai rusak atau retur supaya stok sesuai.
                </p>
            </div>
            <div class="p-header-actions">
                <button type="button" class="s-guide-trigger" data-guide-open="expiryGuide">
                    <span class="s-guide-trigger-icon"><i data-lucide="sparkles"></i></span>
                    <span>Panduan stok expire</span>
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="p-notice is-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <div><span>{{ $errors->first() }}</span></div>
            </div>
        @endif

        {{-- SUDAH KEDALUWARSA --}}
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="calendar-x"></i></span>
                <div>
                    <h3>Sudah kedaluwarsa <span class="s-badge tone-red">{{ $expired->count() }}</span></h3>
                    <p>Tidak ikut dijual. Pilih rusak (dibuang) atau retur (dikembalikan ke supplier).
                        <button type="button" class="s-guide-link" data-guide-open="expiryGuide"
                            data-guide-tab="expire">Jumlah di rak beda dengan sistem?</button>
                    </p>
                </div>
            </div>

            <div class="p-table-wrap">
                <table class="p-table p-table-plain">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Expire</th>
                            <th class="text-end">Sisa</th>
                            <th class="text-end">Catat sebagai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expired as $b)
                            <tr>
                                <td><strong>{{ $b->product->name }}</strong>
                                    @if ($b->batch_code)
                                        <br><small class="s-mono">Batch {{ $b->batch_code }}</small>
                                    @endif
                                </td>
                                <td><span class="s-badge tone-red">{{ $b->expiry_date->format('d/m/Y') }}</span></td>
                                <td class="text-end">{{ $qty($b->remaining_quantity) }} {{ $b->product->base_unit }}</td>
                                <td class="text-end">
                                    <div class="s-actions-row" style="justify-content:flex-end">
                                        @foreach (['damaged' => 'Rusak', 'returned' => 'Retur'] as $reason => $label)
                                            <form method="POST" action="{{ route('expiry.batch.write-off', $b->id) }}"
                                                data-confirm="Catat sisa {{ $b->product->name }} sebagai {{ strtolower($label) }}?">
                                                @csrf
                                                <input type="hidden" name="reason" value="{{ $reason }}">
                                                <button type="submit"
                                                    class="p-btn p-btn-ghost">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"><span class="p-hint">Tidak ada batch kedaluwarsa.</span></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- MENDEKATI EXPIRE --}}
        <section class="p-card p-section">
            <div class="p-section-head">
                <span class="p-section-icon"><i data-lucide="calendar-clock"></i></span>
                <div>
                    <h3>Mendekati expire <span class="s-badge tone-amber">{{ $soon->count() }}</span></h3>
                    <p>Sesuai batas peringatan tiap produk. Kasir menjual batch ini lebih dulu (FEFO).
                        <button type="button" class="s-guide-link" data-guide-open="expiryGuide"
                            data-guide-tab="rak">Barang lama &amp; baru tercampur di rak?</button>
                    </p>
                </div>
            </div>

            <div class="p-table-wrap">
                <table class="p-table p-table-plain">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Expire</th>
                            <th>Sisa hari</th>
                            <th class="text-end">Sisa stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($soon as $b)
                            @php $days = (int) $today->diffInDays($b->expiry_date, false); @endphp
                            <tr>
                                <td><strong>{{ $b->product->name }}</strong></td>
                                <td>{{ $b->expiry_date->format('d/m/Y') }}</td>
                                <td><span
                                        class="s-badge {{ $days <= 7 ? 'tone-red' : 'tone-amber' }}">{{ $days === 0 ? 'Hari ini' : $days . ' hari' }}</span>
                                </td>
                                <td class="text-end">{{ $qty($b->remaining_quantity) }} {{ $b->product->base_unit }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"><span class="p-hint">Tidak ada batch yang mendekati expire.</span></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="p-form-layout">
            <div class="p-form-main">
                {{-- RIWAYAT --}}
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="history"></i></span>
                        <div>
                            <h3>Riwayat barang keluar</h3>
                        </div>
                    </div>
                    <div class="p-table-wrap">
                        <table class="p-table p-table-plain">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Produk</th>
                                    <th>Alasan</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($writeOffs as $w)
                                    <tr>
                                        <td>{{ $w->occurred_at->translatedFormat('d M Y H:i') }}<br><small>{{ $w->user->name ?? 'Kasir (HP)' }}</small>
                                        </td>
                                        <td><strong>{{ $w->product->name ?? '-' }}</strong>
                                            @if ($w->batch?->expiry_date)
                                                <br><small>Exp {{ $w->batch->expiry_date->format('d/m/Y') }}</small>
                                            @endif
                                            @if ($w->note)
                                                <br><small>{{ $w->note }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $reasons[$w->reason] ?? $w->reason }}</td>
                                        <td class="text-end">{{ $qty($w->quantity) }} {{ $w->product->base_unit ?? '' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4"><span class="p-hint">Belum ada barang keluar.</span></td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $writeOffs->links('vendor.pagination.custom') }}
                </section>
            </div>

            <aside class="p-form-side">
                {{-- CATAT MANUAL --}}
                <section class="p-card p-section">
                    <div class="p-section-head">
                        <span class="p-section-icon"><i data-lucide="package-minus"></i></span>
                        <div>
                            <h3>Catat barang keluar</h3>
                            <p>Jumlah dalam satuan terkecil. Produk ber-expire dipotong dari batch terdekat.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('expiry.store') }}">
                        @csrf
                        <div class="p-field">
                            <label for="wo_product">Produk</label>
                            <select id="wo_product" name="product_id" required>
                                <option value="">Pilih produk</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->base_unit }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p-field">
                            <label for="wo_qty">Jumlah</label>
                            <input type="number" id="wo_qty" name="quantity" min="0.001" step="any" required
                                inputmode="decimal">
                        </div>
                        <div class="p-field">
                            <label for="wo_reason">Alasan</label>
                            <select id="wo_reason" name="reason" required>
                                @foreach ($reasons as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="p-field">
                            <label for="wo_note">Catatan</label>
                            <input type="text" id="wo_note" name="note" maxlength="255" placeholder="Opsional">
                        </div>
                        <div class="p-form-actions">
                            <button type="submit" class="p-btn p-btn-primary"><i data-lucide="save"></i> Simpan</button>
                        </div>
                    </form>
                </section>
            </aside>
        </div>
    </div>

    {{-- ============================================================
         PANDUAN STOK EXPIRE (modal)
    ============================================================ --}}
    <div class="p-modal s-guide" id="expiryGuide" aria-hidden="true">
        <div class="p-modal-backdrop" data-guide-close></div>

        <div class="p-modal-dialog is-wide crud-page" role="dialog" aria-modal="true"
            aria-labelledby="expiryGuideTitle">
            <div class="s-guide-hero">
                <span class="s-guide-glow"></span>
                <div class="s-guide-hero-top">
                    <span class="s-guide-badge"><i data-lucide="sparkles"></i> Panduan</span>
                    <button type="button" class="s-guide-close" data-guide-close aria-label="Tutup">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <h2 id="expiryGuideTitle">Barang lama &amp; baru<br>dengan barcode yang sama</h2>
                <p>Sistem mencatat tanggal expire per batch, tapi tidak bisa membedakan botol yang di-scan.
                    Kasir selalu memotong batch terlama dulu (FEFO).</p>

                <div class="s-guide-tabs" role="tablist">
                    <button type="button" role="tab" class="is-active" data-guide-tab-btn="rak">
                        <i data-lucide="store"></i> Menata rak
                    </button>
                    <button type="button" role="tab" data-guide-tab-btn="expire">
                        <i data-lucide="scale"></i> Saat expire tiba
                    </button>
                </div>
            </div>

            <div class="p-modal-body s-guide-body">
                {{-- TAB 1: MENATA RAK --}}
                <div class="s-guide-pane is-active" data-guide-pane="rak">
                    <ol class="s-steps">
                        <li>
                            <span class="s-steps-icon"><i data-lucide="arrow-up-to-line"></i></span>
                            <div>
                                <strong>Pajang yang lama di depan</strong>
                                <p>Taruh barang lama di depan rak atau paling atas kulkas. Simpan barang baru di belakang
                                    atau di gudang sampai yang lama habis.</p>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon"><i data-lucide="tag"></i></span>
                            <div>
                                <strong>Beri tanda</strong>
                                <p>Tempel stiker warna atau tulis <span class="s-chip">EXP 14/10</span> di dus atau rak
                                    barang lama.</p>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon"><i data-lucide="badge-percent"></i></span>
                            <div>
                                <strong>Dorong supaya cepat laku</strong>
                                <p>Untuk batch di daftar kuning <em>Mendekati expire</em>, buat promo atau diskon supaya
                                    habis
                                    sebelum tanggalnya.</p>
                            </div>
                        </li>
                    </ol>
                </div>

                {{-- TAB 2: SAAT EXPIRE TIBA --}}
                <div class="s-guide-pane" data-guide-pane="expire" hidden>
                    <div class="s-guide-note">
                        <i data-lucide="info"></i>
                        <span>Kalau pembeli mengambil barang baru dari belakang, sistem tetap memotong batch lama.
                            <strong>Total stok tetap benar</strong>, hanya pembagian lama/baru yang bergeser.</span>
                    </div>

                    <div class="s-compare">
                        <div class="s-compare-head"><span></span><span>Sistem</span><span>Di rak</span></div>
                        <div><span>Lama <small>exp 14/10</small></span><b>38</b><b class="is-hl">48</b></div>
                        <div><span>Baru</span><b>24</b><b>14</b></div>
                        <div class="s-compare-total"><span>Total</span><b>62</b><b>62</b></div>
                    </div>

                    <ol class="s-steps is-numbered">
                        <li>
                            <span class="s-steps-icon">1</span>
                            <div>
                                <strong>Hitung barang lama di rak</strong>
                                <p>Hitung yang <em>benar-benar</em> tersisa dengan tanggal expire itu.</p>
                                <span class="s-steps-eg">Contoh: 48 botol</span>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon">2</span>
                            <div>
                                <strong>Keluarkan sisa versi sistem</strong>
                                <p>Di tabel merah <em>Sudah kedaluwarsa</em>, klik <b>Rusak</b> atau <b>Retur</b> pada batch
                                    itu.</p>
                                <span class="s-steps-eg">Sistem mencatat 38 → keluarkan 38</span>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon">3</span>
                            <div>
                                <strong>Catat selisihnya</strong>
                                <p>Lewat form <em>Catat barang keluar</em>: pilih produk, isi selisih, alasan Rusak/Retur.
                                    Sistem memotongnya dari batch berikutnya.</p>
                                <span class="s-steps-eg">48 − 38 = 10 botol</span>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>

            <div class="p-modal-foot">
                <button type="button" class="p-btn p-btn-primary" data-guide-close>Mengerti</button>
            </div>
        </div>
    </div>

    @include('partials.guide-script')
@endsection
