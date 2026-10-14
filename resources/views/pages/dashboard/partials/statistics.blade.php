@php
    $cashPct = max(0, min(100, (float) ($cashPercent ?? 0)));
    $transferPct = max(0, min(100, (float) ($transferPercent ?? 0)));

    // komposisi penjualan HARI INI: cash / transfer / tempo
    $todaySales = (float) ($todayCash ?? 0) + (float) ($todayTransfer ?? 0) + (float) ($todayTempo ?? 0);
    $tCash = $todaySales > 0 ? round((($todayCash ?? 0) / $todaySales) * 100, 1) : 0;
    $tTransfer = $todaySales > 0 ? round((($todayTransfer ?? 0) / $todaySales) * 100, 1) : 0;
    $tTempo = $todaySales > 0 ? max(0, 100 - $tCash - $tTransfer) : 0;
    $expensePct = max(0, min(100, (float) ($expensePercent ?? 0)));
@endphp

{{-- =========================================================
     HARI INI
========================================================= --}}
<section aria-labelledby="today-title">
    <div class="section-title">
        <h2 id="today-title">Hari ini</h2>
        <span class="today-range">
            Transaksi tanggal <b>{{ now()->translatedFormat('d F Y') }}</b> (00.00–23.59)
            · {{ $num($todayOrders ?? 0) }} transaksi
        </span>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card">
            <span class="stat-icon orange"><i data-lucide="wallet"></i></span>
            <div class="stat-content">
                <div class="stat-title">Penjualan</div>
                <div class="stat-number">{{ $rp($todayRevenue ?? 0) }}</div>
                <div class="stat-desc">
                    <i data-lucide="trending-up"></i>
                    {{ $num($todayOrders ?? 0) }}
                    transaksi{{ ($todayTempo ?? 0) > 0 ? ' · tempo ' . $rp($todayTempo) : '' }}
                </div>
            </div>
        </div>

        <div class="stat-card">
            <span class="stat-icon red"><i data-lucide="wallet-minimal"></i></span>
            <div class="stat-content">
                <div class="stat-title">Pengeluaran</div>
                <div class="stat-number">{{ $rp($todayExpense ?? 0) }}</div>
                <div class="stat-desc"><i data-lucide="arrow-down-right"></i> Biaya operasional</div>
            </div>
        </div>

        <div class="stat-card">
            <span class="stat-icon green"><i data-lucide="chart-no-axes-combined"></i></span>
            <div class="stat-content">
                <div class="stat-title">Laba Bersih</div>
                <div class="stat-number">{{ $rp($todayNetIncome ?? 0) }}</div>
                <div class="profit-split">
                    <span>Sudah diterima <b>{{ $rp($todayProfitReceived ?? 0) }}</b></span>
                    <span>Masih di piutang <b class="is-pending">{{ $rp($todayProfitPending ?? 0) }}</b></span>
                </div>
                <div class="stat-desc">Penjualan − modal {{ $rp($todayCogs ?? 0) }} − pajak − pengeluaran</div>
            </div>
        </div>

        {{-- Cash & transfer digabung dalam satu kartu --}}
        <div class="stat-card">
            <span class="stat-icon slate"><i data-lucide="banknote"></i></span>
            <div class="stat-content">
                <div class="stat-title">Uang Masuk</div>
                <div class="stat-number">{{ $rp($todayCashIn ?? 0) }}</div>
                <div class="pay-split pay-split-3">
                    <div><small>Cash</small><strong>{{ $rp($todayCash ?? 0) }}</strong></div>
                    <div><small>Transfer</small><strong>{{ $rp($todayTransfer ?? 0) }}</strong></div>
                    <div><small>Tempo</small><strong class="is-tempo">{{ $rp($todayTempo ?? 0) }}</strong></div>
                </div>
                <div class="split-bar" role="img"
                    aria-label="Penjualan hari ini: cash {{ $tCash }} persen, transfer {{ $tTransfer }} persen, tempo {{ $tTempo }} persen">
                    <span class="is-cash" style="width: {{ $tCash }}%"></span>
                    <span class="is-transfer" style="width: {{ $tTransfer }}%"></span>
                    <span class="is-tempo" style="width: {{ $tTempo }}%"></span>
                </div>
                @if (($todayTempoReceived ?? 0) > 0)
                    <div class="stat-desc">Termasuk DP/cicilan tempo {{ $rp($todayTempoReceived) }}</div>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- =========================================================
     KATALOG & AKUN
========================================================= --}}
<section aria-labelledby="catalog-title">
    <div class="section-title">
        <h2 id="catalog-title">Katalog & akun</h2>
        @if (Route::has('products.index'))
            <a href="{{ route('products.index') }}">Kelola produk <i data-lucide="arrow-right"></i></a>
        @endif
    </div>

    <div class="dashboard-grid compact">
        <div class="stat-card">
            <span class="stat-icon orange"><i data-lucide="package"></i></span>
            <div class="stat-content">
                <div class="stat-title">Produk</div>
                <div class="stat-number">{{ $num($totalProducts ?? 0) }}</div>
                <div class="stat-desc">{{ $num($activeProducts ?? 0) }} aktif · {{ $num($inactiveProducts ?? 0) }}
                    nonaktif · {{ $num($totalCategories ?? 0) }} kategori</div>
            </div>
        </div>

        {{-- stok yang BELUM terjual × harga jual --}}
        <div class="stat-card">
            <span class="stat-icon yellow"><i data-lucide="warehouse"></i></span>
            <div class="stat-content">
                <div class="stat-title">Nilai Jual Stok</div>
                <div class="stat-number">{{ $rp($stockValue ?? 0) }}</div>
                <div class="stat-desc">{{ $qty($totalStock ?? 0) }} item belum terjual · harga jual</div>
            </div>
        </div>

        {{-- stok yang belum terjual × harga beli terakhir --}}
        <div class="stat-card">
            <span class="stat-icon slate"><i data-lucide="hand-coins"></i></span>
            <div class="stat-content">
                <div class="stat-title">Modal Stok</div>
                <div class="stat-number">{{ $rp($stockCost ?? 0) }}</div>
                <div class="stat-desc">
                    @if (($stockNoCost ?? 0) > 0)
                        {{ $num($stockNoCost) }} produk belum ada harga beli
                    @else
                        Potensi untung {{ $rp($stockMargin ?? 0) }}
                    @endif
                </div>
            </div>
        </div>

        <div class="stat-card">
            <span class="stat-icon green"><i data-lucide="users"></i></span>
            <div class="stat-content">
                <div class="stat-title">Pengguna</div>
                <div class="stat-number">{{ $num($totalUsers ?? 0) }}</div>
                <div class="stat-desc">Total akun sistem</div>
            </div>
        </div>
    </div>
</section>

{{-- =========================================================
     RINCIAN PENGELUARAN BULAN INI
========================================================= --}}
<section class="expense-summary-card dash-defer">
    <div class="expense-summary-header">
        <div>
            <h3>Pengeluaran Bulan Ini</h3>
            <p>Rincian biaya operasional toko.</p>
        </div>

        @if (Route::has('expenses.index'))
            <a href="{{ route('expenses.index') }}" class="expense-summary-link">
                Lihat pengeluaran <i data-lucide="arrow-up-right"></i>
            </a>
        @endif
    </div>

    <div class="expense-summary-grid">
        <div class="expense-category">
            <span class="expense-category-icon salary"><i data-lucide="users-round"></i></span>
            <div class="expense-category-content">
                <span>Gaji Karyawan</span>
                <strong>{{ $rp($expenseGaji ?? 0) }}</strong>
            </div>
        </div>

        {{-- Data dari kategori pengeluaran "dapur" — ganti nama kategorinya di modul Pengeluaran --}}
        <div class="expense-category">
            <span class="expense-category-icon operational"><i data-lucide="store"></i></span>
            <div class="expense-category-content">
                <span>Operasional Toko</span>
                <strong>{{ $rp($expenseDapur ?? 0) }}</strong>
            </div>
        </div>

        <div class="expense-category">
            <span class="expense-category-icon electricity"><i data-lucide="zap"></i></span>
            <div class="expense-category-content">
                <span>Listrik</span>
                <strong>{{ $rp($expenseListrik ?? 0) }}</strong>
            </div>
        </div>

        <div class="expense-category">
            <span class="expense-category-icon unexpected"><i data-lucide="triangle-alert"></i></span>
            <div class="expense-category-content">
                <span>Tak Terduga</span>
                <strong>{{ $rp($expenseTakTerduga ?? 0) }}</strong>
            </div>
        </div>
    </div>

    <div class="expense-total">
        <div>
            <span>Total Pengeluaran</span>
            <strong>{{ $rp($monthExpense ?? 0) }}</strong>
        </div>

        <div class="expense-percentage">
            <div class="progress" role="img" aria-label="{{ $expensePct }} persen dari pendapatan">
                <div class="progress-bar" style="width: {{ $expensePct }}%"></div>
            </div>
            <strong>{{ number_format($expensePercent ?? 0, 1, ',', '.') }}%</strong>
            <small>dari penjualan</small>
        </div>
    </div>
</section>

@once
    @push('css')
        <style>
            .profit-split {
                display: flex;
                flex-direction: column;
                gap: 2px;
                margin: 4px 0;
                font-size: 12.5px;
                color: #4a505a;
            }

            .profit-split b {
                color: #15171b;
            }

            .profit-split b.is-pending {
                color: #b7791f;
            }

            .pay-split .is-tempo {
                color: #b7791f;
            }

            .split-bar .is-tempo {
                background: #FFB020;
            }

            .dot.tempo {
                background: #FFB020;
            }
        </style>
    @endpush
@endonce
