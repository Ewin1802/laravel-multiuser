{{-- ============================================================
     PANDUAN MULTI-SATUAN — studi kasus rokok (modal)
     Buka dengan: data-guide-open="multiUnitGuide" (+ data-guide-tab="produk|beli|jual|awal")
     Boleh di-include di beberapa halaman; hanya tercetak sekali.
============================================================ --}}
@once
    <div class="p-modal s-guide" id="multiUnitGuide" aria-hidden="true">
        <div class="p-modal-backdrop" data-guide-close></div>

        <div class="p-modal-dialog is-wide crud-page" role="dialog" aria-modal="true" aria-labelledby="multiUnitGuideTitle">
            <div class="s-guide-hero">
                <span class="s-guide-glow"></span>
                <div class="s-guide-hero-top">
                    <span class="s-guide-badge"><i data-lucide="sparkles"></i> Studi kasus</span>
                    <button type="button" class="s-guide-close" data-guide-close aria-label="Tutup">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <h2 id="multiUnitGuideTitle">Rokok: beli per slop,<br>jual slop · bungkus · batang</h2>
                <p>Contoh <b>Sampoerna Mild isi 16</b>. Semua harga hanya contoh — sesuaikan dengan harga di toko.</p>

                <div class="s-guide-tabs" role="tablist">
                    <button type="button" role="tab" class="is-active" data-guide-tab-btn="produk">
                        <i data-lucide="package"></i> 1. Produk
                    </button>
                    <button type="button" role="tab" data-guide-tab-btn="beli">
                        <i data-lucide="truck"></i> 2. Stok masuk
                    </button>
                    <button type="button" role="tab" data-guide-tab-btn="jual">
                        <i data-lucide="shopping-cart"></i> 3. Jual
                    </button>
                    <button type="button" role="tab" data-guide-tab-btn="awal">
                        <i data-lucide="calculator"></i> Stok di rak
                    </button>
                </div>
            </div>

            <div class="p-modal-body s-guide-body">
                {{-- 1. TAMBAH PRODUK --}}
                <div class="s-guide-pane is-active" data-guide-pane="produk">
                    <p class="s-guide-where"><i data-lucide="map-pin"></i> Produk → <b>Tambah Produk</b></p>

                    <div class="s-tbl-title">Informasi Produk</div>
                    <dl class="s-fields">
                        <div><dt>Nama Produk</dt><dd>Sampoerna Mild 16</dd></div>
                        <div><dt>Kode Produk</dt><dd>SAMP-MILD16 <small>(opsional)</small></dd></div>
                        <div><dt>Barcode</dt><dd><small>kosongkan — batang tidak berbarcode, sistem membuat kode toko</small></dd></div>
                        <div><dt>Kategori</dt><dd>Rokok</dd></div>
                        <div><dt>Deskripsi</dt><dd>Sampoerna Mild, 16 batang per bungkus</dd></div>
                    </dl>

                    <div class="s-tbl-title">Satuan Terkecil, Harga &amp; Stok</div>
                    <dl class="s-fields">
                        <div class="is-key"><dt>Satuan Terkecil</dt><dd>BATANG</dd></div>
                        <div><dt>Harga Jual per BATANG</dt><dd>2.500</dd></div>
                        <div><dt>Harga Beli (Modal)</dt><dd><small>kosongkan — terisi dari Stok Masuk</small></dd></div>
                        <div><dt>Stok Awal</dt><dd>0</dd></div>
                    </dl>

                    <div class="s-tbl-title">Satuan Jual Lain <small>(klik <b>Tambah satuan</b> dua kali)</small></div>
                    <div class="s-tbl-wrap">
                        <table class="s-tbl">
                            <thead><tr><th>Nama satuan</th><th>Isi (BATANG)</th><th>Harga jual</th><th>Barcode</th></tr></thead>
                            <tbody>
                                <tr><td><b>BUNGKUS</b></td><td>16</td><td>36000</td><td>scan barcode bungkus</td></tr>
                                <tr><td><b>SLOP</b></td><td class="is-hl">160</td><td>345000</td><td>scan barcode slop</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="s-guide-note is-warn">
                        <i data-lucide="triangle-alert"></i>
                        <span>Isi SLOP adalah <b>160</b>, bukan 10 — selalu dihitung dalam batang (10 bungkus × 16 batang).
                            <b>Lacak tanggal expire</b> biarkan mati, lalu klik <b>Simpan Produk</b>.</span>
                    </div>
                </div>

                {{-- 2. STOK MASUK --}}
                <div class="s-guide-pane" data-guide-pane="beli" hidden>
                    <p class="s-guide-where"><i data-lucide="map-pin"></i> Stok Masuk → <b>Catat Stok Masuk</b></p>
                    <p class="s-guide-lead">Membeli <b>5 slop</b> dari supplier, harga <b>Rp 320.000 / slop</b>:</p>

                    <dl class="s-fields">
                        <div><dt>Supplier, Tanggal Terima</dt><dd><small>sesuai nota</small></dd></div>
                        <div><dt>Pembayaran</dt><dd>Lunas / Tempo</dd></div>
                        <div><dt>Cari produk</dt><dd>sampoerna <small>lalu Enter</small></dd></div>
                        <div class="is-key"><dt>Satuan</dt><dd>SLOP (isi 160 BATANG)</dd></div>
                        <div><dt>Jumlah</dt><dd>5 <small>→ muncul "= 800 BATANG"</small></dd></div>
                        <div><dt>Harga beli / SLOP</dt><dd>320.000</dd></div>
                    </dl>

                    <div class="s-calc">
                        <div class="s-calc-row"><span>Setelah disimpan</span><b>5 SLOP × Rp 320.000</b></div>
                        <div class="s-calc-arrow"><i data-lucide="arrow-down"></i> 1 SLOP = 160 BATANG</div>
                        <div class="s-calc-grid">
                            <div><small>Stok</small><strong>800 BATANG</strong></div>
                            <div><small>Tampil sebagai</small><strong>5 SLOP</strong></div>
                            <div><small>Modal / batang</small><strong>Rp 2.000</strong></div>
                        </div>
                    </div>
                </div>

                {{-- 3. JUAL --}}
                <div class="s-guide-pane" data-guide-pane="jual" hidden>
                    <p class="s-guide-where"><i data-lucide="map-pin"></i> Aplikasi kasir</p>
                    <div class="s-tbl-wrap">
                        <table class="s-tbl">
                            <thead><tr><th>Cara</th><th>Di keranjang</th><th>Stok terpotong</th><th>Untung</th></tr></thead>
                            <tbody>
                                <tr><td>Ketuk produk → pilih BATANG</td><td>1 BATANG Rp 2.500</td><td>1 batang</td><td class="is-ok">Rp 500</td></tr>
                                <tr><td>Scan barcode bungkus</td><td>1 BUNGKUS Rp 36.000</td><td>16 batang</td><td class="is-ok">Rp 4.000</td></tr>
                                <tr><td>Scan barcode slop</td><td>1 SLOP Rp 345.000</td><td>160 batang</td><td class="is-ok">Rp 25.000</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="s-calc">
                        <div class="s-calc-row"><span>Hari ini terjual</span><b>1 slop · 2 bungkus · 3 batang</b></div>
                        <div class="s-calc-arrow"><i data-lucide="arrow-down"></i> 800 − 160 − 32 − 3</div>
                        <div class="s-calc-grid is-two">
                            <div><small>Sisa stok</small><strong>605 BATANG</strong></div>
                            <div><small>Tampil sebagai</small><strong>3 SLOP, 7 BUNGKUS, 13 BATANG</strong></div>
                        </div>
                    </div>
                </div>

                {{-- 4. STOK YANG SUDAH ADA DI RAK --}}
                <div class="s-guide-pane" data-guide-pane="awal" hidden>
                    <p class="s-guide-lead">Sudah ada rokok di rak sebelum mulai memakai aplikasi? Hitung isinya lalu
                        <b>ubah ke jumlah batang</b>.</p>

                    <div class="s-calc">
                        <div class="s-calc-row"><span>Isi rak</span><b>2 slop · 3 bungkus · 5 batang</b></div>
                        <div class="s-calc-arrow"><i data-lucide="arrow-down"></i> 2 × 160 + 3 × 16 + 5</div>
                        <div class="s-calc-grid is-two">
                            <div><small>Stok Awal</small><strong>373</strong></div>
                            <div><small>Harga Beli (modal / batang)</small><strong>2000</strong></div>
                        </div>
                    </div>

                    <p class="s-guide-lead">Isi Harga Beli juga, supaya laba dari penjualan pertama sudah benar.</p>

                    <div class="s-guide-note is-warn">
                        <i data-lucide="triangle-alert"></i>
                        <span><b>Cek jumlah batang di kemasan dulu.</b> Ada varian isi 12 dan isi 16 — begitu juga merek lain.
                            Kalau isi 12: BUNGKUS diisi <b>12</b>, SLOP diisi <b>120</b>.</span>
                    </div>
                </div>
            </div>

            <div class="p-modal-foot">
                <button type="button" class="p-btn p-btn-primary" data-guide-close>Mengerti</button>
            </div>
        </div>
    </div>
@endonce

@include('partials.guide-script')
