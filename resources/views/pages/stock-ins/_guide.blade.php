{{-- ============================================================
     PANDUAN STOK MASUK (modal)
     Buka dengan: data-guide-open="stockInGuide" (+ data-guide-tab="nota|satuan|expire")
============================================================ --}}
@once
    <div class="p-modal s-guide" id="stockInGuide" aria-hidden="true">
        <div class="p-modal-backdrop" data-guide-close></div>

        <div class="p-modal-dialog is-wide crud-page" role="dialog" aria-modal="true" aria-labelledby="stockInGuideTitle">
            <div class="s-guide-hero">
                <span class="s-guide-glow"></span>
                <div class="s-guide-hero-top">
                    <span class="s-guide-badge"><i data-lucide="sparkles"></i> Panduan</span>
                    <button type="button" class="s-guide-close" data-guide-close aria-label="Tutup">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <h2 id="stockInGuideTitle">Catat stok masuk<br>tanpa salah hitung</h2>
                <p>Setiap nota menambah stok, memperbarui modal produk, dan untuk produk ber-expire
                    membuat batch baru yang dijual kasir sesuai urutan tanggal.</p>

                <div class="s-guide-tabs" role="tablist">
                    <button type="button" role="tab" class="is-active" data-guide-tab-btn="nota">
                        <i data-lucide="file-text"></i> Nota &amp; bayar
                    </button>
                    <button type="button" role="tab" data-guide-tab-btn="satuan">
                        <i data-lucide="boxes"></i> Satuan &amp; harga
                    </button>
                    <button type="button" role="tab" data-guide-tab-btn="expire">
                        <i data-lucide="calendar-clock"></i> Produk expire
                    </button>
                </div>
            </div>

            <div class="p-modal-body s-guide-body">
                {{-- TAB 1: NOTA & PEMBAYARAN --}}
                <div class="s-guide-pane is-active" data-guide-pane="nota">
                    <ol class="s-steps">
                        <li>
                            <span class="s-steps-icon"><i data-lucide="truck"></i></span>
                            <div>
                                <strong>Pilih supplier &amp; tanggal terima</strong>
                                <p>Tanggal terima adalah hari barang datang (tidak bisa tanggal yang akan datang).
                                    No. nota boleh kosong, tapi memudahkan dicari nanti.</p>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon"><i data-lucide="wallet"></i></span>
                            <div>
                                <strong>Lunas atau Tempo</strong>
                                <p><b>Lunas</b>: uang dihitung keluar pada tanggal terima.
                                    <b>Tempo</b>: tercatat sebagai hutang ke supplier. Isi jatuh tempo
                                    (ada tombol cepat <span class="s-chip">7</span> <span class="s-chip">14</span>
                                    <span class="s-chip">30</span> hari), lalu lunasi nanti lewat tombol
                                    <b>✓</b> di daftar stok masuk.</p>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon"><i data-lucide="package-plus"></i></span>
                            <div>
                                <strong>Tambah barang, lalu simpan</strong>
                                <p>Ketik nama produk lalu <b>Enter</b>, isi jumlah dan harga beli. Saat disimpan,
                                    stok langsung bertambah dan modal produk mengikuti harga beli terakhir.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="s-guide-note is-warn">
                        <i data-lucide="triangle-alert"></i>
                        <span><b>Edit / hapus nota</b>: stok dari nota itu dikembalikan dulu lalu dihitung ulang.
                            Kalau batch expire di nota itu <b>sudah ada yang terjual</b>, nota tidak bisa diubah;
                            catat koreksinya lewat menu <em>Expire &amp; Barang Keluar</em>.</span>
                    </div>
                </div>

                {{-- TAB 2: SATUAN & HARGA --}}
                <div class="s-guide-pane" data-guide-pane="satuan" hidden>
                    <div class="s-guide-note">
                        <i data-lucide="info"></i>
                        <span>Harga beli diisi <b>per satuan yang dipilih</b>, persis seperti di nota supplier.
                            Sistem mengubahnya ke satuan terkecil untuk stok dan modal.</span>
                    </div>

                    <div class="s-calc">
                        <div class="s-calc-row">
                            <span>Di nota</span>
                            <b>2 SLOP × Rp 250.000</b>
                        </div>
                        <div class="s-calc-arrow"><i data-lucide="arrow-down"></i> 1 SLOP isi 10 BUNGKUS</div>
                        <div class="s-calc-grid">
                            <div><small>Stok bertambah</small><strong>+20 BUNGKUS</strong></div>
                            <div><small>Modal per bungkus</small><strong>Rp 25.000</strong></div>
                            <div><small>Subtotal nota</small><strong>Rp 500.000</strong></div>
                        </div>
                    </div>

                    <ol class="s-steps">
                        <li>
                            <span class="s-steps-icon"><i data-lucide="list-checks"></i></span>
                            <div>
                                <strong>Pilih satuan sesuai nota</strong>
                                <p>Supplier mengirim per DUS? Pilih DUS. Daftar satuan &amp; isinya diatur di
                                    <em>Produk → Edit</em>.
                                    <button type="button" class="s-guide-link" data-guide-open="multiUnitGuide" data-guide-tab="beli">Contoh lengkap: rokok per slop</button></p>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon"><i data-lucide="badge-check"></i></span>
                            <div>
                                <strong>Baca petunjuk harga di tiap baris</strong>
                                <p><span class="s-dot is-ok"></span> Hijau: sama atau lebih murah dari harga termurah sebelumnya.
                                    <br><span class="s-dot is-warn"></span> Oranye: lebih mahal dari supplier lain, atau modal
                                    sudah sama / di atas harga jual.</p>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon"><i data-lucide="repeat"></i></span>
                            <div>
                                <strong>Harga terisi otomatis</strong>
                                <p>Diambil dari harga terakhir supplier itu. Ganti supplier atau satuan → harga yang
                                    belum Anda ubah ikut menyesuaikan.</p>
                            </div>
                        </li>
                    </ol>
                </div>

                {{-- TAB 3: PRODUK EXPIRE --}}
                <div class="s-guide-pane" data-guide-pane="expire" hidden>
                    <ol class="s-steps is-numbered">
                        <li>
                            <span class="s-steps-icon">1</span>
                            <div>
                                <strong>Isi tanggal expire (wajib)</strong>
                                <p>Produk berlabel <span class="s-chip is-amber">Expire</span> punya kolom tanggal expire.
                                    Salin dari kemasan. No. batch boleh dikosongkan.</p>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon">2</span>
                            <div>
                                <strong>Tanggal berbeda? Tambah baris lagi</strong>
                                <p>Cari produk yang sama sekali lagi. Setiap baris menjadi satu batch.</p>
                                <div class="s-batches">
                                    <span><b>24</b> botol · exp 20/04/2027</span>
                                    <span><b>24</b> botol · exp 05/05/2027</span>
                                </div>
                            </div>
                        </li>
                        <li>
                            <span class="s-steps-icon">3</span>
                            <div>
                                <strong>Kasir menjual yang paling dekat expire</strong>
                                <p>Otomatis, termasuk saat HP offline. Batch yang lewat tanggal tidak ikut dijual.
                                    Pantau di menu <em>Expire &amp; Barang Keluar</em>.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="s-guide-note">
                        <i data-lucide="info"></i>
                        <span>Stok produk ber-expire <b>hanya bertambah lewat Stok Masuk</b>, supaya setiap barang
                            selalu punya tanggal expire.</span>
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
