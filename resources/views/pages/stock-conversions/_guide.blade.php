{{-- ============================================================
     PANDUAN BUKA KEMASAN (modal)
     Buka dengan: data-guide-open="conversionGuide" (+ data-guide-tab="cara|dua|tiga")
============================================================ --}}
@once
    <div class="p-modal s-guide" id="conversionGuide" aria-hidden="true">
        <div class="p-modal-backdrop" data-guide-close></div>

        <div class="p-modal-dialog is-wide crud-page" role="dialog" aria-modal="true" aria-labelledby="conversionGuideTitle">
            <div class="s-guide-hero">
                <span class="s-guide-glow"></span>
                <div class="s-guide-hero-top">
                    <span class="s-guide-badge"><i data-lucide="sparkles"></i> Panduan</span>
                    <button type="button" class="s-guide-close" data-guide-close aria-label="Tutup">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <h2 id="conversionGuideTitle">Jual per kemasan<br>dan per eceran</h2>
                <p>Ada dua cara: <b>Satuan jual</b> (satu produk, beberapa satuan) atau <b>Buka Kemasan</b>
                    (dua produk terpisah). Pilih yang sesuai supaya stok tidak kacau.</p>

                <div class="s-guide-tabs" role="tablist">
                    <button type="button" role="tab" class="is-active" data-guide-tab-btn="cara">
                        <i data-lucide="split"></i> Pilih cara
                    </button>
                    <button type="button" role="tab" data-guide-tab-btn="dua">
                        <i data-lucide="layers-2"></i> 2 satuan
                    </button>
                    <button type="button" role="tab" data-guide-tab-btn="tiga">
                        <i data-lucide="layers-3"></i> 3 satuan (rokok)
                    </button>
                </div>
            </div>

            <div class="p-modal-body s-guide-body">
                {{-- TAB 1: PILIH CARA --}}
                <div class="s-guide-pane is-active" data-guide-pane="cara">
                    @include('pages.stock-conversions._rule')

                    <div class="s-choice">
                        <div class="s-choice-card is-recommended">
                            <span class="s-choice-tag">Disarankan</span>
                            <span class="s-steps-icon"><i data-lucide="boxes"></i></span>
                            <strong>Satuan jual</strong>
                            <small>1 produk · diatur di Produk → Edit</small>
                            <ul>
                                <li>Isi kemasan <b>tetap</b> (1 DUS selalu 24 botol)</li>
                                <li>Stok otomatis terpotong, <b>tidak perlu dibuka</b></li>
                                <li>Kasir pilih satuan / scan barcode kemasan</li>
                                <li>Wajib untuk produk <b>ber-expire</b></li>
                            </ul>
                            <p class="s-choice-eg">Rokok, air mineral, mi instan, susu kotak</p>
                        </div>
                        <div class="s-choice-card">
                            <span class="s-steps-icon is-dark"><i data-lucide="package-open"></i></span>
                            <strong>Buka Kemasan</strong>
                            <small>2 produk · dicatat di halaman ini</small>
                            <ul>
                                <li>Kemasan &amp; eceran <b>produk terpisah</b></li>
                                <li>Barang dijual <b>ditakar</b> (liter, kg, meter)</li>
                                <li>Stok eceran baru ada <b>setelah dibuka</b></li>
                                <li>Tidak bisa untuk produk ber-expire</li>
                            </ul>
                            <p class="s-choice-eg">Beras karung → kg, minyak jeriken → liter, kabel rol → meter</p>
                        </div>
                    </div>

                    <div class="s-guide-note">
                        <i data-lucide="info"></i>
                        <span>Pertanyaan kuncinya: <b>apakah isi kemasannya selalu sama dan dijual dari stok yang sama?</b>
                            Kalau ya, pakai <b>Satuan jual</b>. Buka Kemasan untuk barang yang setelah dibuka
                            dijual dengan cara berbeda.</span>
                    </div>
                </div>

                {{-- TAB 2: DUA SATUAN --}}
                <div class="s-guide-pane" data-guide-pane="dua" hidden>
                    <div class="s-case">
                        <span class="s-case-tag">Studi kasus</span>
                        <strong>Beras: dijual per KARUNG 25 kg dan per KG</strong>
                    </div>

                    <div class="s-option">
                        <div class="s-option-head"><span class="s-option-no">A</span> Satuan jual — 1 produk</div>
                        <div class="s-flow">
                            <span class="s-flow-box">KARUNG<small>isi 25</small></span>
                            <i data-lucide="arrow-right"></i>
                            <span class="s-flow-box is-base">KG<small>satuan terkecil</small></span>
                        </div>
                        <p>Produk <b>Beras</b>, satuan terkecil KG, tambah satuan KARUNG isi 25. Jual 1 karung → stok
                            −25 KG otomatis. <b>Tidak perlu Buka Kemasan.</b></p>
                    </div>

                    <div class="s-option">
                        <div class="s-option-head"><span class="s-option-no">B</span> Buka Kemasan — 2 produk</div>
                        <p>Kalau toko sudah punya produk <b>Beras Karung 25 kg</b> (satuan KARUNG) dan <b>Beras Eceran</b>
                            (satuan KG):</p>
                        <dl class="s-fields">
                            <div><dt>Produk kemasan</dt><dd>Beras Karung 25 kg</dd></div>
                            <div><dt>Jumlah dibuka</dt><dd>1</dd></div>
                            <div><dt>Isi per kemasan</dt><dd>25</dd></div>
                            <div><dt>Produk eceran</dt><dd>Beras Eceran</dd></div>
                            <div class="is-result"><dt>Hasil</dt><dd>Karung −1 · KG +25</dd></div>
                        </dl>
                        <p class="s-option-foot">Modal eceran dihitung otomatis: modal karung Rp 300.000 ÷ 25 =
                            <b>Rp 12.000 / KG</b>.</p>
                    </div>
                </div>

                {{-- TAB 3: TIGA SATUAN --}}
                <div class="s-guide-pane" data-guide-pane="tiga" hidden>
                    <div class="s-case">
                        <span class="s-case-tag">Studi kasus</span>
                        <strong>Rokok: dibeli per SLOP, dijual per SLOP, BUNGKUS, dan BATANG</strong>
                    </div>

                    <div class="s-option is-best">
                        <div class="s-option-head"><span class="s-option-no">A</span> Satuan jual — 1 produk
                            <span class="s-choice-tag is-inline">Disarankan</span></div>
                        <div class="s-flow">
                            <span class="s-flow-box">SLOP<small>isi 160</small></span>
                            <i data-lucide="arrow-right"></i>
                            <span class="s-flow-box">BUNGKUS<small>isi 16</small></span>
                            <i data-lucide="arrow-right"></i>
                            <span class="s-flow-box is-base">BATANG<small>terkecil</small></span>
                        </div>
                        <p>Satu produk, satuan terkecil BATANG. Isi satuan <b>dihitung dalam batang</b>: BUNGKUS 16,
                            SLOP 160 (bukan 10). Beli 5 SLOP lewat Stok Masuk → stok 800 BATANG. Kasir bisa langsung jual
                            batang dari bungkus yang dibuka — <b>tanpa Buka Kemasan</b>. Setelah terjual 1 bungkus
                            dan 4 batang, stok tampil "4 SLOP, 8 BUNGKUS, 12 BATANG".</p>
                    </div>

                    <p class="s-guide-lead"><button type="button" class="s-guide-link" data-guide-open="multiUnitGuide">Lihat contoh pengisian lengkap cara A (Sampoerna Mild)</button></p>

                    <div class="s-option">
                        <div class="s-option-head"><span class="s-option-no">B</span> Buka Kemasan — 3 produk, 2 langkah</div>
                        <p>Kalau rokoknya terdaftar sebagai tiga produk (Slop, Bungkus, Batang), buka bertahap:</p>
                        <ol class="s-steps is-numbered">
                            <li>
                                <span class="s-steps-icon">1</span>
                                <div>
                                    <strong>Buka SLOP → BUNGKUS</strong>
                                    <p>Kemasan <em>Rokok Slop</em>, jumlah 1, isi <b>10</b>, eceran <em>Rokok Bungkus</em>.</p>
                                    <span class="s-steps-eg">Slop −1 · Bungkus +10 · modal Rp 300.000 ÷ 10 = Rp 30.000</span>
                                </div>
                            </li>
                            <li>
                                <span class="s-steps-icon">2</span>
                                <div>
                                    <strong>Buka BUNGKUS → BATANG</strong>
                                    <p>Kemasan <em>Rokok Bungkus</em>, jumlah 1, isi <b>16</b>, eceran <em>Rokok Batang</em>.</p>
                                    <span class="s-steps-eg">Bungkus −1 · Batang +16 · modal Rp 30.000 ÷ 16 = Rp 1.875</span>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <div class="s-guide-note is-warn">
                        <i data-lucide="triangle-alert"></i>
                        <span>Dengan cara B, kasir <b>tidak bisa menjual batang</b> sebelum ada bungkus yang dibuka di sini,
                            dan setiap pembukaan harus dicatat manual. Karena itu untuk rokok pakai cara <b>A</b>.
                            Salah buka? Hapus dari riwayat, stok kembali.</span>
                    </div>
                </div>
            </div>

            <div class="p-modal-foot">
                <button type="button" class="p-btn p-btn-primary" data-guide-close>Mengerti</button>
            </div>
        </div>
    </div>
@endonce

@include('pages.products._guide-multiunit')
@include('partials.guide-script')
